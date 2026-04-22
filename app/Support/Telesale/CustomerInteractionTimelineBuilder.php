<?php

namespace App\Support\Telesale;

use App\Common\Constants\Customer\ReasonInteraction;
use App\Common\Constants\Interaction\InteractionDirectionType;
use App\Common\Constants\Interaction\InteractionStatus;
use App\Common\Constants\Interaction\InteractionType;
use App\Models\Customer;
use App\Models\CustomerInteraction;
use App\Models\CustomerStatusLog;
use Illuminate\Support\Collection;

class CustomerInteractionTimelineBuilder
{
    public function build(?Customer $customer): Collection
    {
        if (! $customer) {
            return collect();
        }

        $interactions = $customer->interactions()->with('user')->get();
        $statusLogs = $customer->customerStatusLog()->with('user')->get()->values();

        $interactionEntries = $interactions->map(function (CustomerInteraction $interaction) use (&$statusLogs): array {
            $matchedStatusLog = $this->takeMatchingStatusLog($interaction, $statusLogs);
            $type = InteractionType::tryFrom((int) $interaction->type) ?? InteractionType::NOTE;
            $metadata = $interaction->metadata ?? [];
            $reason = $matchedStatusLog?->reason ?? data_get($metadata, 'reason');
            $statusValue = (int) ($interaction->status ?? data_get($metadata, 'to_status', 0));

            return [
                'title' => $statusValue > 0
                    ? InteractionStatus::getLabel($statusValue)
                    : InteractionType::getLabel($type->value),
                'icon' => $type->getIcon(),
                'actor' => $interaction->user?->name ?? __('telesale.messages.system'),
                'occurred_at' => $interaction->interacted_at ?? $interaction->created_at,
                'status_label' => filled($interaction->status)
                    ? InteractionStatus::getLabelStatus((int) $interaction->status)
                    : ($matchedStatusLog && filled($matchedStatusLog->to_status)
                        ? InteractionStatus::getLabelStatus((int) $matchedStatusLog->to_status)
                        : null),
                'status_style' => filled($interaction->status)
                    ? InteractionStatus::getStyle((int) $interaction->status)
                    : ($matchedStatusLog && filled($matchedStatusLog->to_status)
                        ? InteractionStatus::getStyle((int) $matchedStatusLog->to_status)
                        : null),
                'direction_label' => filled($interaction->direction)
                    ? InteractionDirectionType::label((int) $interaction->direction)
                    : null,
                'content' => $interaction->content ?: $matchedStatusLog?->note,
                'duration' => $interaction->duration,
                'reason_label' => filled($reason) ? ReasonInteraction::getLabel((int) $reason) : null,
            ];
        });

        $legacyStatusEntries = $statusLogs->map(function (CustomerStatusLog $statusLog): array {
            return [
                'title' => __('telesale.form.result'),
                'icon' => InteractionType::NOTE->getIcon(),
                'actor' => $statusLog->user?->name ?? __('telesale.messages.system'),
                'occurred_at' => $statusLog->created_at,
                'status_label' => filled($statusLog->to_status)
                    ? InteractionStatus::getLabelStatus((int) $statusLog->to_status)
                    : null,
                'status_style' => filled($statusLog->to_status)
                    ? InteractionStatus::getStyle((int) $statusLog->to_status)
                    : null,
                'direction_label' => null,
                'content' => $statusLog->note,
                'duration' => null,
                'reason_label' => filled($statusLog->reason) ? ReasonInteraction::getLabel((int) $statusLog->reason) : null,
            ];
        });

        return $interactionEntries
            ->merge($legacyStatusEntries)
            ->filter(fn (array $entry) => filled($entry['occurred_at']))
            ->sortByDesc(fn (array $entry) => $entry['occurred_at'])
            ->values();
    }

    protected function takeMatchingStatusLog(CustomerInteraction $interaction, Collection &$statusLogs): ?CustomerStatusLog
    {
        $matchedKey = $statusLogs->search(function (CustomerStatusLog $statusLog) use ($interaction): bool {
            $reason = data_get($interaction->metadata ?? [], 'reason');
            $interactionTime = $interaction->interacted_at ?? $interaction->created_at;
            $sameTime = $interactionTime && $interactionTime->diffInSeconds($statusLog->created_at) <= 5;

            return $sameTime
                && (int) $statusLog->user_id === (int) $interaction->user_id
                && (int) $statusLog->to_status === (int) ($interaction->status ?? 0)
                && (int) ($statusLog->reason ?? 0) === (int) ($reason ?? 0)
                && (string) ($statusLog->note ?? '') === (string) ($interaction->content ?? '');
        });

        if ($matchedKey === false) {
            return null;
        }

        $matched = $statusLogs->get($matchedKey);
        $statusLogs->forget($matchedKey);
        $statusLogs = $statusLogs->values();

        return $matched;
    }
}
