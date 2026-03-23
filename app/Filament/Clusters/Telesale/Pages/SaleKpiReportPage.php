<?php

namespace App\Filament\Clusters\Telesale\Pages;

use App\Common\Constants\User\UserRole;
use App\Filament\Clusters\Telesale\TelesaleCluster;
use App\Models\User;
use App\Services\Telesale\TelesaleKpiService;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class SaleKpiReportPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-line';
    protected string $view = 'filament.clusters.telesale.pages.sale-kpi-report-page';
    protected static ?int $navigationSort = 12;
    protected static string|null $cluster = TelesaleCluster::class;

    public ?array $data = [];
    public array $summary = [];

    public function mount(): void
    {
        $this->form->fill([
            'month' => now()->startOfMonth()->toDateString(),
            'staff_id' => null,
        ]);

        $this->generateReport();
    }

    public static function getNavigationLabel(): string
    {
        return __('telesale.reports.sale_kpi_navigation');
    }

    public function getTitle(): string
    {
        return __('telesale.reports.sale_kpi_title');
    }

    public static function canAccess(): bool
    {
        return in_array(Auth::user()->role, [
            UserRole::SUPER_ADMIN->value,
            UserRole::ADMIN->value,
            UserRole::SALE->value,
        ], true);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('marketing.report.filter_section'))
                    ->schema([
                        DatePicker::make('month')
                            ->label(__('telesale.reports.month'))
                            ->native(false)
                            ->required(),
                        Select::make('staff_id')
                            ->label(__('telesale.reports.staff'))
                            ->options(User::query()->where('role', UserRole::SALE->value)->pluck('name', 'id'))
                            ->searchable(),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function generateReport(): void
    {
        /** @var TelesaleKpiService $kpiService */
        $kpiService = app(TelesaleKpiService::class);
        $state = $this->form->getState();
        $user = Auth::user();

        $staffId = $state['staff_id'] ?? null;
        if ($user->role === UserRole::SALE->value) {
            $staffId = $user->id;
        }

        $month = Carbon::parse($state['month'] ?? now()->toDateString());

        $this->summary = $kpiService->buildMonthlyKpiSummary(
            organizationId: (int) $user->organization_id,
            userId: $staffId ? (int) $staffId : null,
            month: $month,
        );
    }
}
