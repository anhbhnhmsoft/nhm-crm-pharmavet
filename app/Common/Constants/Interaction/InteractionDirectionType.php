<?php

namespace App\Common\Constants\Interaction;

enum InteractionDirectionType: int
{
    case INBOUND = 1;
    case OUTBOUND = 2;

    public static function label($type): string
    {
        return match ($type) {
            self::INBOUND->value => __('common.interaction_direction.inbound'),
            self::OUTBOUND->value => __('common.interaction_direction.outbound'),
        };
    }

    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label($case->value);
        }
        return $options;
    }
}