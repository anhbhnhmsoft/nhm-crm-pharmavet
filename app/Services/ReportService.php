<?php

namespace App\Services;

use App\Core\ServiceReturn;
use App\Core\Logging;
use App\Repositories\OrderRepository;
use App\Repositories\ExpenseRepository;
use App\Repositories\RevenueRepository;
use Illuminate\Support\Facades\DB;
use Throwable;

class ReportService
{
    public function __construct(
        protected OrderRepository $orderRepository,
        protected ExpenseRepository $expenseRepository,
        protected RevenueRepository $revenueRepository,
    ) {}

    /**
     * Báo cáo kinh doanh theo tháng/ngày
     */
    public function getBusinessReport(int $organizationId, string $fromDate, string $toDate, string $type = 'day'): ServiceReturn
    {
        try {
            // Doanh thu từ đơn hàng
            $orders = $this->orderRepository->query()
                ->where('organization_id', $organizationId)
                ->whereBetween('created_at', [$fromDate, $toDate])
                ->get();

            $revenueFromOrders = $orders->where('status', 'completed')->sum('total_amount');
            $revenueReturned = $orders->where('status', 'cancelled')->sum('total_amount');
            $revenueShipping = $orders->where('status', 'shipping')->sum('total_amount');

            // Doanh thu khác
            $otherRevenues = $this->revenueRepository->query()
                ->where('organization_id', $organizationId)
                ->whereBetween('revenue_date', [$fromDate, $toDate])
                ->sum('amount');

            $totalRevenue = $revenueFromOrders + $otherRevenues;

            // Chi phí
            $expenses = $this->expenseRepository->query()
                ->where('organization_id', $organizationId)
                ->whereBetween('expense_date', [$fromDate, $toDate])
                ->get();

            $totalExpense = $expenses->sum('amount');
            $expenseByCategory = $expenses->groupBy('category')->map(fn($group) => $group->sum('amount'));

            // Lợi nhuận
            $profit = $totalRevenue - $totalExpense;
            $profitRate = $totalRevenue > 0 ? ($profit / $totalRevenue) * 100 : 0;

            $report = [
                'period' => [
                    'from' => $fromDate,
                    'to' => $toDate,
                    'type' => $type,
                ],
                'revenue' => [
                    'from_orders' => $revenueFromOrders,
                    'from_orders_completed' => $revenueFromOrders,
                    'from_orders_returned' => $revenueReturned,
                    'from_orders_shipping' => $revenueShipping,
                    'other' => $otherRevenues,
                    'total' => $totalRevenue,
                ],
                'expense' => [
                    'total' => $totalExpense,
                    'by_category' => $expenseByCategory,
                ],
                'profit' => [
                    'amount' => $profit,
                    'rate' => round($profitRate, 2),
                ],
            ];

            return ServiceReturn::success(data: $report);
        } catch (Throwable $e) {
            Logging::error('Get business report error', ['error' => $e->getMessage()], $e);
            return ServiceReturn::error(__('accounting.report.get_failed'));
        }
    }
}

