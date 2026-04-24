<?php

namespace App\Services\Telesale;

use App\Common\Constants\Customer\CustomerType;
use App\Common\Constants\Interaction\InteractionStatus;
use App\Common\Constants\Order\OrderStatus;
use App\Common\Constants\User\UserRole;
use App\Models\CustomerStatusLog;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class TelesaleReportDataService
{
    public function __construct(
        private TelesaleReportScopeService $scopeService,
        private PushsaleRuleService $pushsaleRuleService,
    ) {
    }

    public function buildRows(string $reportType, User $user, array $filters): array
    {
        return match ($reportType) {
            'operation_funnel' => $this->buildOperationFunnelRows($user, $filters),
            'top_sale_ranking' => $this->buildTopSaleRankingRows($user, $filters),
            default => throw new InvalidArgumentException("Unsupported telesale report type [{$reportType}]"),
        };
    }

    public function buildExportDataset(string $reportType, User $user, array $filters): array
    {
        $rows = $this->buildRows($reportType, $user, $filters);

        return match ($reportType) {
            'operation_funnel' => [
                'headers' => [
                    __('telesale.reports.step'),
                    __('telesale.reports.contacts'),
                    __('telesale.reports.orders'),
                    __('telesale.reports.conversion_rate'),
                    __('telesale.reports.revenue'),
                ],
                'rows' => array_map(fn(array $row): array => [
                    $row['step'],
                    $row['contacts'],
                    $row['orders'],
                    $row['conversion_rate'],
                    $row['revenue'],
                ], $rows),
            ],
            'top_sale_ranking' => [
                'headers' => [
                    '#',
                    __('telesale.reports.staff'),
                    __('telesale.reports.new_customer'),
                    __('telesale.reports.old_customer'),
                    __('telesale.reports.total_orders'),
                    __('telesale.reports.total_revenue'),
                    __('telesale.reports.adjusted_revenue'),
                ],
                'rows' => array_map(fn(array $row): array => [
                    $row['rank'],
                    $row['staff_name'],
                    $row['new_customers'],
                    $row['old_customers'],
                    $row['total_orders'],
                    $row['total_revenue'],
                    $row['adjusted_revenue'],
                ], $rows),
            ],
            default => throw new InvalidArgumentException("Unsupported telesale report type [{$reportType}]"),
        };
    }

    public function buildOperationFunnelRows(User $user, array $filters): array
    {
        $filters = $this->normalizeDateRangeFilters($filters);
        $from = $filters['from_at'];
        $to = $filters['to_at'];
        $staffId = !empty($filters['staff_id']) ? (int) $filters['staff_id'] : null;
        $selectedSteps = array_map('intval', (array) ($filters['selected_steps'] ?? []));
        $unlimitedCloseDate = (bool) ($filters['unlimited_close_date'] ?? false);
        $scopedStaffIds = $this->scopeService->resolveScopedStaffIds($user);

        $base = CustomerStatusLog::query()
            ->join('customers', 'customers.id', '=', 'customer_status_logs.customer_id');

        if (! $unlimitedCloseDate) {
            $base->whereBetween('customer_status_logs.created_at', [$from, $to]);
        }

        if ($user->role !== UserRole::SUPER_ADMIN->value) {
            $base->where('customers.organization_id', $user->organization_id);
        }

        if (is_array($scopedStaffIds)) {
            $this->applyAssignedStaffScope($base, $scopedStaffIds);
        }

        if ($staffId) {
            $this->applyAssignedStaffScope($base, [$staffId]);
        }

        if ($user->role === UserRole::SALE->value) {
            $this->applyAssignedStaffScope($base, [$user->id]);
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

        if ($selectedSteps !== []) {
            $steps = array_values(array_intersect($steps, $selectedSteps));
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

            if (! $unlimitedCloseDate) {
                $ordersQuery->whereBetween('created_at', [$from, $to]);
            }

            if ($user->role !== UserRole::SUPER_ADMIN->value) {
                $ordersQuery->where('organization_id', $user->organization_id);
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

        return $rows;
    }

    public function buildTopSaleRankingRows(User $user, array $filters): array
    {
        $filters = $this->normalizeDateRangeFilters($filters);
        $from = $filters['from_at'];
        $to = $filters['to_at'];
        $staffId = !empty($filters['staff_id']) ? (int) $filters['staff_id'] : null;
        $ruleSetId = !empty($filters['pushsale_rule_set_id']) ? (int) $filters['pushsale_rule_set_id'] : null;

        $query = Order::query()
            ->join('users', 'users.id', '=', 'orders.created_by')
            ->join('customers', 'customers.id', '=', 'orders.customer_id')
            ->whereBetween('orders.created_at', [$from, $to])
            ->where('users.role', UserRole::SALE->value)
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

        if ($user->role !== UserRole::SUPER_ADMIN->value) {
            $query->where('users.organization_id', $user->organization_id);
        }

        $this->scopeService->applyOrderScope($query, $user);

        if ($staffId) {
            $query->where('orders.created_by', $staffId);
        }

        if ($user->role === UserRole::SALE->value) {
            $query->where('orders.created_by', $user->id);
        }

        return $query->get()
            ->values()
            ->map(function ($row, $index) use ($ruleSetId) {
                $rule = $this->pushsaleRuleService->applyRuleSet((float) $row->total_revenue, $ruleSetId);

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

    protected function normalizeDateRangeFilters(array $filters): array
    {
        $fromDate = (string) ($filters['from_date'] ?? now()->startOfMonth()->toDateString());
        $toDate = (string) ($filters['to_date'] ?? now()->toDateString());

        return [
            ...$filters,
            'from_date' => Carbon::parse($fromDate)->toDateString(),
            'to_date' => Carbon::parse($toDate)->toDateString(),
            'from_at' => $this->normalizeBoundary($fromDate, true),
            'to_at' => $this->normalizeBoundary($toDate, false),
        ];
    }

    protected function normalizeBoundary(string $value, bool $isStart): string
    {
        $date = Carbon::parse($value);

        return $isStart
            ? $date->startOfDay()->toDateTimeString()
            : $date->endOfDay()->toDateTimeString();
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
