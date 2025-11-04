<?php

namespace App\Common\Constants\Team;

enum TeamType: int
{
    case SALE = 1;
    case CSKH = 2;
    case MARKETING = 3;
    case BILL_OF_LADING = 4;

    public function label(): string
    {
        return match ($this) {
            self::SALE => __('enum.team_type.sale'),
            self::CSKH => __('enum.team_type.cskh'),
            self::MARKETING => __('enum.team_type.marketing'),
            self::BILL_OF_LADING => __('enum.team_type.billoflading'),
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
