<?php

namespace App\Common\Constants;

enum StatusConnect: int
{
    case CONNECTED = 1;
    case PENDING   = 2;
    case ERROR     = 3;

    public function label(): string
    {
        return match ($this) {
            self::CONNECTED => __('filament.lead.distribution.by_definition'),
            self::PENDING => __('filament.lead.distribution.most_recent_repicient'),
            self::ERROR => __('filament.lead.distribution.most_recent_repicient'),
        };
    }

    public static function toOptions(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }
        return $options;
    }
}
