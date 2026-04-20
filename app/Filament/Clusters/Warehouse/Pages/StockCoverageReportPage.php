<?php

namespace App\Filament\Clusters\Warehouse\Pages;

use App\Exports\SimpleArrayExport;
use App\Services\Warehouse\WarehouseReportService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class StockCoverageReportPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected string $view = 'filament.clusters.warehouse.pages.stock-coverage-report-page';

    protected static ?int $navigationSort = 21;

    public ?array $data = [];

    public array $stats = [];

    public function mount(): void
    {
        $this->form->fill([
            'from_date' => now()->startOfMonth()->toDateString(),
            'to_date' => now()->toDateString(),
            'window_days' => 30,
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
        return __('warehouse.reports.coverage_navigation');
    }

    public function getTitle(): string
    {
        return __('warehouse.reports.coverage_title');
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
                        Select::make('window_days')
                            ->label(__('warehouse.reports.window_days'))
                            ->options([7 => '7', 30 => '30'])
                            ->native(false)
                            ->required()
                            ->extraInputAttributes(['required' => false])
                            ->validationMessages([
                                'required' => __('common.error.required'),
                            ]),
                    ])
                    ->columns(3),
            ])
            ->statePath('data');
    }

    public function generateReport(): void
    {
        $filters = $this->validateAndBuildFilters();

        /** @var WarehouseReportService $service */
        $service = app(WarehouseReportService::class);
        $this->stats = $service->buildCoverageSummary(
            organizationId: (int) Auth::user()->organization_id,
            fromDate: $filters['from_date'],
            toDate: $filters['to_date'],
            days: $filters['window_days'],
        );
    }

    public function exportExcel()
    {
        $filters = $this->validateAndBuildFilters();

        /** @var WarehouseReportService $service */
        $service = app(WarehouseReportService::class);
        $this->stats = $service->buildCoverageSummary(
            organizationId: (int) Auth::user()->organization_id,
            fromDate: $filters['from_date'],
            toDate: $filters['to_date'],
            days: $filters['window_days'],
        );

        return Excel::download(
            new SimpleArrayExport(
                [
                    __('warehouse.order.form.from_date'),
                    __('warehouse.order.form.to_date'),
                    __('warehouse.reports.window_days'),
                    __('warehouse.reports.available_stock'),
                    __('warehouse.reports.avg_daily_out'),
                    __('warehouse.reports.days_of_stock'),
                ],
                [[
                    (string) ($this->data['from_date'] ?? ''),
                    (string) ($this->data['to_date'] ?? ''),
                    (int) ($this->data['window_days'] ?? 30),
                    (float) ($this->stats['available_stock'] ?? 0),
                    (float) ($this->stats['avg_daily_out'] ?? 0),
                    (float) ($this->stats['days_of_stock'] ?? 0),
                ]]
            ),
            'warehouse-coverage-report-' . now()->format('YmdHis') . '.xlsx'
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
                'window_days' => ['bail', 'required', 'in:7,30'],
            ],
            [
                'from_date.required' => __('common.error.required'),
                'to_date.required' => __('common.error.required'),
                'to_date.after_or_equal' => __('common.error.date_after', ['date' => __('warehouse.order.form.from_date')]),
                'window_days.required' => __('common.error.required'),
            ],
            [
                'from_date' => __('warehouse.order.form.from_date'),
                'to_date' => __('warehouse.order.form.to_date'),
                'window_days' => __('warehouse.reports.window_days'),
            ]
        )->validate();

        return [
            'from_date' => (string) $validated['from_date'],
            'to_date' => (string) $validated['to_date'],
            'window_days' => (int) $validated['window_days'],
        ];
    }
}
