<?php

namespace App\Common\Constants\Accounting;

enum DebtTransactionType: int
{
    case DEBIT = 1;  // Tăng nợ
    case CREDIT = 2; // Giảm nợ

    public function label(): string
    {
        return match ($this) {
            self::DEBIT => __('accounting.debt_transaction.debit'),
            self::CREDIT => __('accounting.debt_transaction.credit'),
        };
    }
}
