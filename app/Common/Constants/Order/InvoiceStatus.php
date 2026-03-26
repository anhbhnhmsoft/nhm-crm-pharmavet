<?php

namespace App\Common\Constants\Order;

enum InvoiceStatus: int
{
    case UNISSUED = 1;
    case ISSUED = 2;
    case CANCELLED = 3;

    public function getLabel(): string
    {
        return match ($this) {
            self::UNISSUED => __('order.invoice_status_options.unissued'),
            self::ISSUED => __('order.invoice_status_options.issued'),
            self::CANCELLED => __('order.invoice_status_options.cancelled'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::UNISSUED => 'gray',
            self::ISSUED => 'success',
            self::CANCELLED => 'danger',
        };
    }

    public static function toArray(): array
    {
        $array = [];
        foreach (self::cases() as $case) {
            $array[$case->value] = $case->getLabel();
        }
        return $array;
    }
}
