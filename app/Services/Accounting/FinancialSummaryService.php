<?php

namespace App\Services\Accounting;

use App\Repositories\FinancialSummaryRepository;
use App\Repositories\OrderRepository;
use App\Repositories\InventoryTicketRepository;
use App\Repositories\RevenueRepository;
use App\Repositories\ExpenseRepository;
use App\Core\ServiceReturn;
use Carbon\Carbon;
use Throwable;
use Illuminate\Support\Facades\Log;

class FinancialSummaryService
{
    public function __construct(
        protected FinancialSummaryRepository $financialSummaryRepository,
        protected OrderRepository $orderRepository,
        protected InventoryTicketRepository $inventoryTicketRepository,
        protected RevenueRepository $revenueRepository,
        protected ExpenseRepository $expenseRepository
    ) {
    }

    /**
     * Đồng bộ báo cáo tài chính cho một ngày cụ thể của một tổ chức
     */
    public function syncDailySummary(int $organizationId, string $date): ServiceReturn
    {
        try {
            $carbonDate = Carbon::parse($date);
            $startDate = $carbonDate->startOfDay()->toDateTimeString();
            $endDate = $carbonDate->endOfDay()->toDateTimeString();
            $dateStr = $carbonDate->toDateString();

            $completedOrders = $this->orderRepository->findCompletedOrdersByDateRange($organizationId, $startDate, $endDate);

            $ordersCount = $completedOrders->count();
            $totalDiscounts = (float) $completedOrders->sum('discount');
            $grossRevenue = (float) $completedOrders->sum('total_amount') + $totalDiscounts;

            //Tính COGS (Giá vốn hàng bán)
            $totalCogs = 0;
            foreach ($completedOrders as $order) {
                foreach ($order->items as $item) {
                    $totalCogs += ($item->quantity * ($item->cost_price ?? 0));
                }
            }

            $returnsValue = $this->inventoryTicketRepository->sumCompletedSalesReturnsByDate($organizationId, $startDate, $endDate);

            $otherRevenues = $this->revenueRepository->sumTotalByDateRange($organizationId, $dateStr, $dateStr);

            $totalExpenses = $this->expenseRepository->sumTotalByDateRange($organizationId, $dateStr, $dateStr);

            $netRevenue = $grossRevenue - $totalDiscounts - $returnsValue;
            $grossProfit = $netRevenue - $totalCogs;
            $netProfit = $grossProfit + $otherRevenues - $totalExpenses;

            $grossMarginRate = $netRevenue > 0 ? ($grossProfit / $netRevenue) * 100 : 0;
            $netMarginRate = ($netRevenue + $otherRevenues) > 0 
                ? ($netProfit / ($netRevenue + $otherRevenues)) * 100 
                : 0;

            $summary = $this->financialSummaryRepository->updateOrCreateSummary(
                $organizationId,
                $dateStr,
                [
                    'orders_count' => $ordersCount,
                    'gross_revenue' => $grossRevenue,
                    'discounts' => $totalDiscounts,
                    'returns_value' => $returnsValue,
                    'net_revenue' => $netRevenue,
                    'cogs' => $totalCogs,
                    'gross_profit' => $grossProfit,
                    'other_revenues' => $otherRevenues,
                    'total_expenses' => $totalExpenses,
                    'net_profit' => $netProfit,
                    'gross_margin_rate' => round($grossMarginRate, 2),
                    'net_margin_rate' => round($netMarginRate, 2),
                ]
            );

            return ServiceReturn::success($summary);
        } catch (Throwable $e) {
            Log::error('Sync Daily Summary failed', [
                'organization_id' => $organizationId,
                'date' => $date,
                'error' => $e->getMessage()
            ]);
            return ServiceReturn::error(__('accounting.summary.sync_failed'));
        }
    }

    public function syncRange(int $organizationId, string $fromDate, string $toDate): ServiceReturn
    {
        try {
            $start = Carbon::parse($fromDate);
            $end = Carbon::parse($toDate);

            while ($start <= $end) {
                $this->syncDailySummary($organizationId, $start->toDateString());
                $start->addDay();
            }

            return ServiceReturn::success();
        } catch (Throwable $e) {
            Log::error('Sync Range Summary failed', [
                'organization_id' => $organizationId,
                'from' => $fromDate,
                'to' => $toDate,
                'error' => $e->getMessage()
            ]);
            return ServiceReturn::error(__('accounting.summary.sync_range_failed'));
        }
    }
}
