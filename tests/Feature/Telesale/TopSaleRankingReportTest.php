<?php

use App\Common\Constants\Customer\CustomerType;
use App\Common\Constants\Order\OrderStatus;
use App\Common\Constants\User\UserRole;
use App\Filament\Clusters\Telesale\Pages\TopSaleRankingReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('only includes sale users in the top sale ranking rows', function () {
    $organizationId = DB::table('organizations')->insertGetId([
        'name' => 'Test Org',
        'code' => 'TEST-ORG',
        'product_field' => 1,
        'disable' => false,
        'maximum_employees' => 10,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $admin = User::factory()->create([
        'organization_id' => $organizationId,
        'role' => UserRole::ADMIN->value,
        'position' => 2,
        'name' => 'Admin User',
        'username' => 'admin_user',
        'email' => 'admin@example.com',
    ]);

    $sale = User::factory()->create([
        'organization_id' => $organizationId,
        'role' => UserRole::SALE->value,
        'position' => 3,
        'name' => 'Sale User',
        'username' => 'sale_user',
        'email' => 'sale@example.com',
    ]);

    $marketing = User::factory()->create([
        'organization_id' => $organizationId,
        'role' => UserRole::MARKETING->value,
        'position' => 2,
        'name' => 'Marketing User',
        'username' => 'marketing_user',
        'email' => 'marketing@example.com',
    ]);

    $saleCustomerId = DB::table('customers')->insertGetId([
        'organization_id' => $organizationId,
        'username' => 'Sale Customer',
        'customer_type' => CustomerType::NEW->value,
        'interaction_status' => 1,
        'assigned_staff_id' => $sale->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $marketingCustomerId = DB::table('customers')->insertGetId([
        'organization_id' => $organizationId,
        'username' => 'Marketing Customer',
        'customer_type' => CustomerType::OLD_CUSTOMER->value,
        'interaction_status' => 1,
        'assigned_staff_id' => $marketing->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $adminCustomerId = DB::table('customers')->insertGetId([
        'organization_id' => $organizationId,
        'username' => 'Admin Customer',
        'customer_type' => CustomerType::OLD_CUSTOMER->value,
        'interaction_status' => 1,
        'assigned_staff_id' => $admin->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('orders')->insert([
        [
            'organization_id' => $organizationId,
            'customer_id' => $saleCustomerId,
            'code' => 'ORD-SALE-001',
            'status' => OrderStatus::COMPLETED->value,
            'total_amount' => 1500000,
            'created_by' => $sale->id,
            'updated_by' => $sale->id,
            'created_at' => now()->setTime(10, 0),
            'updated_at' => now()->setTime(10, 0),
        ],
        [
            'organization_id' => $organizationId,
            'customer_id' => $marketingCustomerId,
            'code' => 'ORD-MKT-001',
            'status' => OrderStatus::COMPLETED->value,
            'total_amount' => 900000,
            'created_by' => $marketing->id,
            'updated_by' => $marketing->id,
            'created_at' => now()->setTime(11, 0),
            'updated_at' => now()->setTime(11, 0),
        ],
        [
            'organization_id' => $organizationId,
            'customer_id' => $adminCustomerId,
            'code' => 'ORD-ADMIN-001',
            'status' => OrderStatus::COMPLETED->value,
            'total_amount' => 600000,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
            'created_at' => now()->setTime(12, 0),
            'updated_at' => now()->setTime(12, 0),
        ],
    ]);

    $this->actingAs($admin);

    Livewire::test(TopSaleRankingReport::class)
        ->set('data.from_date', now()->toDateString())
        ->set('data.to_date', now()->toDateString())
        ->call('generateReport')
        ->assertSet('rows', function (array $rows) use ($sale) {
            expect($rows)->toHaveCount(1);
            expect($rows[0]['staff_name'])->toBe($sale->name);
            expect($rows[0]['total_orders'])->toBe(1);
            expect($rows[0]['total_revenue'])->toBe(1500000.0);

            return true;
        });
});
