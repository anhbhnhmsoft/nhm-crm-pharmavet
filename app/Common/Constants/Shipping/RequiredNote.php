<?php

namespace App\Common\Constants\Shipping;

enum RequiredNote: int
{
    case ALLOW_TO_TRY = 1;
    case ALLOW_VIEWING_NOT_TRIAL = 2;
    case NO_VIEWING = 3;

    public function label(): string
    {
        return match ($this) {
            self::ALLOW_TO_TRY              => __('filament.shipping.allow_to_try'),
            self::ALLOW_VIEWING_NOT_TRIAL   => __('filament.shipping.allow_viewing_not_trial'),
            self::NO_VIEWING                => __('filament.shipping.no_viewing'),
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

    public static function getLabel(int $value): string
    {
        return self::tryFrom($value)?->label();
    }
}
