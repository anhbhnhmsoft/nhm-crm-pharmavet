<?php

namespace App\Common\Constants\Warehouse;

enum TypeTicket: int
{
    case IMPORT = 1;
    case EXPORT = 2;
    case TRANSFER = 3;
    case CANCEL_EXPORT = 4;

    public function getLabel(): string
    {
        return match ($this) {
            self::IMPORT => 'Nhập kho',
            self::EXPORT => 'Xuất kho',
            self::TRANSFER => 'Chuyển kho',
            self::CANCEL_EXPORT => 'Xuất hủy',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::IMPORT => 'success',
            self::EXPORT => 'warning',
            self::TRANSFER => 'info',
            self::CANCEL_EXPORT => 'danger',
        };
    }

    public static function toArray(): array
    {
        return [
            self::IMPORT->value => self::IMPORT->getLabel(),
            self::EXPORT->value => self::EXPORT->getLabel(),
            self::TRANSFER->value => self::TRANSFER->getLabel(),
            self::CANCEL_EXPORT->value => self::CANCEL_EXPORT->getLabel(),
        ];
    }
}
