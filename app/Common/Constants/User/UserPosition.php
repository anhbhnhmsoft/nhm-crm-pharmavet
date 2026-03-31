<?php

namespace App\Common\Constants\User;

enum UserPosition: int
{
    case LEADER = 2;
    case STAFF = 3;

    public function label(): string
    {
        return match ($this) {
            self::LEADER => __('enum.user_position.leader'),
            self::STAFF => __('enum.user_position.staff'),
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
