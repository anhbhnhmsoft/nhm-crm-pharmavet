<?php

namespace App\Filament\Clusters\Warehouse\Pages;

use App\Exports\SimpleArrayExport;
use App\Services\Warehouse\WarehouseReportService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class WarehouseStockReportPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected string $view = 'filament.clusters.warehouse.pages.warehouse-stock-report-page';

    protected static ?int $navigationSort = 20;

    public ?array $data = [];

    public array $stats = [];

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
        return __('warehouse.reports.stock_navigation');
    }

    public function getTitle(): string
    {
        return __('warehouse.reports.stock_title');
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

        /** @var WarehouseReportService $service */
        $service = app(WarehouseReportService::class);
        $this->stats = $service->buildStockSummary(
            organizationId: (int) Auth::user()->organization_id,
            fromDate: $filters['from_date'],
            toDate: $filters['to_date'],
        );
    }

    public function exportExcel()
    {
        $filters = $this->validateAndBuildFilters();

        /** @var WarehouseReportService $service */
        $service = app(WarehouseReportService::class);
        $this->stats = $service->buildStockSummary(
            organizationId: (int) Auth::user()->organization_id,
            fromDate: $filters['from_date'],
            toDate: $filters['to_date'],
        );

        return Excel::download(
            new SimpleArrayExport(
                [
                    __('warehouse.order.form.from_date'),
                    __('warehouse.order.form.to_date'),
                    __('warehouse.reports.opening'),
                    __('warehouse.reports.imports'),
                    __('warehouse.reports.exports'),
                    __('warehouse.reports.closing'),
                ],
                [[
                    (string) ($this->data['from_date'] ?? ''),
                    (string) ($this->data['to_date'] ?? ''),
                    (int) ($this->stats['opening'] ?? 0),
                    (int) ($this->stats['imports'] ?? 0),
                    (int) ($this->stats['exports'] ?? 0),
                    (int) ($this->stats['closing'] ?? 0),
                ]]
            ),
            'warehouse-stock-report-' . now()->format('YmdHis') . '.xlsx'
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
        $validated = $this->validate(
            [
                'data.from_date' => ['bail', 'required', 'date'],
                'data.to_date' => ['bail', 'required', 'date', 'after_or_equal:data.from_date'],
            ],
            [
                'data.from_date.required' => __('common.error.required'),
                'data.to_date.required' => __('common.error.required'),
                'data.to_date.after_or_equal' => __('validation.after_or_equal', [
                    'attribute' => __('warehouse.order.form.to_date'),
                    'date' => __('warehouse.order.form.from_date'),
                ]),
            ],
            [
                'data.from_date' => __('warehouse.order.form.from_date'),
                'data.to_date' => __('warehouse.order.form.to_date'),
            ]
        );

        return [
            'from_date' => (string) $validated['data']['from_date'],
            'to_date' => (string) $validated['data']['to_date'],
        ];
    }
}
