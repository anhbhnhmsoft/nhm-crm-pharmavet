<?php

namespace App\Common\Constants\Accounting;

enum DebtPartnerType: int
{
    case CUSTOMER = 1;
    case LOGISTICS = 2;

    public static function getOptions(): array
    {
        return [
            self::CUSTOMER->value => __('accounting.debt_reconciliation.partner_customer'),
            self::LOGISTICS->value => __('accounting.debt_reconciliation.partner_logistics'),
        ];
    }

    public function getLabel(): string
    {
        return self::getOptions()[$this->value] ?? '';
    }
}
