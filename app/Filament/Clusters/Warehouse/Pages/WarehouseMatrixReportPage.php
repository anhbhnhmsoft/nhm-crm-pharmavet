<?php

namespace App\Filament\Clusters\Warehouse\Pages;

use App\Models\Warehouse;
use App\Models\ProductWarehouse;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class WarehouseMatrixReportPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-table-cells';

    protected string $view = 'filament.clusters.warehouse.pages.warehouse-matrix-report-page';

    protected static ?int $navigationSort = 23;

    public array $rows = [];

    public function mount(): void
    {
        $warehouses = Warehouse::query()
            ->where('organization_id', Auth::user()->organization_id)
            ->orderBy('name')
            ->get(['id', 'name']);

        $stocks = ProductWarehouse::query()
            ->whereIn('warehouse_id', $warehouses->pluck('id'))
            ->selectRaw('warehouse_id, SUM(quantity) as qty, SUM(pending_quantity) as pending')
            ->groupBy('warehouse_id')
            ->get()
            ->keyBy('warehouse_id');

        $this->rows = $warehouses->map(function ($warehouse) use ($stocks) {
            $stock = $stocks->get($warehouse->id);

            return [
                'warehouse' => $warehouse->name,
                'quantity' => (int) ($stock->qty ?? 0),
                'pending' => (int) ($stock->pending ?? 0),
            ];
        })->toArray();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('warehouse.features.reports_v1', false);
    }

    public static function getNavigationGroup(): \UnitEnum|string|null
    {
        return __('filament.navigation.unit_warehouse');
    }

    public static function getNavigationLabel(): string
    {
        return __('warehouse.reports.matrix_navigation');
    }

    public function getTitle(): string
    {
        return __('warehouse.reports.matrix_title');
    }
}
