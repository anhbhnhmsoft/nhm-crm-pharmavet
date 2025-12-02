<?php

namespace App\Common\Constants\Warehouse;

enum StatusTicket: int
{
    case DRAFT = 1;
    case COMPLETED = 2;
    case CANCELLED = 3;

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => 'Phiếu tạm',
            self::COMPLETED => 'Hoàn thành',
            self::CANCELLED => 'Đã hủy',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::COMPLETED => 'success',
            self::CANCELLED => 'danger',
        };
    }

    public static function toArray(): array
    {
        return [
            self::DRAFT->value => self::DRAFT->getLabel(),
            self::COMPLETED->value => self::COMPLETED->getLabel(),
            self::CANCELLED->value => self::CANCELLED->getLabel(),
        ];
    }
}
