<?php

namespace App\Utils;

use Carbon\Carbon;
use Filament\Notifications\Notification;

class DateRangeGuard
{
    protected static array $sentNotifications = [];

    public static function hasInvalidRange(mixed $from, mixed $to): bool
    {
        if (blank($from) || blank($to)) {
            return false;
        }

        try {
            return Carbon::parse((string) $from)->gt(Carbon::parse((string) $to));
        } catch (\Throwable) {
            return false;
        }
    }

    public static function notifyInvalidRange(string $key, string $message): void
    {
        if (isset(self::$sentNotifications[$key])) {
            return;
        }

        self::$sentNotifications[$key] = true;

        Notification::make()
            ->danger()
            ->title($message)
            ->send();
    }
}
