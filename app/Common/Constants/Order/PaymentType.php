<?php

namespace App\Common\Constants\Order;

enum PaymentType: int
{
    case SELLER_PAYS = 1;
    case BUYER_PAYS_COD = 2;

    public function label(): string
    {
        return match ($this) {
            self::SELLER_PAYS => __('order.form.seller_pays'),
            self::BUYER_PAYS_COD => __('order.form.buyer_pays_cod'),
        };
    }

    public static function toOptions(): array
    {
        return [
            self::SELLER_PAYS->value => self::SELLER_PAYS->label(),
            self::BUYER_PAYS_COD->value => self::BUYER_PAYS_COD->label(),
        ];
    }
}
