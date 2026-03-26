<?php

namespace App\Services\Accounting;

use App\Common\Constants\Accounting\ExpenseCategory;
use App\Models\Order;
use App\Repositories\Accounting\DebtRepository;
use App\Repositories\ExpenseRepository;
use App\Core\ServiceReturn;

class DebtService
{
    public function __construct(
        protected DebtRepository $debtRepository,
        protected ExpenseRepository $expenseRepository
    ) {}

    public function provisionDebt(Order $order, float $amount, string $note = ''): ServiceReturn
    {
        if ($order->remaining_debt < $amount) {
            return ServiceReturn::error(__('accounting.bad_debt.provision_exceeds_debt'));
        }

        try {
            \DB::beginTransaction();

            // Create expense record
            $this->expenseRepository->create([
                'organization_id' => $order->organization_id,
                'expense_date' => now(),
                'category' => ExpenseCategory::BAD_DEBT->value,
                'amount' => $amount,
                'description' => __('accounting.bad_debt.provision_desc', ['order' => $order->code]),
                'note' => $note,
                'order_id' => $order->id,
                'created_by' => auth()->id(),
            ]);

            // Update order provision status
            $order->update([
                'debt_provision_amount' => $order->debt_provision_amount + $amount,
            ]);

            \DB::commit();
            return ServiceReturn::success($order);
        } catch (\Exception $e) {
            \DB::rollBack();
            return ServiceReturn::error($e->getMessage());
        }
    }

    public function writeOffDebt(Order $order, int $userId, string $note = ''): ServiceReturn
    {
        if ($order->is_written_off) {
            return ServiceReturn::error(__('accounting.bad_debt.already_written_off'));
        }

        try {
            $order->update([
                'is_written_off' => true,
                'write_off_at' => now(),
                'write_off_by' => $userId,
                'note' => $order->note . "\n" . __('accounting.bad_debt.write_off_note', ['note' => $note]),
            ]);

            return ServiceReturn::success($order);
        } catch (\Exception $e) {
            return ServiceReturn::error($e->getMessage());
        }
    }
}
