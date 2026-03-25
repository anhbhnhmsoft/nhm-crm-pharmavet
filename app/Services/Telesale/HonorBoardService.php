<?php

namespace App\Services\Telesale;

use App\Common\Constants\Order\OrderStatus;
use App\Common\Constants\Team\TeamType;
use App\Common\Constants\User\UserRole;
use App\Models\User;
use App\Repositories\CustomerRepository;
use App\Repositories\OrderRepository;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class HonorBoardService
{
    public function __construct(
        private OrderRepository $orderRepository,
        private CustomerRepository $customerRepository,
        private PushsaleRuleService $pushsaleRuleService,
        private TelesaleReportScopeService $reportScopeService,
    ) {
    }

    public function buildBoard(array $filters, User $viewer): array
    {
        $normalized = $this->normalizeFilters($filters);
        $scopedStaffIds = $this->reportScopeService->resolveScopedStaffIds($viewer);

        $sale = $this->buildSaleTeamRanking($normalized, $viewer, $scopedStaffIds);
        $telesale = $this->buildTelesaleUserRanking($normalized, $viewer, $scopedStaffIds);
        $marketing = $this->buildMarketingSourceRanking($normalized, $viewer, $scopedStaffIds);

        $query = trim((string) ($normalized['q'] ?? ''));
        $suggestions = [];
        if ($query !== '') {
            $suggestions = collect([$sale, $telesale, $marketing])
                ->flatMap(fn(array $column) => collect($column['rows'])->pluck('name'))
                ->filter(fn(string $name) => str_contains(mb_strtolower($name), mb_strtolower($query)))
                ->unique()
                ->take(8)
                ->values()
                ->all();
        }

        return [
            'filters' => $normalized,
            'sale' => [
                'top3' => $sale['top3'],
                'list' => $sale['list'],
            ],
            'telesale' => [
                'top3' => $telesale['top3'],
                'list' => $telesale['list'],
            ],
            'marketing' => [
                'top3' => $marketing['top3'],
                'list' => $marketing['list'],
            ],
            'suggestions' => $suggestions,
        ];
    }

    public function buildSaleTeamRanking(array $filters, User $viewer, ?array $scopedStaffIds = null): array
    {
        $query = $this->baseOrderRevenueQuery($filters, $viewer, $scopedStaffIds)
            ->join('users as order_staff', 'order_staff.id', '=', 'orders.created_by')
            ->join('teams as sale_teams', function ($join) {
                $join->on('sale_teams.id', '=', 'order_staff.team_id')
                    ->where('sale_teams.type', TeamType::SALE->value);
            })
            ->selectRaw('sale_teams.id as entity_id')
            ->selectRaw('sale_teams.name as entity_name')
            ->selectRaw('COUNT(orders.id) as orders_count')
            ->selectRaw('SUM(' . $this->buildRevenueExpression($filters['revenue_mode']) . ') as raw_revenue')
            ->groupBy('sale_teams.id', 'sale_teams.name');

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $query->where('sale_teams.name', 'like', '%' . $search . '%');
        }

        /** @var Collection<int, object> $orderRows */
        $orderRows = $query->get();

        $contactQuery = DB::table('customer_interactions as ci')
            ->join('users as u', 'u.id', '=', 'ci.user_id')
            ->join('teams as t', function ($join) {
                $join->on('t.id', '=', 'u.team_id')
                    ->where('t.type', TeamType::SALE->value);
            })
            ->whereBetween('ci.interacted_at', [$filters['from_at'], $filters['to_at']])
            ->selectRaw('t.id as entity_id')
            ->selectRaw('t.name as entity_name')
            ->selectRaw('COUNT(DISTINCT ci.customer_id) as contacts_count')
            ->groupBy('t.id', 't.name');

        if (!$this->isSuperAdmin($viewer)) {
            $contactQuery->where('u.organization_id', $viewer->organization_id);
        }

        if (is_array($scopedStaffIds)) {
            $contactQuery->whereIn('ci.user_id', $scopedStaffIds);
        }

        if ($search !== '') {
            $contactQuery->where('t.name', 'like', '%' . $search . '%');
        }

        /** @var Collection<int, object> $contactRows */
        $contactRows = $contactQuery->get();

        return $this->buildRankedColumnData($orderRows, $contactRows, (int) ($filters['pushsale_rule_set_id'] ?? 0));
    }

    public function buildTelesaleUserRanking(array $filters, User $viewer, ?array $scopedStaffIds = null): array
    {
        $contactsQuery = DB::table('customer_interactions as ci')
            ->join('users as u', 'u.id', '=', 'ci.user_id')
            ->join('teams as t', function ($join) {
                $join->on('t.id', '=', 'u.team_id')
                    ->where('t.type', TeamType::CSKH->value);
            })
            ->whereBetween('ci.interacted_at', [$filters['from_at'], $filters['to_at']])
            ->selectRaw('u.id as entity_id')
            ->selectRaw('u.name as entity_name')
            ->selectRaw('COUNT(DISTINCT ci.customer_id) as contacts_count')
            ->groupBy('u.id', 'u.name');

        if (!$this->isSuperAdmin($viewer)) {
            $contactsQuery->where('u.organization_id', $viewer->organization_id);
        }

        if (is_array($scopedStaffIds)) {
            $contactsQuery->whereIn('ci.user_id', $scopedStaffIds);
        }

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $contactsQuery->where('u.name', 'like', '%' . $search . '%');
        }

        /** @var Collection<int, object> $contactRows */
        $contactRows = $contactsQuery->get();

        $orders = $this->baseOrderRevenueQuery($filters, $viewer, $scopedStaffIds)
            ->select('orders.id', 'orders.customer_id', 'orders.created_at')
            ->selectRaw($this->buildRevenueExpression($filters['revenue_mode']) . ' as raw_revenue')
            ->get();

        $orderRows = $this->mapTelesaleAttributedOrders($orders, $filters, $viewer, $scopedStaffIds, $search);

        return $this->buildRankedColumnData(
            collect($orderRows),
            $contactRows,
            (int) ($filters['pushsale_rule_set_id'] ?? 0)
        );
    }

    public function buildMarketingSourceRanking(array $filters, User $viewer, ?array $scopedStaffIds = null): array
    {
        $search = trim((string) ($filters['q'] ?? ''));

        $query = $this->baseOrderRevenueQuery($filters, $viewer, $scopedStaffIds)
            ->join('customers as c', 'c.id', '=', 'orders.customer_id')
            ->selectRaw('COALESCE(NULLIF(c.source, ""), ?) as entity_name', [__('marketing.honor_board.unknown_source')])
            ->selectRaw('COALESCE(NULLIF(c.source, ""), "__unknown__") as entity_id')
            ->selectRaw('COUNT(orders.id) as orders_count')
            ->selectRaw('SUM(' . $this->buildRevenueExpression($filters['revenue_mode']) . ') as raw_revenue')
            ->groupBy('entity_id', 'entity_name');

        if ($search !== '') {
            $query->where('c.source', 'like', '%' . $search . '%');
        }

        /** @var Collection<int, object> $orderRows */
        $orderRows = $query->get();

        $contactsQuery = $this->customerRepository->query()
            ->selectRaw('COALESCE(NULLIF(source, ""), ?) as entity_name', [__('marketing.honor_board.unknown_source')])
            ->selectRaw('COALESCE(NULLIF(source, ""), "__unknown__") as entity_id')
            ->selectRaw('COUNT(DISTINCT id) as contacts_count')
            ->whereBetween('created_at', [$filters['from_at'], $filters['to_at']])
            ->groupBy('entity_id', 'entity_name');

        if (!$this->isSuperAdmin($viewer)) {
            $contactsQuery->where('organization_id', $viewer->organization_id);
        }

        if ($search !== '') {
            $contactsQuery->where('source', 'like', '%' . $search . '%');
        }

        /** @var Collection<int, object> $contactRows */
        $contactRows = $contactsQuery->get();

        return $this->buildRankedColumnData($orderRows, $contactRows, (int) ($filters['pushsale_rule_set_id'] ?? 0));
    }

    private function baseOrderRevenueQuery(array $filters, User $viewer, ?array $scopedStaffIds = null)
    {
        $itemTotals = DB::table('order_items')
            ->selectRaw('order_id, SUM(total) as item_total')
            ->groupBy('order_id');

        $query = $this->orderRepository->query()
            ->leftJoinSub($itemTotals, 'order_item_totals', function ($join) {
                $join->on('order_item_totals.order_id', '=', 'orders.id');
            })
            ->whereBetween('orders.created_at', [$filters['from_at'], $filters['to_at']])
            ->whereIn('orders.status', [
                OrderStatus::CONFIRMED->value,
                OrderStatus::SHIPPING->value,
                OrderStatus::COMPLETED->value,
            ]);

        if (!$this->isSuperAdmin($viewer)) {
            $query->where('orders.organization_id', $viewer->organization_id);
        }

        if (is_array($scopedStaffIds)) {
            $query->whereIn('orders.created_by', $scopedStaffIds);
        }

        return $query;
    }

    /**
     * @param Collection<int, object> $orders
     * @return array<int, array<string, int|float|string>>
     */
    private function mapTelesaleAttributedOrders(
        Collection $orders,
        array $filters,
        User $viewer,
        ?array $scopedStaffIds,
        string $search,
    ): array {
        if ($orders->isEmpty()) {
            return [];
        }

        $customerIds = $orders->pluck('customer_id')->filter()->unique()->values();

        $interactionQuery = DB::table('customer_interactions as ci')
            ->join('users as u', 'u.id', '=', 'ci.user_id')
            ->join('teams as t', function ($join) {
                $join->on('t.id', '=', 'u.team_id')
                    ->where('t.type', TeamType::CSKH->value);
            })
            ->whereIn('ci.customer_id', $customerIds)
            ->whereBetween('ci.interacted_at', [$filters['from_at'], $filters['to_at']])
            ->select('ci.id', 'ci.customer_id', 'ci.user_id', 'ci.interacted_at', 'u.name as entity_name')
            ->orderByDesc('ci.interacted_at')
            ->orderByDesc('ci.id');

        if (!$this->isSuperAdmin($viewer)) {
            $interactionQuery->where('u.organization_id', $viewer->organization_id);
        }

        if (is_array($scopedStaffIds)) {
            $interactionQuery->whereIn('ci.user_id', $scopedStaffIds);
        }

        if ($search !== '') {
            $interactionQuery->where('u.name', 'like', '%' . $search . '%');
        }

        /** @var Collection<int, object> $interactions */
        $interactions = $interactionQuery->get();
        $byCustomer = $interactions->groupBy('customer_id');

        $rows = [];
        foreach ($orders as $order) {
            $customerInteractions = $byCustomer->get($order->customer_id, collect());
            if ($customerInteractions->isEmpty()) {
                continue;
            }

            $selected = $this->pickLastTouchInteraction($customerInteractions, (string) $order->created_at);
            if (!$selected) {
                continue;
            }

            $entityId = (int) $selected->user_id;
            if (!isset($rows[$entityId])) {
                $rows[$entityId] = [
                    'entity_id' => $entityId,
                    'entity_name' => $selected->entity_name,
                    'orders_count' => 0,
                    'raw_revenue' => 0.0,
                ];
            }

            $rows[$entityId]['orders_count']++;
            $rows[$entityId]['raw_revenue'] += (float) $order->raw_revenue;
        }

        return array_values($rows);
    }

    private function pickLastTouchInteraction(Collection $interactions, string $orderCreatedAt): ?object
    {
        foreach ($interactions as $interaction) {
            if (strtotime((string) $interaction->interacted_at) <= strtotime($orderCreatedAt)) {
                return $interaction;
            }
        }

        return null;
    }

    /**
     * @param Collection<int, object|array> $orderRows
     * @param Collection<int, object|array> $contactRows
     */
    private function buildRankedColumnData(Collection $orderRows, Collection $contactRows, int $pushsaleRuleSetId): array
    {
        $rows = [];

        foreach ($orderRows as $row) {
            $item = is_array($row) ? $row : (array) $row;
            $entityKey = (string) ($item['entity_id'] ?? '__unknown__');

            $rows[$entityKey] = [
                'entity_id' => $entityKey,
                'name' => (string) ($item['entity_name'] ?? __('marketing.honor_board.unknown_entity')),
                'contacts' => (int) ($rows[$entityKey]['contacts'] ?? 0),
                'orders' => (int) ($item['orders_count'] ?? 0),
                'raw_revenue' => round((float) ($item['raw_revenue'] ?? 0), 2),
            ];
        }

        foreach ($contactRows as $row) {
            $item = is_array($row) ? $row : (array) $row;
            $entityKey = (string) ($item['entity_id'] ?? '__unknown__');

            if (!isset($rows[$entityKey])) {
                $rows[$entityKey] = [
                    'entity_id' => $entityKey,
                    'name' => (string) ($item['entity_name'] ?? __('marketing.honor_board.unknown_entity')),
                    'contacts' => 0,
                    'orders' => 0,
                    'raw_revenue' => 0,
                ];
            }

            $rows[$entityKey]['contacts'] = (int) ($item['contacts_count'] ?? 0);
        }

        $ranked = collect($rows)
            ->values()
            ->map(function (array $row) use ($pushsaleRuleSetId) {
                $rule = $this->pushsaleRuleService->applyRuleSet($row['raw_revenue'], $pushsaleRuleSetId ?: null);
                $contacts = max(0, (int) $row['contacts']);
                $orders = max(0, (int) $row['orders']);

                $row['adjusted_revenue'] = round((float) ($rule['adjusted_revenue'] ?? 0), 2);
                $row['kpi_multiplier'] = round((float) ($rule['kpi_multiplier'] ?? 1), 4);
                $row['conversion_rate'] = $contacts > 0 ? round(($orders / $contacts) * 100, 2) : 0.0;

                return $row;
            })
            ->sort(function (array $a, array $b): int {
                if ($a['adjusted_revenue'] !== $b['adjusted_revenue']) {
                    return $a['adjusted_revenue'] < $b['adjusted_revenue'] ? 1 : -1;
                }

                if ($a['conversion_rate'] !== $b['conversion_rate']) {
                    return $a['conversion_rate'] < $b['conversion_rate'] ? 1 : -1;
                }

                return strcasecmp($a['name'], $b['name']);
            })
            ->values()
            ->map(function (array $row, int $index) {
                $row['rank'] = $index + 1;

                return $row;
            });

        return [
            'rows' => $ranked->all(),
            'top3' => $ranked->take(3)->values()->all(),
            'list' => $ranked->slice(3)->values()->all(),
        ];
    }

    private function normalizeFilters(array $filters): array
    {
        $datePreset = (string) ($filters['date_preset'] ?? 'this_month');
        $revenueMode = (string) ($filters['revenue_mode'] ?? 'after_discount');

        if (!in_array($datePreset, ['today', 'this_week', 'this_month', 'custom'], true)) {
            $datePreset = 'this_month';
        }

        if (!in_array($revenueMode, ['before_discount', 'after_discount'], true)) {
            $revenueMode = 'after_discount';
        }

        [$fromDate, $toDate] = $this->resolveDateRange(
            $datePreset,
            (string) ($filters['from_date'] ?? ''),
            (string) ($filters['to_date'] ?? '')
        );

        return [
            'pushsale_rule_set_id' => !empty($filters['pushsale_rule_set_id']) ? (int) $filters['pushsale_rule_set_id'] : null,
            'revenue_mode' => $revenueMode,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'from_at' => $fromDate . ' 00:00:00',
            'to_at' => $toDate . ' 23:59:59',
            'q' => trim((string) ($filters['q'] ?? '')),
            'date_preset' => $datePreset,
        ];
    }

    private function resolveDateRange(string $preset, string $fromDate, string $toDate): array
    {
        if ($preset === 'today') {
            $today = now()->toDateString();

            return [$today, $today];
        }

        if ($preset === 'this_week') {
            return [
                now()->startOfWeek(Carbon::MONDAY)->toDateString(),
                now()->endOfWeek(Carbon::SUNDAY)->toDateString(),
            ];
        }

        if ($preset === 'this_month') {
            return [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()];
        }

        $from = $this->safeDate($fromDate) ?? now()->startOfMonth();
        $to = $this->safeDate($toDate) ?? now();

        if ($to->lt($from)) {
            $to = $from->copy();
        }

        return [$from->toDateString(), $to->toDateString()];
    }

    private function safeDate(string $value): ?Carbon
    {
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function buildRevenueExpression(string $mode): string
    {
        if ($mode === 'before_discount') {
            return 'COALESCE(order_item_totals.item_total, 0) + COALESCE(orders.shipping_fee, 0) + COALESCE(orders.cod_fee, 0)';
        }

        return 'COALESCE(orders.total_amount, 0)';
    }

    private function isSuperAdmin(User $viewer): bool
    {
        return (int) $viewer->role === UserRole::SUPER_ADMIN->value;
    }
}
