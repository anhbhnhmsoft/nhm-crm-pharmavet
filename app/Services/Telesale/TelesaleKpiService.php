<?php

namespace App\Services\Telesale;

use App\Models\Order;
use App\Models\SaleKpiTarget;
use App\Repositories\OrderRepository;
use App\Repositories\SaleKpiTargetRepository;
use Illuminate\Support\Carbon;

class TelesaleKpiService
{

    public function __construct(
        private OrderRepository $orderRepository,
        private SaleKpiTargetRepository $saleKpiTargetRepository,
    ) {
    }

    public function buildMonthlyKpiSummary(int $organizationId, ?int $userId, Carbon $month): array
    {
        $monthKey = $month->format('Y-m');
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $query = $this->orderRepository->query()
            ->where('organization_id', $organizationId)
            ->whereBetween('created_at', [$start, $end]);

        if (!empty($userId)) {
            $query->where('created_by', $userId);
        }

        $revenue = (float) $query->sum('total_amount');

        $targetQuery = $this->saleKpiTargetRepository->query()
            ->where('organization_id', $organizationId)
            ->where('month', $monthKey);

        if (!empty($userId)) {
            $targetQuery->where('user_id', $userId);
        }

        $target = (float) $targetQuery->sum('kpi_amount');
        $baseSalary = (float) $targetQuery->sum('base_salary');

        $daysProgress = round((now()->day / max(1, $month->daysInMonth)) * 100, 2);
        $kpiProgress = $target > 0 ? round(($revenue / $target) * 100, 2) : 0;

        return [
            'revenue' => $revenue,
            'target' => $target,
            'days_progress' => $daysProgress,
            'kpi_progress' => $kpiProgress,
            'base_salary' => $baseSalary,
            'estimated_bonus' => round(max(0, $revenue - $target) * 0.05, 2),
            'estimated_income' => round($baseSalary + max(0, $revenue - $target) * 0.05, 2),
        ];
    }
}
