<?php

namespace App\Filament\Clusters\Telesale\Pages;

use App\Common\Constants\Interaction\InteractionStatus;
use App\Common\Constants\User\UserRole;
use App\Models\User;
use App\Services\Telesale\TelesaleReportDataService;
use App\Services\Telesale\TelesaleReportExportService;
use App\Services\Telesale\TelesaleReportScopeService;
use BackedEnum;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class OperationFunnelReport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';
    protected string $view = 'filament.clusters.telesale.pages.operation-funnel-report';
    protected static ?int $navigationSort = 11;
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
            'selected_steps' => [],
            'unlimited_close_date' => false,
        ]);

        $this->generateReport();
    }

    public static function getNavigationLabel(): string
    {
        return __('telesale.reports.funnel_navigation');
    }

    public function getTitle(): string
    {
        return __('telesale.reports.funnel_title');
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
                            ->options(fn (): array => $this->getStaffOptions())
                            ->placeholder(__('telesale.reports.all_staff'))
                            ->searchable(),
                        Select::make('selected_steps')
                            ->label(__('telesale.reports.step'))
                            ->options(InteractionStatus::options())
                            ->multiple()
                            ->searchable(),
                        Checkbox::make('unlimited_close_date')
                            ->label(__('telesale.reports.unlimited_close_date')),
                    ])
                    ->columns(5),
            ])
            ->statePath('data');
    }

    public function generateReport(): void
    {
        /** @var TelesaleReportDataService $reportDataService */
        $reportDataService = app(TelesaleReportDataService::class);
        $filters = $this->validateFilters();

        $this->rows = $reportDataService->buildOperationFunnelRows(
            user: Auth::user(),
            filters: $filters,
        );
    }

    public function exportReport(TelesaleReportExportService $exportService): void
    {
        /** @var TelesaleReportDataService $reportDataService */
        $reportDataService = app(TelesaleReportDataService::class);
        $filters = $this->validateFilters();
        $rows = $reportDataService->buildOperationFunnelRows(Auth::user(), $filters);

        $job = $exportService->enqueueExport(
            user: Auth::user(),
            reportType: 'operation_funnel',
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
                'data.selected_steps' => ['nullable', 'array'],
                'data.unlimited_close_date' => ['nullable', 'boolean'],
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
                'data.selected_steps' => __('telesale.reports.step'),
                'data.unlimited_close_date' => __('telesale.reports.unlimited_close_date'),
            ],
        );

        return [
            'from_date' => (string) $validated['data']['from_date'],
            'to_date' => (string) $validated['data']['to_date'],
            'staff_id' => $validated['data']['staff_id'] ?? null,
            'selected_steps' => $validated['data']['selected_steps'] ?? [],
            'unlimited_close_date' => (bool) ($validated['data']['unlimited_close_date'] ?? false),
        ];
    }

    protected function getStaffOptions(): array
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $scopeService = app(TelesaleReportScopeService::class);
        $scopedStaffIds = $scopeService->resolveScopedStaffIds($user);

        $query = User::query()
            ->where('role', UserRole::SALE->value)
            ->orderBy('name');

        if ($user->role !== UserRole::SUPER_ADMIN->value) {
            $query->where('organization_id', $user->organization_id);
        }

        if (is_array($scopedStaffIds)) {
            $query->whereIn('id', $scopedStaffIds);
        }

        return $query->pluck('name', 'id')->all();
    }

    protected function applyAssignedStaffScope(Builder $query, array $staffIds): void
    {
        $staffIds = array_values(array_filter(array_map('intval', $staffIds)));

        if ($staffIds === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function (Builder $scopeQuery) use ($staffIds): void {
            $scopeQuery
                ->whereIn('customers.assigned_staff_id', $staffIds)
                ->orWhereExists(function ($subQuery) use ($staffIds): void {
                    $subQuery
                        ->selectRaw('1')
                        ->from('user_assigned_staff')
                        ->whereColumn('user_assigned_staff.customer_id', 'customers.id')
                        ->whereIn('user_assigned_staff.staff_id', $staffIds);
                });
        });
    }
}
