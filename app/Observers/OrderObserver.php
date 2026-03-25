<?php

namespace App\Observers;

use App\Common\Constants\Order\OrderStatus;
use App\Models\Order;
use App\Services\ExpenseService;
use App\Services\Accounting\FinancialSummaryService;

class OrderObserver
{
    public function __construct(
        protected ExpenseService $expenseService,
        protected FinancialSummaryService $financialSummaryService
    ) {
    }

    public function updated(Order $order): void
    {
        if ($order->wasChanged('status')) {
            $currentStatus = $order->status;
            $oldStatus = $order->getOriginal('status');

            if ($currentStatus === OrderStatus::COMPLETED->value || $oldStatus === OrderStatus::COMPLETED->value) {
                // Đồng bộ lại báo cáo tài chính cho ngày tạo đơn
                $this->financialSummaryService->syncDailySummary($order->organization_id, $order->created_at->toDateString());
            }

            if ($currentStatus === OrderStatus::COMPLETED->value) {
                $this->expenseService->createShippingExpenseForOrder($order);
            }
        }
    }
}
