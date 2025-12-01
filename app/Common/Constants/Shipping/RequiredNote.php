<?php

namespace App\Common\Constants\Shipping;

enum RequiredNote: string
{
    case ALLOW_TO_TRY = 'CHOTHUHANG';
    case ALLOW_VIEWING_NOT_TRIAL = 'CHOXEMHANGKHONGTHU';
    case NO_VIEWING = 'KHONGCHOXEMHANG';

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

    public static function getLabel(string $value): string
    {
        return self::tryFrom($value)->label();
    }
}
