<?php

namespace App\Common\Constants\Shipping;

enum ShiftGetGood: int
{
    case MORNING   = 1;
    case AFTERNOON = 2;
    case EVENING   = 3;

    public function label(): string
    {
        return match ($this) {
            self::MORNING    => __('filament.shipping.morning_shift'),
            self::AFTERNOON  => __('filament.shipping.afternoon_shift'),
            self::EVENING    => __('filament.shipping.evening_shift'),
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
        return self::tryFrom($value)->label();
    }
}
