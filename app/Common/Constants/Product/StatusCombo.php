<?php

namespace App\Common\Constants\Product;

enum StatusCombo: int
{
    case ACTIVE = 1;
    case EXPIRED = 2;
    case UPCOMING = 3;

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE   => __('common.status.active'),
            self::EXPIRED  => __('common.status.expired'),
            self::UPCOMING => __('common.status.upcoming'),
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
