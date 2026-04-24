<?php

namespace Tests\Unit\Telesale;

use App\Common\Constants\Customer\CustomerType;
use App\Common\Constants\Customer\ReasonInteraction;
use App\Common\Constants\Interaction\InteractionStatus;
use App\Common\Constants\User\UserRole;
use App\Filament\Clusters\Telesale\Resources\CustomerOperations\Actions\InteractionStepActions;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class InteractionStepActionsTest extends TestCase
{
    public function test_handle_action_updates_customer_interaction_status_and_refreshes_table(): void
    {
        $this->prepareInteractionTables();
        [$customer, $user] = $this->makeCustomerWithActor(InteractionStatus::FIRST_CALL->value);
        $livewire = new class {
            public int $resetTableCalls = 0;

            public function resetTable(): void
            {
                $this->resetTableCalls++;
            }
        };

        $this->actingAs($user);

        $this->invokeHandleAction($customer, [
            'reason' => ReasonInteraction::NO_ANSWER->value,
            'note' => 'Khach khong nghe may',
        ], $livewire);

        $customer->refresh();

        $this->assertSame(InteractionStatus::SECOND_CALL->value, (int) $customer->interaction_status);
        $this->assertNull($customer->next_action_at);
        $this->assertSame(1, $livewire->resetTableCalls);
    }

    public function test_handle_action_updates_next_action_at_for_callback_flow(): void
    {
        $this->prepareInteractionTables();
        [$customer, $user] = $this->makeCustomerWithActor(InteractionStatus::FIRST_CALL->value);
        $livewire = new class {
            public int $resetTableCalls = 0;

            public function resetTable(): void
            {
                $this->resetTableCalls++;
            }
        };
        $nextActionAt = now()->addDay()->startOfHour();

        $this->actingAs($user);

        $this->invokeHandleAction($customer, [
            'reason' => ReasonInteraction::CALL_BACK->value,
            'note' => 'Hen goi lai',
            'next_action_at' => $nextActionAt,
        ], $livewire);

        $customer->refresh();

        $this->assertSame(InteractionStatus::SECOND_CARE->value, (int) $customer->interaction_status);
        $this->assertNotNull($customer->next_action_at);
        $this->assertTrue($customer->next_action_at->equalTo($nextActionAt));
        $this->assertSame(1, $livewire->resetTableCalls);
    }

    protected function invokeHandleAction(Customer $customer, array $data, object $livewire): void
    {
        $method = new ReflectionMethod(InteractionStepActions::class, 'handleAction');
        $method->setAccessible(true);
        $method->invoke(null, $customer, $data, $livewire);
    }

    protected function makeCustomerWithActor(int $interactionStatus): array
    {
        DB::table('customers')->delete();
        DB::table('customer_interactions')->delete();
        DB::table('customer_status_logs')->delete();

        $user = new User();
        $user->id = 1;
        $user->organization_id = 1;
        $user->role = UserRole::SALE->value;
        $user->name = 'Sale User';
        $user->username = 'sale_user';
        $user->email = 'sale@example.com';

        $customer = Customer::query()->create([
            'organization_id' => 1,
            'username' => 'Khach hang test',
            'phone' => '0912345678',
            'customer_type' => CustomerType::NEW->value,
            'interaction_status' => $interactionStatus,
        ]);

        return [$customer, $user];
    }

    protected function prepareInteractionTables(): void
    {
        if (! Schema::hasTable('customers')) {
            Schema::create('customers', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('organization_id')->nullable();
                $table->string('username')->nullable();
                $table->string('phone')->nullable();
                $table->unsignedTinyInteger('customer_type')->nullable();
                $table->unsignedTinyInteger('interaction_status')->nullable();
                $table->timestamp('next_action_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('customer_interactions')) {
            Schema::create('customer_interactions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('customer_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('type')->nullable();
                $table->unsignedTinyInteger('direction')->nullable();
                $table->unsignedTinyInteger('attempt_no')->nullable();
                $table->unsignedTinyInteger('care_no')->nullable();
                $table->unsignedTinyInteger('status')->nullable();
                $table->text('content')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('interacted_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('customer_status_logs')) {
            Schema::create('customer_status_logs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('customer_id');
                $table->unsignedTinyInteger('from_status')->nullable();
                $table->unsignedTinyInteger('to_status')->nullable();
                $table->unsignedTinyInteger('reason')->nullable();
                $table->text('note')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();
            });
        }
    }
}
