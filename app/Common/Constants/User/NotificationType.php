<?php

namespace App\Common\Constants\User;

enum NotificationType: int
{
    case DEBT_REMINDER = 1;
    case SYSTEM = 2;
    case ORDER_UPDATE = 3;

    public function label(): string
    {
        return match ($this) {
            self::DEBT_REMINDER => __('enum.notification_type.debt_reminder'),
            self::SYSTEM => __('enum.notification_type.system'),
            self::ORDER_UPDATE => __('enum.notification_type.order_update'),
        };
    }
}
