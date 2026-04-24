<?php

use App\Filament\Clusters\Warehouse\Pages\StockCoverageReportPage;
use App\Filament\Clusters\Warehouse\Pages\WarehouseRevenueReportPage;
use App\Filament\Clusters\Warehouse\Pages\WarehouseStockReportPage;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

it('shows validation error when stock report to date is before from date', function () {
    config()->set('warehouse.features.reports_v1', true);
    prepareWarehouseReportTables();
    $this->actingAs(makeWarehouseReportUser());

    Livewire::test(WarehouseStockReportPage::class)
        ->set('data.from_date', '2026-04-20')
        ->set('data.to_date', '2026-04-10')
        ->call('generateReport')
        ->assertHasErrors(['data.to_date']);
});

it('shows validation error when stock coverage report to date is before from date', function () {
    config()->set('warehouse.features.reports_v1', true);
    prepareWarehouseReportTables();
    $this->actingAs(makeWarehouseReportUser());

    Livewire::test(StockCoverageReportPage::class)
        ->set('data.from_date', '2026-04-20')
        ->set('data.to_date', '2026-04-10')
        ->set('data.window_days', 30)
        ->call('generateReport')
        ->assertHasErrors(['data.to_date']);
});

it('shows validation error when warehouse revenue report to date is before from date', function () {
    config()->set('warehouse.features.reports_v1', true);
    prepareWarehouseReportTables();
    $this->actingAs(makeWarehouseReportUser());

    Livewire::test(WarehouseRevenueReportPage::class)
        ->set('data.from_date', '2026-04-20')
        ->set('data.to_date', '2026-04-10')
        ->call('generateReport')
        ->assertHasErrors(['data.to_date']);
});

function makeWarehouseReportUser(): User
{
    $user = new User();
    $user->id = 1;
    $user->organization_id = 1;
    $user->role = 2;
    $user->name = 'Warehouse Admin';
    $user->username = 'warehouse_admin';
    $user->email = 'warehouse-admin@example.com';

    return $user;
}

function prepareWarehouseReportTables(): void
{
    if (! Schema::hasTable('inventory_movements')) {
        Schema::create('inventory_movements', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->string('movement_type')->nullable();
            $table->integer('quantity_change')->default(0);
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();
        });
    }

    if (! Schema::hasTable('warehouses')) {
        Schema::create('warehouses', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });
    }

    if (! Schema::hasTable('orders')) {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->unsignedTinyInteger('status')->default(1);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
    }
}
