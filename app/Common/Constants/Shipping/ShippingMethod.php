<?php

namespace App\Common\Constants\Shipping;

enum ShippingMethod: string
{
    case GHN = 'GHN';

    public function label(): string
    {
        return match ($this) {
            self::GHN => __('filament.shipping.shipping_method.ghn'),
        };
    }

    public static function getOptions(): array
    {
        return collect(self::cases())
            ->mapWithKeys(function ($case) {
                return [$case->value => $case->label()];
            })
            ->toArray();
    }
}