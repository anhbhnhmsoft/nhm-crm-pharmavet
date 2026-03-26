<?php

namespace App\Common\Constants\Accounting;

enum ExpenseCategory: int
{
    case MARKETING = 1;      // MKT
    case OPERATIONAL = 2;    // Vận hành
    case FINANCIAL = 3;      // Tài chính
    case OTHER = 4;          // Khác
    case COST_OF_GOODS = 5;  // Giá vốn (Optionally for internal use)
    case SHIPPING_AUTO = 6;  // Phí ship tự động
    case BAD_DEBT = 7;       // Dự phòng nợ khó đòi

    public static function getOptions(): array
    {
        return [
            self::OPERATIONAL->value => 'Vận hành',
            self::MARKETING->value => 'MKT',
            self::FINANCIAL->value => 'Tài chính',
            self::OTHER->value => 'Khác',
            self::SHIPPING_AUTO->value => 'Phí ship (Tự động)',
            self::BAD_DEBT->value => 'Dự phòng nợ khó đòi',
        ];
    }

    public function getLabel(): string
    {
        return self::getOptions()[$this->value] ?? '';
    }
}
