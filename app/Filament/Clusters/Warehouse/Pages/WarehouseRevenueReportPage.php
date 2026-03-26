<?php

namespace App\Filament\Clusters\Warehouse\Pages;

use App\Common\Constants\Order\OrderStatus;
use App\Models\Order;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class WarehouseRevenueReportPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected string $view = 'filament.clusters.warehouse.pages.warehouse-revenue-report-page';

    protected static ?int $navigationSort = 22;

    public ?array $data = [];

    public array $rows = [];

    public function mount(): void
    {
        $this->form->fill([
            'from_date' => now()->startOfMonth()->toDateString(),
            'to_date' => now()->toDateString(),
        ]);

        $this->generateReport();
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
        return __('warehouse.reports.revenue_navigation');
    }

    public function getTitle(): string
    {
        return __('warehouse.reports.revenue_title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('warehouse.reports.filters'))
                    ->schema([
                        DatePicker::make('from_date')->label(__('warehouse.order.form.from_date'))->required(),
                        DatePicker::make('to_date')->label(__('warehouse.order.form.to_date'))->required()->afterOrEqual('from_date'),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function generateReport(): void
    {
        $state = $this->form->getState();
        $from = ($state['from_date'] ?? now()->startOfMonth()->toDateString()) . ' 00:00:00';
        $to = ($state['to_date'] ?? now()->toDateString()) . ' 23:59:59';

        $query = Order::query()
            ->join('warehouses', 'warehouses.id', '=', 'orders.warehouse_id')
            ->where('orders.organization_id', Auth::user()->organization_id)
            ->whereBetween('orders.created_at', [$from, $to])
            ->where('orders.status', OrderStatus::COMPLETED->value)
            ->groupBy('warehouses.id', 'warehouses.name')
            ->selectRaw('warehouses.name as warehouse_name')
            ->selectRaw('COUNT(orders.id) as total_orders')
            ->selectRaw('SUM(orders.total_amount) as total_revenue')
            ->orderByDesc('total_revenue');

        $this->rows = $query->get()->map(fn($row) => [
            'warehouse_name' => $row->warehouse_name,
            'total_orders' => (int) $row->total_orders,
            'total_revenue' => (float) $row->total_revenue,
        ])->toArray();
    }
}
