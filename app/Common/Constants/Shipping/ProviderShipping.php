<?php

namespace App\Common\Constants\Shipping;

enum ProviderShipping: string
{
    case GHN     = 'GHN';
    case VIETTEL = 'Viettel';
    case MANUAL  = 'Manual';

    public function label(): string
    {
        return match ($this) {
            self::GHN     => __('enum.provider_shipping.ghn'),
            self::VIETTEL => __('enum.provider_shipping.viettel'),
            self::MANUAL  => __('enum.provider_shipping.manual'),
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

    public static function getLabel(string $value): string
    {
        return self::tryFrom($value)?->label() ?? $value;
    }
}
