<?php

namespace App\Common\Constants;

enum StatusProgress: int
{
    case IN_PROGRESS = 1; // Đang xử lý
    case PENDING = 2; // Đang chờ
    case COMPLETED = 3; // Đã hoàn thành
    case FAILED = 4; // Thất bại
    case CANCELLED = 5; // Đã hủy
    case CONFIRMED = 6; // Đã xác nhận
    case DELIVERED = 8; // Đã giao hàng
    case RETURNED = 9; // Đã trả lại
    case DELIVERED_AGAIN = 10; // Giao lại

    public static function label(int $value): string
    {
        return match ($value) {
            self::IN_PROGRESS => __('common.status_progress.in_progress'),
            self::PENDING => __('common.status_progress.pending'),
            self::COMPLETED => __('common.status_progress.completed'),
            self::FAILED => __('common.status_progress.failed'),
            self::CANCELLED => __('common.status_progress.cancelled'),
            self::CONFIRMED => __('common.status_progress.confirmed'),
            self::DELIVERED => __('common.status_progress.delivered'),
            self::RETURNED => __('common.status_progress.returned'),
            self::DELIVERED_AGAIN => __('common.status_progress.delivered_again'),
            default => __('common.status_progress.unknown'),
        };
    }

    public static function options(): array
    {
        return [
            self::IN_PROGRESS => __('common.status_progress.in_progress'),
            self::PENDING => __('common.status_progress.pending'),
            self::COMPLETED => __('common.status_progress.completed'),
            self::FAILED => __('common.status_progress.failed'),
            self::CANCELLED => __('common.status_progress.cancelled'),
            self::CONFIRMED => __('common.status_progress.confirmed'),
            self::DELIVERED => __('common.status_progress.delivered'),
            self::RETURNED => __('common.status_progress.returned'),
            self::DELIVERED_AGAIN => __('common.status_progress.delivered_again'),
        ];
    }
}
