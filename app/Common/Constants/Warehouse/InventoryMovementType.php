<?php

namespace App\Common\Constants\Warehouse;

enum InventoryMovementType: string
{
    case IN = 'in';
    case OUT = 'out';
    case TRANSFER_IN = 'transfer_in';
    case TRANSFER_OUT = 'transfer_out';
    case RESERVE = 'reserve';
    case RELEASE = 'release';
    case CONSUME = 'consume';
    case RETURN_IN = 'return_in';

    public static function importValues(): array
    {
        return [
            self::IN->value,
            self::TRANSFER_IN->value,
            self::RETURN_IN->value,
        ];
    }

    public static function exportValues(): array
    {
        return [
            self::OUT->value,
            self::TRANSFER_OUT->value,
            self::CONSUME->value,
        ];
    }
}
