<?php

namespace App\Services;

use App\Common\Constants\Accounting\ExpenseCategory;
use App\Core\Logging;
use App\Core\ServiceReturn;
use App\Models\Expense;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Throwable;

class ExpenseService
{
    /**
     * Xử lý nhập chi phí hàng loạt từ Excel
     * 
     * @param int $organizationId
     * @param array $items
     * @return ServiceReturn
     */
    public function processBatchExpenses(int $organizationId, array $items): ServiceReturn
    {
        try {
            $created = 0;
            $failed = 0;

            foreach ($items as $item) {
                try {
                    Expense::create([
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
