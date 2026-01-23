<?php

namespace App\Common\Constants\Accounting;

enum ReconciliationStatus: int
{
    case PENDING = 1; // Chờ xác nhận
    case CONFIRMED = 2; // Đã xác nhận
    case CANCELLED = 3; // Đã hủy

    public static function getOptions(): array
    {
        return [
            self::PENDING->value => __('accounting.reconciliation_status.pending'),
            self::CONFIRMED->value => __('accounting.reconciliation_status.confirmed'),
            self::CANCELLED->value => __('accounting.reconciliation_status.cancelled'),
        ];
    }

    public function getLabel(): string
    {
        return self::getOptions()[$this->value] ?? '';
    }
}

