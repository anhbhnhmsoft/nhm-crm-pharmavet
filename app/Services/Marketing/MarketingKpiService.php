<?php

namespace App\Services\Marketing;

use App\Models\User;
use Carbon\Carbon;

class MarketingKpiService
{
    public function __construct(
        protected MarketingBudgetService $marketingBudgetService,
    ) {
    }

    public function buildDashboard(array $filters, User $viewer): array
    {
        $current = $this->marketingBudgetService->summarize($filters, $viewer);

        [$from, $to] = [Carbon::parse($current['filters']['from_date']), Carbon::parse($current['filters']['to_date'])];
        $days = max(1, $from->diffInDays($to) + 1);

        $previousFilters = $filters;
        $previousFilters['from_date'] = $from->copy()->subDays($days)->toDateString();
        $previousFilters['to_date'] = $to->copy()->subDays($days)->toDateString();

        $previous = $this->marketingBudgetService->summarize($previousFilters, $viewer);

        $currentMetrics = $this->flattenSummary($current);
        $previousMetrics = $this->flattenSummary($previous);

        $cards = [];
        foreach ($currentMetrics as $key => $value) {
            $prev = (float) ($previousMetrics[$key] ?? 0);
            $variance = $prev > 0 ? round((($value - $prev) / $prev) * 100, 2) : 0.0;
            $cards[$key] = [
                'value' => $value,
                'previous' => $prev,
                'variance' => $variance,
                'trend' => $variance > 0 ? 'up' : ($variance < 0 ? 'down' : 'flat'),
            ];
        }

        return [
            'filters' => $current['filters'],
            'cards' => $cards,
            'rows' => $current['rows'],
        ];
    }

    private function flattenSummary(array $summary): array
    {
        $rows = collect($summary['rows'] ?? []);
        $totalSpend = round((float) $rows->sum(fn(array $row) => (float) $row['actual_spend'] + (float) $row['fee_amount']), 2);
        $validLeads = (int) $rows->sum('valid_leads');
        $completedRevenue = round((float) $rows->sum(fn(array $row) => (float) $row['new_revenue'] + (float) $row['old_revenue']), 2);
        $completedOrders = (int) $rows->sum('orders_count');

        return [
            'total_spend' => $totalSpend,
            'valid_leads' => $validLeads,
            'cost_per_lead' => $validLeads > 0 ? round($totalSpend / $validLeads, 2) : 0,
            'new_revenue' => round((float) $rows->sum('new_revenue'), 2),
            'old_revenue' => round((float) $rows->sum('old_revenue'), 2),
            'close_rate' => round((float) $rows->avg('close_rate'), 2),
            'cancel_rate' => round((float) $rows->avg('cancel_rate'), 2),
            'aov' => $completedOrders > 0 ? round($completedRevenue / $completedOrders, 2) : 0,
            'roi' => $totalSpend > 0 ? round($completedRevenue / $totalSpend, 4) : 0,
        ];
    }
}
