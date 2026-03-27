<?php

namespace App\Filament\Clusters\Telesale\Pages;

use App\Common\Constants\Customer\CustomerType;
use App\Common\Constants\Order\OrderStatus;
use App\Common\Constants\User\UserRole;
use App\Models\Order;
use App\Models\PushsaleRuleSet;
use App\Models\User;
use App\Services\Telesale\PushsaleRuleService;
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

    public function mount(TelesaleReportScopeService $scopeService, PushsaleRuleService $pushsaleRuleService): void
    {
        $this->form->fill([
            'from_date' => now()->startOfMonth()->toDateString(),
            'to_date' => now()->toDateString(),
            'staff_id' => null,
            'pushsale_rule_set_id' => null,
        ]);

        $this->generateReport($scopeService, $pushsaleRuleService);
    }

    public static function getNavigationLabel(): string
    {
        if (config('telesale.reports.honor_board_v1', false)) {
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

    public function generateReport(TelesaleReportScopeService $scopeService, PushsaleRuleService $pushsaleRuleService): void
    {
        $state = $this->form->getState();
        $from = ($state['from_date'] ?? now()->startOfMonth()->toDateString()) . ' 00:00:00';
        $to = ($state['to_date'] ?? now()->toDateString()) . ' 23:59:59';
        $staffId = $state['staff_id'] ?? null;
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $query = Order::query()
            ->join('users', 'users.id', '=', 'orders.created_by')
            ->join('customers', 'customers.id', '=', 'orders.customer_id')
            ->whereBetween('orders.created_at', [$from, $to])
            ->whereIn('orders.status', [
                OrderStatus::CONFIRMED->value,
                OrderStatus::SHIPPING->value,
                OrderStatus::COMPLETED->value,
            ])
            ->selectRaw('orders.created_by as staff_id')
            ->selectRaw('users.name as staff_name')
            ->selectRaw('COUNT(orders.id) as total_orders')
            ->selectRaw('SUM(orders.total_amount) as total_revenue')
            ->selectRaw(
                'SUM(CASE WHEN customers.customer_type = ? THEN 1 ELSE 0 END) as new_customers',
                [CustomerType::NEW->value]
            )
            ->selectRaw(
                'SUM(CASE WHEN customers.customer_type = ? THEN 1 ELSE 0 END) as old_customers',
                [CustomerType::OLD_CUSTOMER->value]
            )
            ->groupBy('orders.created_by', 'users.name')
            ->orderByDesc('total_revenue');

        $scopeService->applyOrderScope($query, $user);

        if (!empty($staffId)) {
            $query->where('orders.created_by', $staffId);
        }

        if ($user->role === UserRole::SALE->value) {
            $query->where('orders.created_by', $user->id);
        }

        $ruleSetId = (int) ($state['pushsale_rule_set_id'] ?? 0);

        $this->rows = $query->get()
            ->values()
            ->map(function ($row, $index) use ($pushsaleRuleService, $ruleSetId) {
                $rule = $pushsaleRuleService->applyRuleSet((float) $row->total_revenue, $ruleSetId ?: null);

                return [
                    'rank' => $index + 1,
                    'staff_name' => $row->staff_name,
                    'new_customers' => (int) $row->new_customers,
                    'old_customers' => (int) $row->old_customers,
                    'total_orders' => (int) $row->total_orders,
                    'total_revenue' => (float) $row->total_revenue,
                    'adjusted_revenue' => (float) $rule['adjusted_revenue'],
                    'kpi_multiplier' => (float) $rule['kpi_multiplier'],
                ];
            })
            ->toArray();
    }

    public function exportReport(TelesaleReportExportService $exportService): void
    {
        $job = $exportService->enqueueExport(
            user: Auth::user(),
            reportType: 'top_sale_ranking',
            filters: $this->form->getState(),
            rowCount: count($this->rows),
        );

        Notification::make()
            ->title(__('telesale.reports.export_queued', ['id' => $job->id]))
            ->success()
            ->send();
    }
}
