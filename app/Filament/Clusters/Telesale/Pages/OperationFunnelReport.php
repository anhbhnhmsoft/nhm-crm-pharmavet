<?php

namespace App\Filament\Clusters\Telesale\Pages;

use App\Common\Constants\Interaction\InteractionStatus;
use App\Common\Constants\Order\OrderStatus;
use App\Common\Constants\User\UserRole;
use App\Models\CustomerStatusLog;
use App\Models\Order;
use App\Models\User;
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

    public function mount(TelesaleReportScopeService $scopeService): void
    {
        $this->form->fill([
            'from_date' => now()->startOfMonth()->toDateString(),
            'to_date' => now()->toDateString(),
            'staff_id' => null,
            'selected_steps' => [],
            'unlimited_close_date' => false,
        ]);

        $this->generateReport($scopeService);
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
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                        DatePicker::make('to_date')
                            ->label(__('telesale.filters.to_date'))
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->afterOrEqual('from_date'),
                        Select::make('staff_id')
                            ->label(__('telesale.reports.staff'))
                            ->options(
                                User::query()
                                    ->where('role', UserRole::SALE->value)
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                            )
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

    public function generateReport(TelesaleReportScopeService $scopeService): void
    {
        $state = $this->form->getState();
        $from = ($state['from_date'] ?? now()->startOfMonth()->toDateString()) . ' 00:00:00';
        $to = ($state['to_date'] ?? now()->toDateString()) . ' 23:59:59';
        $staffId = $state['staff_id'] ?? null;
        $selectedSteps = $state['selected_steps'] ?? [];
        $unlimitedCloseDate = (bool) ($state['unlimited_close_date'] ?? false);
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $base = CustomerStatusLog::query()
            ->join('customers', 'customers.id', '=', 'customer_status_logs.customer_id');

        if (!$unlimitedCloseDate) {
            $base->whereBetween('customer_status_logs.created_at', [$from, $to]);
        }

        if ($user->role !== UserRole::SUPER_ADMIN->value) {
            $base->where('customers.organization_id', $user->organization_id);
        }

        if (!empty($staffId)) {
            $base->where('customer_status_logs.user_id', $staffId);
        }

        if ($user->role === UserRole::SALE->value) {
            $base->where('customer_status_logs.user_id', $user->id);
        }

        $steps = [
            InteractionStatus::FIRST_CALL->value,
            InteractionStatus::SECOND_CALL->value,
            InteractionStatus::THIRD_CALL->value,
            InteractionStatus::FOURTH_CALL->value,
            InteractionStatus::FIFTH_CALL->value,
            InteractionStatus::SIXTH_CALL->value,
            InteractionStatus::USER_MANUAL->value,
            InteractionStatus::SECOND_CARE->value,
            InteractionStatus::THIRD_CARE->value,
            InteractionStatus::PASS->value,
        ];
        if (!empty($selectedSteps)) {
            $steps = array_values(array_intersect($steps, array_map('intval', $selectedSteps)));
        }

        $rows = [];

        foreach ($steps as $step) {
            $stepQuery = (clone $base)->where('customer_status_logs.to_status', $step);
            $customerIds = $stepQuery->distinct()->pluck('customer_status_logs.customer_id');
            $contacts = $customerIds->count();

            $ordersQuery = Order::query()
                ->whereIn('customer_id', $customerIds)
                ->whereIn('status', [
                    OrderStatus::CONFIRMED->value,
                    OrderStatus::SHIPPING->value,
                    OrderStatus::COMPLETED->value,
                ]);

            if (!$unlimitedCloseDate) {
                $ordersQuery->whereBetween('created_at', [$from, $to]);
            }

            $scopeService->applyOrderScope($ordersQuery, $user);

            if (!empty($staffId)) {
                $ordersQuery->where('created_by', $staffId);
            }

            if ($user->role === UserRole::SALE->value) {
                $ordersQuery->where('created_by', $user->id);
            }

            $orderCount = (clone $ordersQuery)->count();
            $revenue = (float) ((clone $ordersQuery)->sum('total_amount') ?? 0);
            $rate = $contacts > 0 ? round(($orderCount / $contacts) * 100, 2) : 0;

            $rows[] = [
                'step' => InteractionStatus::getLabelStatus($step),
                'contacts' => $contacts,
                'orders' => $orderCount,
                'conversion_rate' => $rate,
                'revenue' => $revenue,
            ];
        }

        $this->rows = $rows;
    }

    public function exportReport(TelesaleReportExportService $exportService): void
    {
        $job = $exportService->enqueueExport(
            user: Auth::user(),
            reportType: 'operation_funnel',
            filters: $this->form->getState(),
            rowCount: count($this->rows),
        );

        Notification::make()
            ->title(__('telesale.reports.export_queued', ['id' => $job->id]))
            ->success()
            ->send();
    }
}
