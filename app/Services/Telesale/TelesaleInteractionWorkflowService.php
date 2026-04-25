<?php

namespace App\Services\Telesale;

use App\Common\Constants\Customer\ReasonInteraction;
use App\Common\Constants\Interaction\InteractionDirectionType;
use App\Common\Constants\Interaction\InteractionStatus;
use App\Common\Constants\Interaction\InteractionType;
use App\Models\Customer;
use App\Models\CustomerInteraction;
use App\Models\CustomerStatusLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TelesaleInteractionWorkflowService
{
    public function execute(TelesaleInteractionCommand $command): TelesaleInteractionResult
    {
        $reason = ReasonInteraction::tryFrom($command->reason);

        if (! $reason) {
            throw ValidationException::withMessages([
                'reason' => __('telesale.messages.invalid_interaction_reason'),
            ]);
        }

        return DB::transaction(function () use ($command, $reason) {
            /** @var Customer $customer */
            $customer = Customer::query()
                ->lockForUpdate()
                ->findOrFail($command->customerId);

            $currentStatus = (int) $customer->interaction_status;
            $nextActionAt = $command->normalizedNextActionAt();

            $this->validate($reason, $currentStatus, $nextActionAt, $command->context);

            $nextStatus = $this->resolveNextStatus($reason, $currentStatus);

            $interaction = $customer->interactions()->create([
                'type' => $this->resolveInteractionType($currentStatus, $command->context)->value,
                'direction' => $this->resolveDirection($currentStatus),
                'attempt_no' => $this->resolveAttemptNo($currentStatus),
                'care_no' => $this->resolveCareNo($currentStatus),
                'status' => $nextStatus,
                'user_id' => $command->actorId,
                'content' => $command->note,
                'metadata' => [
                    'context' => $command->context,
                    'reason' => $reason->value,
                    'reason_label' => $reason->label(),
                    'from_status' => $currentStatus,
                    'to_status' => $nextStatus,
                ],
                'interacted_at' => now(),
            ]);

            $statusLog = $customer->customerStatusLog()->create([
                'from_status' => $currentStatus,
                'to_status' => $nextStatus,
                'reason' => $reason->value,
                'note' => $command->note,
                'user_id' => $command->actorId,
            ]);

            $customer->update([
                'interaction_status' => $nextStatus,
                'next_action_at' => ReasonInteraction::requiresScheduling($reason->value)
                    ? $nextActionAt
                    : null,
            ]);

            $customer->refresh();

            return new TelesaleInteractionResult(
                customer: $customer,
                interaction: $interaction,
                statusLog: $statusLog,
                reason: $reason,
                fromStatus: $currentStatus,
                toStatus: $nextStatus,
                nextActionAt: $customer->next_action_at,
                message: __('common.success.update_success'),
            );
        });
    }

    public function resolveNextStatus(ReasonInteraction $reason, int $currentStatus): int
    {
        return match ($reason) {
            ReasonInteraction::CLOSING_ORDER,
            ReasonInteraction::GOOD_PERFORMANCE => InteractionStatus::RECEIVED->value,

            ReasonInteraction::NO_NEED => InteractionStatus::UN_CARE->value,
            ReasonInteraction::SUBSCRIBERS,
            ReasonInteraction::POOR_PERFORMANCE => InteractionStatus::INEFFICIENT->value,

            ReasonInteraction::CALL_BACK,
            ReasonInteraction::THINK_MORE => InteractionStatus::SECOND_CARE->value,

            ReasonInteraction::NO_ANSWER,
            ReasonInteraction::BUSY => $this->resolveNextCallStatus($currentStatus),
        };
    }

    public function isWorkflowStatus(int $status): bool
    {
        return in_array($status, [
            InteractionStatus::FIRST_CALL->value,
            InteractionStatus::SECOND_CALL->value,
            InteractionStatus::THIRD_CALL->value,
            InteractionStatus::FOURTH_CALL->value,
            InteractionStatus::FIFTH_CALL->value,
            InteractionStatus::SIXTH_CALL->value,
            InteractionStatus::USER_MANUAL->value,
            InteractionStatus::SECOND_CARE->value,
            InteractionStatus::THIRD_CARE->value,
        ], true);
    }

    protected function validate(ReasonInteraction $reason, int $currentStatus, mixed $nextActionAt, string $context): void
    {
        if (! $this->isWorkflowStatus($currentStatus)) {
            throw ValidationException::withMessages([
                'reason' => __('telesale.messages.invalid_interaction_status'),
            ]);
        }

        $nextActionField = $context === 'edit_form'
            ? 'interaction_next_action_at'
            : 'next_action_at';

        if (ReasonInteraction::requiresScheduling($reason->value) && blank($nextActionAt)) {
            throw ValidationException::withMessages([
                $nextActionField => __('common.error.required'),
            ]);
        }

        if (ReasonInteraction::requiresScheduling($reason->value) && filled($nextActionAt) && $nextActionAt->lt(now())) {
            throw ValidationException::withMessages([
                $nextActionField => __('telesale.messages.next_action_must_be_future'),
            ]);
        }
    }

    protected function resolveNextCallStatus(int $currentStatus): int
    {
        return match ($currentStatus) {
            InteractionStatus::FIRST_CALL->value => InteractionStatus::SECOND_CALL->value,
            InteractionStatus::SECOND_CALL->value => InteractionStatus::THIRD_CALL->value,
            InteractionStatus::THIRD_CALL->value => InteractionStatus::FOURTH_CALL->value,
            InteractionStatus::FOURTH_CALL->value => InteractionStatus::FIFTH_CALL->value,
            InteractionStatus::FIFTH_CALL->value => InteractionStatus::SIXTH_CALL->value,
            InteractionStatus::SIXTH_CALL->value => InteractionStatus::USER_MANUAL->value,
            InteractionStatus::USER_MANUAL->value => InteractionStatus::SECOND_CARE->value,
            InteractionStatus::SECOND_CARE->value => InteractionStatus::THIRD_CARE->value,
            InteractionStatus::THIRD_CARE->value => InteractionStatus::RECEIVED->value,
            default => $currentStatus,
        };
    }

    protected function resolveInteractionType(int $currentStatus, string $context): InteractionType
    {
        if (
            $context === 'table_action'
            || in_array($currentStatus, [
                InteractionStatus::FIRST_CALL->value,
                InteractionStatus::SECOND_CALL->value,
                InteractionStatus::THIRD_CALL->value,
                InteractionStatus::FOURTH_CALL->value,
                InteractionStatus::FIFTH_CALL->value,
                InteractionStatus::SIXTH_CALL->value,
            ], true)
        ) {
            return InteractionType::CALL;
        }

        return InteractionType::NOTE;
    }

    protected function resolveDirection(int $currentStatus): ?int
    {
        return in_array($currentStatus, [
            InteractionStatus::FIRST_CALL->value,
            InteractionStatus::SECOND_CALL->value,
            InteractionStatus::THIRD_CALL->value,
            InteractionStatus::FOURTH_CALL->value,
            InteractionStatus::FIFTH_CALL->value,
            InteractionStatus::SIXTH_CALL->value,
        ], true)
            ? InteractionDirectionType::OUTBOUND->value
            : null;
    }

    protected function resolveAttemptNo(int $currentStatus): ?int
    {
        return match ($currentStatus) {
            InteractionStatus::FIRST_CALL->value => 1,
            InteractionStatus::SECOND_CALL->value => 2,
            InteractionStatus::THIRD_CALL->value => 3,
            InteractionStatus::FOURTH_CALL->value => 4,
            InteractionStatus::FIFTH_CALL->value => 5,
            InteractionStatus::SIXTH_CALL->value => 6,
            default => 1,
        };
    }

    protected function resolveCareNo(int $currentStatus): ?int
    {
        return match ($currentStatus) {
            InteractionStatus::FIRST_CALL->value,
            InteractionStatus::SECOND_CALL->value,
            InteractionStatus::THIRD_CALL->value,
            InteractionStatus::FOURTH_CALL->value,
            InteractionStatus::FIFTH_CALL->value,
            InteractionStatus::SIXTH_CALL->value,
            InteractionStatus::USER_MANUAL->value => 1,
            InteractionStatus::SECOND_CARE->value => 2,
            InteractionStatus::THIRD_CARE->value => 3,
            default => 1,
        };
    }
}
