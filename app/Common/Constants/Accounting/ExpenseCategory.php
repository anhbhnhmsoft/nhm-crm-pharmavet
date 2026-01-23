<?php

namespace App\Common\Constants\Accounting;

enum ExpenseCategory: int
{
    case SALARY = 1; // Lương
    case MARKETING = 2; // MKT
    case SHIPPING = 3; // Đối soát giao hàng
    case MANAGEMENT = 4; // Quản lý doanh nghiệp
    case OFFICE = 5; // Văn phòng
    case OTHER = 6; // Chi tiêu khác
    case COST_OF_GOODS = 7; // Giá vốn

    public static function getOptions(): array
    {
        return [
            self::SALARY->value => __('accounting.expense_category.salary'),
            self::MARKETING->value => __('accounting.expense_category.marketing'),
            self::SHIPPING->value => __('accounting.expense_category.shipping'),
            self::MANAGEMENT->value => __('accounting.expense_category.management'),
            self::OFFICE->value => __('accounting.expense_category.office'),
            self::OTHER->value => __('accounting.expense_category.other'),
            self::COST_OF_GOODS->value => __('accounting.expense_category.cost_of_goods'),
        ];
    }

    public function getLabel(): string
    {
        return self::getOptions()[$this->value] ?? '';
    }
}

