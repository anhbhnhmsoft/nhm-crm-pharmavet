<?php

use App\Common\Constants\Order\GhnOrderStatus;
use App\Common\Constants\Order\OrderStatus;
use App\Common\Constants\Shipping\RequiredNote;
use App\Common\Constants\User\UserRole;
use App\Core\ServiceReturn;
use App\Filament\Clusters\Warehouse\Resources\Orders\Pages\ListOrders;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

afterEach(function (): void {
    \Mockery::close();
});

it('refreshes the order table after posting an order so status and actions update immediately', function () {
    prepareOrdersTableTestTables();
    DB::table('orders')->delete();
    DB::table('organizations')->delete();
    DB::table('accounting_periods')->delete();

    DB::table('organizations')->insert([
        'id' => 1,
        'name' => 'Test Org',
    ]);

    $order = Order::query()->create([
        'organization_id' => 1,
        'code' => 'ORD-POST-001',
        'status' => OrderStatus::CONFIRMED->value,
        'shipping_fee' => 20000,
        'total_amount' => 100000,
        'required_note' => RequiredNote::ALLOW_VIEWING_NOT_TRIAL->value,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $service = \Mockery::mock(OrderService::class);
    $service->shouldReceive('postOrder')
        ->once()
        ->withArgs(function (Order $receivedOrder, array $data) use ($order): bool {
            return $receivedOrder->is($order)
                && (float) ($data['shipping_fee'] ?? 0) === 20000.0;
        })
        ->andReturnUsing(function (Order $receivedOrder): ServiceReturn {
            $receivedOrder->update([
                'status' => OrderStatus::SHIPPING->value,
                'ghn_status' => GhnOrderStatus::READY_TO_PICK->value,
                'ghn_posted_at' => now(),
            ]);

            return ServiceReturn::success(message: __('order.notification.post_order_success'));
        });
    app()->instance(OrderService::class, $service);

    $this->actingAs(makeWarehouseOrdersTableUser());

    Livewire::test(ListOrders::class)
        ->assertTableActionVisible('post_order', $order)
        ->assertTableActionHidden('cancel_post', $order)
        ->callTableAction('post_order', $order, data: [
            'shipping_fee' => 20000,
            'weight' => 200,
            'ghn_service_type_id' => 2,
            'ghn_payment_type_id' => 2,
            'required_note' => RequiredNote::ALLOW_VIEWING_NOT_TRIAL->value,
            'insurance_value' => 0,
        ])
        ->assertTableActionHidden('post_order', $order->fresh())
        ->assertTableActionVisible('cancel_post', $order->fresh());

    expect($order->fresh()->status)->toBe(OrderStatus::SHIPPING->value);
});

it('refreshes the order table after cancelling a posted order so status and actions update immediately', function () {
    prepareOrdersTableTestTables();
    DB::table('orders')->delete();
    DB::table('organizations')->delete();
    DB::table('accounting_periods')->delete();

    DB::table('organizations')->insert([
        'id' => 1,
        'name' => 'Test Org',
    ]);

    $order = Order::query()->create([
        'organization_id' => 1,
        'code' => 'ORD-CANCEL-001',
        'status' => OrderStatus::SHIPPING->value,
        'shipping_fee' => 20000,
        'total_amount' => 100000,
        'ghn_order_code' => 'GHN-001',
        'ghn_status' => GhnOrderStatus::READY_TO_PICK->value,
        'ghn_posted_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $service = \Mockery::mock(OrderService::class);
    $service->shouldReceive('cancelOrder')
        ->once()
        ->withArgs(fn (Order $receivedOrder): bool => $receivedOrder->is($order))
        ->andReturnUsing(function (Order $receivedOrder): ServiceReturn {
            $receivedOrder->update([
                'status' => OrderStatus::CONFIRMED->value,
                'ghn_status' => GhnOrderStatus::CANCEL->value,
                'ghn_cancelled_at' => now(),
            ]);

            return ServiceReturn::success(message: __('order.notification.cancel_order_success'));
        });
    app()->instance(OrderService::class, $service);

    $this->actingAs(makeWarehouseOrdersTableUser());

    Livewire::test(ListOrders::class)
        ->assertTableActionHidden('post_order', $order)
        ->assertTableActionVisible('cancel_post', $order)
        ->callTableAction('cancel_post', $order)
        ->assertTableActionVisible('post_order', $order->fresh())
        ->assertTableActionHidden('cancel_post', $order->fresh());

    expect($order->fresh()->status)->toBe(OrderStatus::CONFIRMED->value);
});

function makeWarehouseOrdersTableUser(): User
{
    $user = new User();
    $user->id = 1;
    $user->organization_id = 1;
    $user->role = UserRole::WAREHOUSE->value;
    $user->name = 'Warehouse User';
    $user->username = 'warehouse_user';
    $user->email = 'warehouse-user@example.com';

    return $user;
}

function prepareOrdersTableTestTables(): void
{
    if (! Schema::hasTable('organizations')) {
        Schema::create('organizations', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    if (! Schema::hasTable('accounting_periods')) {
        Schema::create('accounting_periods', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->unsignedTinyInteger('month')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    if (! Schema::hasTable('warehouses')) {
        Schema::create('warehouses', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    if (! Schema::hasTable('orders')) {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('code')->nullable();
            $table->unsignedTinyInteger('status')->default(OrderStatus::PENDING->value);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('shipping_fee', 15, 2)->default(0);
            $table->decimal('deposit', 15, 2)->default(0);
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->string('ghn_order_code')->nullable();
            $table->string('ghn_status')->nullable();
            $table->timestamp('ghn_posted_at')->nullable();
            $table->timestamp('ghn_cancelled_at')->nullable();
            $table->timestamp('ghn_expected_delivery_time')->nullable();
            $table->decimal('ghn_total_fee', 15, 2)->nullable();
            $table->text('ghn_response')->nullable();
            $table->string('shipping_exception_reason_code')->nullable();
            $table->unsignedInteger('redelivery_attempt')->default(0);
            $table->string('required_note')->nullable();
            $table->decimal('insurance_value', 15, 2)->default(0);
            $table->unsignedInteger('weight')->nullable();
            $table->unsignedInteger('length')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedSmallInteger('ghn_service_type_id')->nullable();
            $table->unsignedSmallInteger('ghn_payment_type_id')->nullable();
            $table->text('shipping_address')->nullable();
            $table->unsignedTinyInteger('invoice_status')->default(0);
            $table->boolean('is_printed')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
