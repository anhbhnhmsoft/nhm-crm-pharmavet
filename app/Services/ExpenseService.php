<?php

namespace App\Services;

use App\Common\Constants\Accounting\ExpenseCategory;
use App\Core\Logging;
use App\Core\ServiceReturn;
use App\Models\Expense;
use App\Models\Order;
use App\Repositories\ExpenseRepository;
use App\Repositories\UserRepository;
use App\Utils\AccountingPeriodGuard;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class ExpenseService
{
    public function __construct(
        protected ExpenseRepository $expenseRepository,
        protected UserRepository $userRepository,
    ) {
    }

    public function getDefaultMonthlySalary(int $organizationId): float
    {
        return $this->userRepository->getActiveSalarySumByOrganization($organizationId);
    }

    public function createMonthlySalaryExpense(
        int $organizationId,
        int $createdBy,
        string $month,
        float $totalSalary,
        string $payrollFile
    ): ServiceReturn {
        try {
            $formattedMonth = Carbon::parse($month)->format('m/Y');

            $expense = $this->expenseRepository->create([
                'organization_id' => $organizationId,
                'expense_date' => Carbon::parse($month)->endOfMonth(),
                'category' => ExpenseCategory::OPERATIONAL->value,
                'description' => __('accounting.expense.salary_month_description', ['month' => $formattedMonth]),
                'unit_price' => $totalSalary,
                'quantity' => 1,
                'amount' => $totalSalary,
                'attachments' => [$payrollFile],
                'created_by' => $createdBy,
            ]);

            return ServiceReturn::success(
                $expense,
                __('accounting.expense.salary_month_created', ['month' => $formattedMonth])
            );
        } catch (Throwable $e) {
            Logging::error('Create monthly salary expense failed', [
                'organization_id' => $organizationId,
                'month' => $month,
                'error' => $e->getMessage(),
            ], $e);

            return ServiceReturn::error(__('accounting.expense.create_failed'), $e);
        }
    }

    public function importBatchExpensesFromUploadedFile(
        int $organizationId,
        int $createdBy,
        string $disk,
        string $path
    ): ServiceReturn {
        try {
            if (! Storage::disk($disk)->exists($path)) {
                return ServiceReturn::error(__('accounting.expense.batch_import.file_not_found'));
            }

            $filePath = Storage::disk($disk)->path($path);
            $rows = Excel::toArray(new class {}, $filePath);
            $sheet = $rows[0] ?? [];

            if (empty($sheet)) {
                return ServiceReturn::error(__('accounting.expense.batch_import.file_empty'));
            }

            $header = array_shift($sheet);
            $normalizedHeader = array_map(fn ($value) => trim(mb_strtolower((string) $value)), $header);
            $requiredHeaders = __('accounting.expense.batch_import.headers');
            $columnMapping = [];

            foreach ($requiredHeaders as $key => $aliases) {
                foreach ($aliases as $alias) {
                    $index = array_search(trim(mb_strtolower($alias)), $normalizedHeader);

                    if ($index !== false) {
                        $columnMapping[$key] = $index;
                        break;
                    }
                }
            }

            $missingColumns = [];

            foreach ($requiredHeaders as $key => $aliases) {
                if (! isset($columnMapping[$key])) {
                    $missingColumns[] = '"' . ($aliases[0] ?? $key) . '"';
                }
            }

            if ($missingColumns !== []) {
                return ServiceReturn::error(
                    __('accounting.expense.batch_import.missing_columns', ['columns' => implode(', ', $missingColumns)])
                );
            }

            $items = [];

            foreach ($sheet as $row) {
                $price = (float) str_replace([',', '.'], '', $row[$columnMapping['unit_price']] ?? 0);
                $quantity = (int) ($row[$columnMapping['quantity']] ?? 1);

                $items[] = [
                    'expense_date' => $row[$columnMapping['date']] ?? null,
                    'category' => $row[$columnMapping['category']] ?? '',
                    'unit_price' => $price,
                    'quantity' => $quantity,
                    'amount' => $price * $quantity,
                    'description' => $row[$columnMapping['description']] ?? '',
                    'note' => isset($columnMapping['note']) ? ($row[$columnMapping['note']] ?? '') : '',
                ];
            }

            if ($items === []) {
                return ServiceReturn::error(__('accounting.expense.batch_import.no_valid_data'));
            }

            return $this->processBatchExpenses($organizationId, $createdBy, $items);
        } catch (Throwable $e) {
            Logging::error('Batch expense import file error', [
                'organization_id' => $organizationId,
                'path' => $path,
                'error' => $e->getMessage(),
            ], $e);

            return ServiceReturn::error(__('accounting.expense.batch_import.process_error'), $e);
        } finally {
            if (Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);
            }
        }
    }

    public function processBatchExpenses(int $organizationId, int $createdBy, array $items): ServiceReturn
    {
        try {
            $created = 0;
            $failed = 0;

            foreach ($items as $item) {
                try {
                    $this->expenseRepository->create([
                        'organization_id' => $organizationId,
                        'expense_date' => $this->normalizeDate($item['expense_date'] ?? null),
                        'category' => $this->normalizeCategory((string) ($item['category'] ?? '')),
                        'unit_price' => (float) ($item['unit_price'] ?? 0),
                        'quantity' => (int) ($item['quantity'] ?? 1),
                        'amount' => (float) ($item['amount'] ?? 0),
                        'description' => trim((string) ($item['description'] ?? __('accounting.expense.batch_import.default_description'))),
                        'note' => trim((string) ($item['note'] ?? '')),
                        'created_by' => $createdBy,
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
                [
                    'created' => $created,
                    'failed' => $failed,
                ],
                __('accounting.expense.batch_import.completed', [
                    'created' => $created,
                    'failed' => $failed,
                ])
            );
        } catch (Throwable $e) {
            Logging::error('Batch expense processing error', [
                'organization_id' => $organizationId,
                'error' => $e->getMessage(),
            ], $e);

            return ServiceReturn::error(__('accounting.expense.batch_import.process_error'), $e);
        }
    }

    public function createShippingExpenseForOrder(Order $order): ServiceReturn
    {
        try {
            if ($order->shipping_fee <= 0) {
                return ServiceReturn::error(__('accounting.expense.auto_shipping_fee_zero'));
            }

            $existingExpense = $this->expenseRepository->query()
                ->where('order_id', $order->id)
                ->where('category', ExpenseCategory::SHIPPING_AUTO->value)
                ->first();

            if ($existingExpense) {
                return ServiceReturn::success($existingExpense, __('accounting.expense.auto_shipping_exists'));
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
                'error' => $e->getMessage(),
            ], $e);

            return ServiceReturn::error(__('accounting.expense.auto_shipping_failed'));
        }
    }

    public function updateExpense(Expense $expense, array $data): ServiceReturn
    {
        try {
            $targetDate = $data['expense_date'] ?? $expense->expense_date;

            if (AccountingPeriodGuard::isClosedForDate($expense->organization_id, $targetDate)) {
                return ServiceReturn::error(__('accounting.accounting_period.period_closed'));
            }

            $updatedExpense = $this->expenseRepository->updateById($expense->id, $data);

            if (! $updatedExpense) {
                return ServiceReturn::error(__('common.error.data_not_found'));
            }

            return ServiceReturn::success($updatedExpense, __('common.success.update_success'));
        } catch (Throwable $e) {
            Logging::error('Update expense failed', [
                'expense_id' => $expense->id,
                'organization_id' => $expense->organization_id,
                'error' => $e->getMessage(),
            ], $e);

            return ServiceReturn::error($e->getMessage(), $e);
        }
    }

    public function deleteExpense(Expense $expense): ServiceReturn
    {
        try {
            if (AccountingPeriodGuard::isClosedForRecord($expense, 'expense_date')) {
                return ServiceReturn::error(__('accounting.accounting_period.period_closed'));
            }

            $deleted = $this->expenseRepository->delete($expense->id);

            if (! $deleted) {
                return ServiceReturn::error(__('common.error.data_not_found'));
            }

            return ServiceReturn::success(message: __('common.success.delete_success'));
        } catch (Throwable $e) {
            Logging::error('Delete expense failed', [
                'expense_id' => $expense->id,
                'organization_id' => $expense->organization_id,
                'error' => $e->getMessage(),
            ], $e);

            return ServiceReturn::error($e->getMessage(), $e);
        }
    }

    private function normalizeDate(mixed $date): string
    {
        try {
            if (is_numeric($date)) {
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
