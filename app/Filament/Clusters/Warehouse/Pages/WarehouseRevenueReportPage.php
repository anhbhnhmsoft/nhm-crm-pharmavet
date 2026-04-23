<?php

namespace App\Filament\Clusters\Warehouse\Pages;

use App\Common\Constants\Order\OrderStatus;
use App\Exports\SimpleArrayExport;
use App\Models\Order;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

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
                        DatePicker::make('from_date')
                            ->label(__('warehouse.order.form.from_date'))
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->extraInputAttributes(['required' => false])
                            ->validationMessages([
                                'required' => __('common.error.required'),
                            ]),
                        DatePicker::make('to_date')
                            ->label(__('warehouse.order.form.to_date'))
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->afterOrEqual('from_date')
                            ->extraInputAttributes(['required' => false])
                            ->validationMessages([
                                'required' => __('common.error.required'),
                                'after_or_equal' => __('common.error.date_after', ['date' => __('warehouse.order.form.from_date')]),
                            ]),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function generateReport(): void
    {
        $filters = $this->validateAndBuildFilters();
        $from = $this->normalizeBoundary($filters['from_date'], true);
        $to = $this->normalizeBoundary($filters['to_date'], false);

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

    public function exportExcel()
    {
        $this->generateReport();

        return Excel::download(
            new SimpleArrayExport(
                [
                    __('warehouse.form.name'),
                    __('warehouse.reports.total_orders'),
                    __('warehouse.reports.total_revenue'),
                ],
                collect($this->rows)->map(fn (array $row) => [
                    $row['warehouse_name'] ?? '',
                    (int) ($row['total_orders'] ?? 0),
                    (float) ($row['total_revenue'] ?? 0),
                ])->all()
            ),
            'warehouse-revenue-report-' . now()->format('YmdHis') . '.xlsx'
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_excel')
                ->label(__('warehouse.reports.export_excel'))
                ->icon('heroicon-o-arrow-down-tray')
                ->action('exportExcel'),
        ];
    }

    protected function validateAndBuildFilters(): array
    {
        $validated = validator(
            $this->data,
            [
                'from_date' => ['bail', 'required', 'date'],
                'to_date' => ['bail', 'required', 'date', 'after_or_equal:from_date'],
            ],
            [
                'from_date.required' => __('common.error.required'),
                'to_date.required' => __('common.error.required'),
                'to_date.after_or_equal' => __('common.error.date_after', ['date' => __('warehouse.order.form.from_date')]),
            ],
            [
                'from_date' => __('warehouse.order.form.from_date'),
                'to_date' => __('warehouse.order.form.to_date'),
            ]
        )->validate();

        return [
            'from_date' => (string) $validated['from_date'],
            'to_date' => (string) $validated['to_date'],
        ];
    }

    protected function normalizeBoundary(string $value, bool $isStart): string
    {
        $date = Carbon::parse($value);

        return $isStart
            ? $date->startOfDay()->toDateTimeString()
            : $date->endOfDay()->toDateTimeString();
    }
}
