<?php

namespace App\Services;

use App\Common\Constants\Organization\FundTransactionStatus;
use App\Common\Constants\Organization\FundTransactionType;
use App\Core\ServiceReturn;
use App\Models\Fund;
use App\Models\FundTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class FundService
{
    /**
     * Thực hiện giao dịch nạp/rút tiền
     */
    public function createTransaction(Fund $fund, array $data): ServiceReturn
    {
        if ($fund->is_locked) {
            return ServiceReturn::error(__('accounting.fund.notifications.locked_warning'));
        }

        try {
            return DB::transaction(function () use ($fund, $data) {
                $amount = $data['amount'];
                $type = $data['type']; // Use FundTransactionType values (2: Deposit, 3: Withdraw)

                $newBalance = $fund->balance;
                if ($type == FundTransactionType::DEPOSIT->value) {
                    $newBalance += $amount;
                } elseif ($type == FundTransactionType::WITHDRAW->value) {
                    $newBalance -= $amount;
                } else {
                    return ServiceReturn::error('Invalid transaction type');
                }

                $transaction = $fund->transactions()->create([
                    'type' => $type,
                    'amount' => $amount,
                    'balance_after' => $newBalance,
                    'description' => $data['description'] ?? '',
                    'status' => FundTransactionStatus::COMPLETED->value,
                    'transaction_code' => 'FT' . now()->format('YmdHis') . rand(100, 999),
                ]);

                $fund->update(['balance' => $newBalance]);

                return ServiceReturn::success($transaction);
            });
        } catch (Throwable $e) {
            Log::error('Fund transaction error: ' . $e->getMessage());
            return ServiceReturn::error(__('error.common_error_server'));
        }
    }

    /**
     * Khóa quỹ
     */
    public function lockFund(Fund $fund): ServiceReturn
    {
        try {
            $fund->update(['is_locked' => true]);
            return ServiceReturn::success(message: __('accounting.fund.notifications.locked'));
        } catch (Throwable $e) {
            return ServiceReturn::error(__('accounting.fund.notifications.lock_failed'));
        }
    }

    /**
     * Mở khóa quỹ
     */
    public function unlockFund(Fund $fund): ServiceReturn
    {
        try {
            $fund->update(['is_locked' => false]);
            return ServiceReturn::success(message: __('accounting.fund.notifications.unlocked'));
        } catch (Throwable $e) {
            return ServiceReturn::error(__('accounting.fund.notifications.unlock_failed'));
        }
    }
}
