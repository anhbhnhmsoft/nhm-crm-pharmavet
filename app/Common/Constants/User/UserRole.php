<?php

namespace App\Common\Constants\User;

enum UserRole: int
{
    case SUPER_ADMIN = 1; //Quản trị viên cấp cao nhất
    case ADMIN = 2; //Quản trị viên
    case WAREHOUSE = 3; //Thủ kho
    case ACCOUNTING = 4; //Kế toán
    case MARKETING = 5; //Marketing
    case SALE = 6; //Nhân viên kinh doanh

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
