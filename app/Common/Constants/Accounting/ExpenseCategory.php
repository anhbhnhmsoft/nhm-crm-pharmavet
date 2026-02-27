<?php

namespace App\Common\Constants\Accounting;

enum ExpenseCategory: int
{
    case SALES = 1;         // Bán hàng
    case MARKETING = 2;     // MKT
    case RECONCILIATION = 3; // Đối soát
    case SHIPPING_AUTO = 4; // Giao hàng (auto)
    case MANAGEMENT = 5;    // Quản lý
    case OFFICE = 6;        // Văn phòng
    case SPENDING = 7;      // Chi tiêu
    case OTHER = 8;         // Khác
    case COST_OF_GOODS = 9; // Giá vốn

    public static function getOptions(): array
    {
        return [
            self::SALES->value => __('accounting.expense_category.sales'),
            self::MARKETING->value => __('accounting.expense_category.marketing'),
            self::RECONCILIATION->value => __('accounting.expense_category.reconciliation'),
            self::SHIPPING_AUTO->value => __('accounting.expense_category.shipping_auto'),
            self::MANAGEMENT->value => __('accounting.expense_category.management'),
            self::OFFICE->value => __('accounting.expense_category.office'),
            self::SPENDING->value => __('accounting.expense_category.spending'),
            self::OTHER->value => __('accounting.expense_category.other'),
            self::COST_OF_GOODS->value => __('accounting.expense_category.cost_of_goods'),
        ];
    }

    public function getLabel(): string
    {
        return self::getOptions()[$this->value] ?? '';
    }
}
