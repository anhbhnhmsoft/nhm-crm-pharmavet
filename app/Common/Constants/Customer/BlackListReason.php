<?php

namespace App\Common\Constants\Customer;

enum BlackListReason: int
{
    case OVERDUE_DEBT_30 = 1;
    case SCAM = 2;
    case OTHER = 3;

    public function label(): string
    {
        return match ($this) {
            self::OVERDUE_DEBT_30 => __('customer.blacklist_reason.overdue_debt_30'),
            self::SCAM => __('customer.blacklist_reason.scam'),
            self::OTHER => __('customer.blacklist_reason.other'),
        };
    }

    public static function toOptions(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }
        return $options;
    }
}
