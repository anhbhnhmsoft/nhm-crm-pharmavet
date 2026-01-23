<?php

namespace App\Common\Constants\Order;

enum GhnOrderStatus: string
{
    case READY_TO_PICK = 'ready_to_pick';
    case PICKING = 'picking';
    case DELIVERING = 'delivering';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::READY_TO_PICK => __('order.ghn_status.ready_to_pick'),
            self::PICKING => __('order.ghn_status.picking'),
            self::DELIVERING => __('order.ghn_status.delivering'),
            self::DELIVERED => __('order.ghn_status.delivered'),
            self::CANCELLED => __('order.ghn_status.cancelled'),
        };
    }

    public static function color(?string $state): string
    {
        return match ($state) {
            self::READY_TO_PICK->value => 'info',
            self::PICKING->value => 'warning',
            self::DELIVERING->value => 'primary',
            self::DELIVERED->value => 'success',
            self::CANCELLED->value => 'danger',
            default => 'gray',
        };
    }

    public static function toOptions(): array
    {
        return [
            self::READY_TO_PICK->value => self::READY_TO_PICK->label(),
            self::PICKING->value => self::PICKING->label(),
            self::DELIVERING->value => self::DELIVERING->label(),
            self::DELIVERED->value => self::DELIVERED->label(),
            self::CANCELLED->value => self::CANCELLED->label(),
        ];
    }
}


