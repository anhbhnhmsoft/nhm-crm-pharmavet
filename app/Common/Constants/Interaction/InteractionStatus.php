<?php

namespace App\Common\Constants\Interaction;

enum InteractionStatus: int
{
    case FIRST_CALL = 1;
    case SECOND_CALL = 2;
    case THIRD_CALL = 3;
    case FOURTH_CALL = 4;
    case FIFTH_CALL = 5;
    case SIXTH_CALL = 6;
    case USER_MANUAL = 7;
    case SECOND_CARE = 8;
    case THIRD_CARE = 9;
    case PASS = 10;

    public static function label(int $type)
    {
        return match ($type) {
            self::FIRST_CALL->value => __('common.interaction_type.first_call'),
            self::SECOND_CALL->value => __('common.interaction_type.second_call'),
            self::THIRD_CALL->value => __('common.interaction_type.third_call'),
            self::FOURTH_CALL->value => __('common.interaction_type.fourth_call'),
            self::FIFTH_CALL->value => __('common.interaction_type.fifth_call'),
            self::SIXTH_CALL->value => __('common.interaction_type.sixth_call'),
            self::USER_MANUAL->value => __('common.interaction_type.user_manual'),
            self::SECOND_CARE->value => __('common.interaction_type.second_care'),
            self::THIRD_CARE->value => __('common.interaction_type.third_care'),
            self::PASS->value => __('common.interaction_type.pass'),
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
