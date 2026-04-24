<?php

namespace App\Filament\Clusters\Telesale\Pages;

use App\Common\Constants\User\UserRole;
use App\Models\PushsaleRuleSet;
use App\Models\User;
use App\Services\Telesale\TelesaleReportDataService;
use App\Services\Telesale\TelesaleReportExportService;
use App\Services\Telesale\TelesaleReportScopeService;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class TopSaleRankingReport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-trophy';
    protected string $view = 'filament.clusters.telesale.pages.top-sale-ranking-report';
    protected static ?int $navigationSort = 10;
    public static function getNavigationGroup(): \UnitEnum|string|null
    {
        return __('filament.navigation.unit_telesale');
    }

    public ?array $data = [];

    public array $rows = [];

    public function mount(): void
    {
        $this->form->fill([
            'from_date' => now()->startOfMonth()->toDateString(),
            'to_date' => now()->toDateString(),
            'staff_id' => null,
            'pushsale_rule_set_id' => null,
        ]);

        $this->generateReport();
    }

    public static function getNavigationLabel(): string
    {
        if (config('telesale.reports.honor_board_v1', false) || config('marketing.features.ranking_v2', false)) {
            return __('telesale.reports.top_sale_navigation') . ' (' . __('telesale.reports.legacy_suffix') . ')';
        }

        return __('telesale.reports.top_sale_navigation');
    }

    public function getTitle(): string
    {
        return __('telesale.reports.top_sale_title');
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
                        DatePicker::make('from_date')
                            ->label(__('telesale.filters.from_date'))
                            ->required()
                            ->extraInputAttributes(['required' => false])
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->validationMessages([
                                'required' => __('validation.required', [
                                    'attribute' => __('telesale.filters.from_date'),
                                ]),
                            ]),
                        DatePicker::make('to_date')
                            ->label(__('telesale.filters.to_date'))
                            ->required()
                            ->extraInputAttributes(['required' => false])
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->afterOrEqual('from_date')
                            ->validationMessages([
                                'required' => __('validation.required', [
                                    'attribute' => __('telesale.filters.to_date'),
                                ]),
                                'after_or_equal' => __('validation.after_or_equal', [
                                    'attribute' => __('telesale.filters.to_date'),
                                    'date' => __('telesale.filters.from_date'),
                                ]),
                            ]),
                        Select::make('staff_id')
                            ->label(__('telesale.reports.staff'))
                            ->options(fn() => $this->getStaffOptions(app(TelesaleReportScopeService::class)))
                            ->placeholder(__('telesale.reports.all_staff'))
                            ->searchable(),
                        Select::make('pushsale_rule_set_id')
                            ->label(__('telesale.reports.pushsale_rule_set'))
                            ->options(
                                PushsaleRuleSet::query()
                                    ->where('organization_id', Auth::user()->organization_id)
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                            )
                            ->searchable(),
                    ])
                    ->columns(4),
            ])
            ->statePath('data');
    }

    protected function getStaffOptions(TelesaleReportScopeService $scopeService): array
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $query = User::query()
            ->where('role', UserRole::SALE->value)
            ->orderBy('name');

        if ($user->role !== UserRole::SUPER_ADMIN->value) {
            $query->where('organization_id', $user->organization_id);
        }

        $staffIds = $scopeService->resolveScopedStaffIds($user);
        if (is_array($staffIds)) {
            $query->whereIn('id', $staffIds);
        }

        return $query->pluck('name', 'id')->all();
    }

    public function generateReport(): void
    {
        /** @var TelesaleReportDataService $reportDataService */
        $reportDataService = app(TelesaleReportDataService::class);

        $filters = $this->validateFilters();

        $this->rows = $reportDataService->buildTopSaleRankingRows(
            user: Auth::user(),
            filters: $filters,
        );
    }

    public function exportReport(TelesaleReportExportService $exportService): void
    {
        /** @var TelesaleReportDataService $reportDataService */
        $reportDataService = app(TelesaleReportDataService::class);
        $filters = $this->validateFilters();
        $rows = $reportDataService->buildTopSaleRankingRows(Auth::user(), $filters);

        $job = $exportService->enqueueExport(
            user: Auth::user(),
            reportType: 'top_sale_ranking',
            filters: $filters,
            rowCount: count($rows),
        );

        Notification::make()
            ->title(__('telesale.reports.export_queued', ['id' => $job->id]))
            ->body(__('telesale.reports.export_history_hint'))
            ->success()
            ->send();
    }

    protected function validateFilters(): array
    {
        $validated = $this->validate(
            [
                'data.from_date' => ['bail', 'required', 'date'],
                'data.to_date' => ['bail', 'required', 'date', 'after_or_equal:data.from_date'],
                'data.staff_id' => ['nullable'],
                'data.pushsale_rule_set_id' => ['nullable'],
            ],
            [
                'data.from_date.required' => __('validation.required', [
                    'attribute' => __('telesale.filters.from_date'),
                ]),
                'data.to_date.required' => __('validation.required', [
                    'attribute' => __('telesale.filters.to_date'),
                ]),
                'data.to_date.after_or_equal' => __('validation.after_or_equal', [
                    'attribute' => __('telesale.filters.to_date'),
                    'date' => __('telesale.filters.from_date'),
                ]),
            ],
            [
                'data.from_date' => __('telesale.filters.from_date'),
                'data.to_date' => __('telesale.filters.to_date'),
                'data.staff_id' => __('telesale.reports.staff'),
                'data.pushsale_rule_set_id' => __('telesale.reports.pushsale_rule_set'),
            ],
        );

        return [
            'from_date' => (string) $validated['data']['from_date'],
            'to_date' => (string) $validated['data']['to_date'],
            'staff_id' => $validated['data']['staff_id'] ?? null,
            'pushsale_rule_set_id' => $validated['data']['pushsale_rule_set_id'] ?? null,
        ];
    }
}
