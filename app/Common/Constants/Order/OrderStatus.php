<?php

namespace App\Common\Constants\Order;

enum OrderStatus: int
{
    case PENDING = 1; // Lưu đơn (Draft)
    case CONFIRMED = 2; // Chốt đơn (Waiting for shipping)
    case SHIPPING = 3; // Đã đăng đơn (Posted)
    case COMPLETED = 4; // Hoàn thành
    case CANCELLED = 5; // Hủy

    public function label(): string
    {
        return match ($this) {
            self::PENDING => __('order.status.pending'),
            self::CONFIRMED => __('order.status.confirmed'),
            self::SHIPPING => __('order.status.shipping'),
            self::COMPLETED => __('order.status.completed'),
            self::CANCELLED => __('order.status.cancelled'),
        };
    }

    public static function color(int $state): string
    {
        return match ($state) {
            self::PENDING->value => 'gray',
            self::CONFIRMED->value => 'warning',
            self::SHIPPING->value => 'info',
            self::COMPLETED->value => 'success',
            self::CANCELLED->value => 'danger',
        };
    }

    public static function getLabel(int $status): string
    {
        return match ($status) {
            self::PENDING->value => __('order.status.pending'),
            self::CONFIRMED->value => __('order.status.confirmed'),
            self::SHIPPING->value => __('order.status.shipping'),
            self::COMPLETED->value => __('order.status.completed'),
            self::CANCELLED->value => __('order.status.cancelled'),
        };
    }

    public static function toOptions(): array
    {
        return [
            self::PENDING->value => __('order.status.pending'),
            self::CONFIRMED->value => __('order.status.confirmed'),
            self::SHIPPING->value => __('order.status.shipping'),
            self::COMPLETED->value => __('order.status.completed'),
            self::CANCELLED->value => __('order.status.cancelled'),
        ];
    }
}
