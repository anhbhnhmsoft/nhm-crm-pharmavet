<?php

namespace App\Common\Constants\Shipping;

enum ProviderShipping: string
{
    case GHN = 'GHN';

    public function label(): string
    {
        return match ($this) {
            self::GHN => 'GHN',
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
        return self::tryFrom($value)->label();
    }
}
