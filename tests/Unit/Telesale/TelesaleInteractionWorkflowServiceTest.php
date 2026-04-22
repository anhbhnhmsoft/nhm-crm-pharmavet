<?php

namespace Tests\Unit\Telesale;

use App\Common\Constants\Customer\CustomerType;
use App\Common\Constants\Customer\ReasonInteraction;
use App\Common\Constants\Interaction\InteractionDirectionType;
use App\Common\Constants\Interaction\InteractionStatus;
use App\Common\Constants\Interaction\InteractionType;
use App\Common\Constants\User\UserRole;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\User;
use App\Services\Telesale\TelesaleInteractionCommand;
use App\Services\Telesale\TelesaleInteractionWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TelesaleInteractionWorkflowServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_answer_advances_to_next_call_and_creates_full_audit_trail(): void
    {
        [$customer, $user] = $this->makeCustomerWithActor(InteractionStatus::FIRST_CALL->value);
        $service = app(TelesaleInteractionWorkflowService::class);

        $result = $service->execute(new TelesaleInteractionCommand(
            customerId: (int) $customer->getKey(),
            actorId: (int) $user->getKey(),
            currentStatus: InteractionStatus::FIRST_CALL->value,
            reason: ReasonInteraction::NO_ANSWER->value,
            note: 'Khách không nghe máy',
            context: 'table_action',
        ));

        $customer->refresh();

        $this->assertSame(InteractionStatus::SECOND_CALL->value, (int) $customer->interaction_status);
        $this->assertNull($customer->next_action_at);
        $this->assertSame(InteractionStatus::FIRST_CALL->value, $result->fromStatus);
        $this->assertSame(InteractionStatus::SECOND_CALL->value, $result->toStatus);

        $this->assertDatabaseHas('customer_interactions', [
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'type' => InteractionType::CALL->value,
            'direction' => InteractionDirectionType::OUTBOUND->value,
            'status' => InteractionStatus::SECOND_CALL->value,
            'content' => 'Khách không nghe máy',
        ]);

        $this->assertDatabaseHas('customer_status_logs', [
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'from_status' => InteractionStatus::FIRST_CALL->value,
            'to_status' => InteractionStatus::SECOND_CALL->value,
            'reason' => ReasonInteraction::NO_ANSWER->value,
            'note' => 'Khách không nghe máy',
        ]);
    }

    public function test_callback_requires_scheduling_time(): void
    {
        [$customer, $user] = $this->makeCustomerWithActor(InteractionStatus::SECOND_CALL->value);
        $service = app(TelesaleInteractionWorkflowService::class);

        $this->expectException(ValidationException::class);

        try {
            $service->execute(new TelesaleInteractionCommand(
                customerId: (int) $customer->getKey(),
                actorId: (int) $user->getKey(),
                currentStatus: InteractionStatus::SECOND_CALL->value,
                reason: ReasonInteraction::CALL_BACK->value,
                note: 'Hẹn gọi lại',
                context: 'table_action',
            ));
        } finally {
            $customer->refresh();

            $this->assertSame(InteractionStatus::SECOND_CALL->value, (int) $customer->interaction_status);
            $this->assertDatabaseCount('customer_interactions', 0);
            $this->assertDatabaseCount('customer_status_logs', 0);
        }
    }

    public function test_callback_sets_second_care_and_next_action_at(): void
    {
        [$customer, $user] = $this->makeCustomerWithActor(InteractionStatus::FIRST_CALL->value);
        $service = app(TelesaleInteractionWorkflowService::class);
        $nextActionAt = now()->addDay()->startOfHour();

        $service->execute(new TelesaleInteractionCommand(
            customerId: (int) $customer->getKey(),
            actorId: (int) $user->getKey(),
            currentStatus: InteractionStatus::FIRST_CALL->value,
            reason: ReasonInteraction::CALL_BACK->value,
            note: 'Khách xin gọi lại ngày mai',
            nextActionAt: $nextActionAt,
            context: 'edit_form',
        ));

        $customer->refresh();

        $this->assertSame(InteractionStatus::SECOND_CARE->value, (int) $customer->interaction_status);
        $this->assertNotNull($customer->next_action_at);
        $this->assertTrue($customer->next_action_at->equalTo($nextActionAt));
    }

    public function test_closing_order_moves_customer_to_received(): void
    {
        [$customer, $user] = $this->makeCustomerWithActor(InteractionStatus::THIRD_CARE->value);
        $service = app(TelesaleInteractionWorkflowService::class);

        $service->execute(new TelesaleInteractionCommand(
            customerId: (int) $customer->getKey(),
            actorId: (int) $user->getKey(),
            currentStatus: InteractionStatus::THIRD_CARE->value,
            reason: ReasonInteraction::CLOSING_ORDER->value,
            note: 'Đã chốt đơn thành công',
            context: 'edit_form',
        ));

        $customer->refresh();

        $this->assertSame(InteractionStatus::RECEIVED->value, (int) $customer->interaction_status);
        $this->assertNull($customer->next_action_at);
    }

    protected function makeCustomerWithActor(int $interactionStatus): array
    {
        $organization = Organization::query()->create([
            'name' => 'Org Test',
            'code' => 'ORG-' . fake()->unique()->numerify('####'),
            'product_field' => 1,
        ]);

        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'role' => UserRole::SALE->value,
        ]);

        $customer = Customer::query()->create([
            'organization_id' => $organization->id,
            'username' => 'Khach hang test',
            'phone' => '0912345678',
            'customer_type' => CustomerType::NEW->value,
            'interaction_status' => $interactionStatus,
        ]);

        return [$customer, $user];
    }
}
