<?php

namespace App\Services;

use App\Common\Constants\Accounting\ExpenseCategory;
use App\Core\Logging;
use App\Core\ServiceReturn;
use App\Models\Order;
use App\Repositories\ExpenseRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Throwable;

class ExpenseService
{
    public function __construct(
        protected ExpenseRepository $expenseRepository
    ) {
    }

    /**
     * Xử lý nhập chi phí hàng loạt từ Excel
     */
    public function processBatchExpenses(int $organizationId, array $items): ServiceReturn
    {
        try {
            $created = 0;
            $failed = 0;

            foreach ($items as $item) {
                try {
                    $this->expenseRepository->create([
                        'organization_id' => $organizationId,
                        'expense_date' => $this->normalizeDate($item['expense_date'] ?? null),
                        'category' => $this->normalizeCategory($item['category'] ?? ''),
                        'unit_price' => (float) ($item['unit_price'] ?? 0),
                        'quantity' => (int) ($item['quantity'] ?? 1),
                        'amount' => (float) ($item['amount'] ?? 0),
                        'description' => trim($item['description'] ?? 'Chi phí hàng loạt'),
                        'note' => trim($item['note'] ?? ''),
                        'created_by' => Auth::id(),
                    ]);
                    $created++;
                } catch (Throwable $e) {
                    $failed++;
                    Logging::error('Batch expense row error', ['error' => $e->getMessage(), 'item' => $item], $e);
                }
            }

            Logging::web('Batch expense import completed', [
                'organization_id' => $organizationId,
                'created_count' => $created,
                'failed_count' => $failed,
            ]);

            return ServiceReturn::success(
                data: [
                    'created' => $created,
                    'failed' => $failed,
                ],
                message: "Đã tạo thành công {$created} chi phí. Thất bại: {$failed}."
            );
        } catch (Throwable $e) {
            Logging::error('Batch expense processing error', [
                'organization_id' => $organizationId,
                'error' => $e->getMessage()
            ], $e);
            return ServiceReturn::error('Có lỗi xảy ra khi xử lý dữ liệu hàng loạt.');
        }
    }

    /**
     * Tạo chi phí vận chuyển tự động cho đơn hàng
     */
    public function createShippingExpenseForOrder(Order $order): ServiceReturn
    {
        try {
            if ($order->shipping_fee <= 0) {
                return ServiceReturn::error('Shipping fee is zero');
            }

            $existingExpense = $this->expenseRepository->query()
                ->where('order_id', $order->id)
                ->where('category', ExpenseCategory::SHIPPING_AUTO->value)
                ->first();

            if ($existingExpense) {
                return ServiceReturn::success($existingExpense, 'Expense already exists');
            }

            $expense = $this->expenseRepository->create([
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

            return ServiceReturn::success($expense);
        } catch (Throwable $e) {
            Logging::error('Auto create shipping expense failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ], $e);
            return ServiceReturn::error('Có lỗi khi tạo chi phí vận chuyển tự động.');
        }
    }

    private function normalizeDate($date): string
    {
        try {
            if (is_numeric($date)) {
                // Excel serial date format
                return Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($date))->toDateString();
            }
            return Carbon::parse($date ?? now())->toDateString();
        } catch (Throwable) {
            return now()->toDateString();
        }
    }

    private function normalizeCategory(string $categoryName): int
    {
        $name = mb_strtolower(trim($categoryName));

        if (str_contains($name, 'vận hành') || str_contains($name, 'operat')) {
            return ExpenseCategory::OPERATIONAL->value;
        }
        if (str_contains($name, 'mkt') || str_contains($name, 'marketing')) {
            return ExpenseCategory::MARKETING->value;
        }
        if (str_contains($name, 'tài chính') || str_contains($name, 'finan')) {
            return ExpenseCategory::FINANCIAL->value;
        }

        return ExpenseCategory::OTHER->value;
    }
}
