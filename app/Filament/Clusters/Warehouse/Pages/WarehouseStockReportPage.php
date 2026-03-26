<?php

namespace App\Filament\Clusters\Warehouse\Pages;

use App\Services\Warehouse\WarehouseReportService;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

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

        /** @var WarehouseReportService $service */
        $service = app(WarehouseReportService::class);
        $this->stats = $service->buildStockSummary(
            organizationId: (int) Auth::user()->organization_id,
            fromDate: (string) ($state['from_date'] ?? now()->startOfMonth()->toDateString()),
            toDate: (string) ($state['to_date'] ?? now()->toDateString()),
        );
    }
}
