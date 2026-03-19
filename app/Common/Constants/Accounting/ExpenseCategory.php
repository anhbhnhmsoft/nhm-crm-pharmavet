<?php

namespace App\Common\Constants\Accounting;

enum ExpenseCategory: int
{
    case MARKETING = 1;      // MKT
    case OPERATIONAL = 2;    // Vận hành
    case FINANCIAL = 3;      // Tài chính
    case OTHER = 4;          // Khác
    case COST_OF_GOODS = 5;  // Giá vốn (Optionally for internal use)

    public static function getOptions(): array
    {
        return [
            self::OPERATIONAL->value => 'Vận hành',
            self::MARKETING->value => 'MKT',
            self::FINANCIAL->value => 'Tài chính',
            self::OTHER->value => 'Khác',
        ];
    }

    public function getLabel(): string
    {
        return self::getOptions()[$this->value] ?? '';
    }
}
