<?php

namespace App\Common\Constants\Order;

enum ServiceType: int
{
    case LIGHT = 2;
    case HEAVY = 5;

    public function label(): string
    {
        return match ($this) {
            self::LIGHT => __('order.form.light'),
            self::HEAVY => __('order.form.heavy'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::LIGHT => 'info',
            self::HEAVY => 'warning',
        };
    }

    public static function toOptions(): array
    {
        return [
            self::LIGHT->value => self::LIGHT->label(),
            self::HEAVY->value => self::HEAVY->label(),
        ];
    }
}
