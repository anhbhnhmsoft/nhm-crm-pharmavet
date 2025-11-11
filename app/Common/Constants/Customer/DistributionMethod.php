<?php

namespace App\Common\Constants\Customer;

enum DistributionMethod: int
{
    case BY_DEFINITION = 1; // Chia theo định mức
    case MOST_RECENT_RECIPIENT = 2; // chia theo người nhận số gần nhất

    public function label(): string
    {
        return match ($this) {
            self::BY_DEFINITION => __('filament.lead.distribution.by_definition'),
            self::MOST_RECENT_RECIPIENT => __('filament.lead.distribution.most_recent_repicient'),
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
