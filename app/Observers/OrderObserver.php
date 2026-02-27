<?php

namespace App\Observers;

use App\Common\Constants\Accounting\ExpenseCategory;
use App\Common\Constants\Order\OrderStatus;
use App\Models\Order;
use App\Repositories\ExpenseRepository;
use Illuminate\Support\Facades\Log;
use Throwable;

class OrderObserver
{
    public function __construct(
        protected ExpenseRepository $expenseRepository
    ) {
    }

    public function updated(Order $order): void
    {
        // Tự động tạo expense cho chi phí giao hàng khi đơn hàng completed
        if ($order->wasChanged('status') && $order->status === OrderStatus::COMPLETED->value) {
            try {
                // Kiểm tra xem đã có expense cho đơn hàng này chưa
                $existingExpense = $this->expenseRepository->query()
                    ->where('order_id', $order->id)
                    ->where('category', ExpenseCategory::SHIPPING_AUTO->value)
                    ->first();

                if (!$existingExpense && $order->shipping_fee > 0) {
                    $this->expenseRepository->create([
                        'organization_id' => $order->organization_id,
                        'expense_date' => $order->updated_at->toDateString(),
                        'category' => ExpenseCategory::SHIPPING_AUTO->value,
                        'description' => __('accounting.expense.auto_shipping_fee', [
                            'order_code' => $order->code,
                        ]),
                        'amount' => $order->shipping_fee,
                        'order_id' => $order->id,
                        'created_by' => $order->updated_by,
                    ]);
                }
            } catch (Throwable $e) {
                Log::error('Auto create expense for completed order failed', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}

