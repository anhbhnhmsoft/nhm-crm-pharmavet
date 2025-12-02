<?php

namespace App\Common\Constants\User;

enum UserRole: int
{
    case SUPER_ADMIN = 1;
    case ADMIN = 2;
    case WAREHOUSE = 3;
    case ACCOUNTING = 4;
    case MARKETING = 5;
    case SALE = 6;

    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => __('enum.user_role.super_admin'),
            self::ADMIN => __('enum.user_role.admin'),
            self::WAREHOUSE => __('enum.user_role.warehouse'),
            self::ACCOUNTING => __('enum.user_role.accounting'),
            self::MARKETING => __('enum.user_role.marketing'),
            self::SALE => __('enum.user_role.sale'),
        };
    }

    public static function getOptions(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            // Lọc ra case SUPER_ADMIN
            if ($case === self::SUPER_ADMIN) {
                continue;
            }
            // Thêm vào mảng theo format [value => label]
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    public static function getLabel(int $value): string
    {
        return self::tryFrom($value)->label();
    }
}
