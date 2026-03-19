<?php

namespace App\Common\Constants\Accounting;

enum ReconciliationStatus: int
{
    case PENDING = 1; // Chờ xác nhận
    case CONFIRMED = 2; // Đã xác nhận
    case CANCELLED = 3; // Đã hủy
    case PAID = 4; // Đã thanh toán

    public static function getOptions(): array
    {
        return [
            self::PENDING->value => __('accounting.reconciliation_status.pending'),
            self::CONFIRMED->value => __('accounting.reconciliation_status.confirmed'),
            self::CANCELLED->value => __('accounting.reconciliation_status.cancelled'),
            self::PAID->value => __('accounting.reconciliation_status.paid'),
        ];
    }

    public function getLabel(): string
    {
        return self::getOptions()[$this->value] ?? '';
    }
}

