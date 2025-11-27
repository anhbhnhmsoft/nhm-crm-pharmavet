<?php

namespace App\Common\Constants\Customer;

enum ReasonInteraction: int
{
    case CLOSING_ORDER = 1;
    case NO_ANSWER = 2;
    case BUSY = 3;
    case CALL_BACK = 4;
    case SUBSCRIBERS = 5;
    case THINK_MORE = 6;
    case NO_NEED = 7;
    case GOOD_PERFORMANCE = 8;
    case POOR_PERFORMANCE = 9;
    
    public static function label($value): string
    {
        return match ($value) {
            self::CLOSING_ORDER => __('telesale.reason_interaction.closing_order'),
            self::NO_ANSWER => __('telesale.reason_interaction.no_answer'),
            self::BUSY => __('telesale.reason_interaction.busy'),
            self::CALL_BACK => __('telesale.reason_interaction.call_back'),
            self::SUBSCRIBERS => __('telesale.reason_interaction.subscribers'),
            self::THINK_MORE => __('telesale.reason_interaction.think_more'),
            self::NO_NEED => __('telesale.reason_interaction.no_need'),
            self::GOOD_PERFORMANCE => __('telesale.reason_interaction.good_performance'),
            self::POOR_PERFORMANCE => __('telesale.reason_interaction.poor_performance'),
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