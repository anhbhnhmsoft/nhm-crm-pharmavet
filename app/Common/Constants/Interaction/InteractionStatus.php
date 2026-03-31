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
    case UN_CARE = 11;
    case INEFFICIENT = 12;
    case RECEIVED = 13;
    case COMPLETED = 14;
    case MISSED = 15;
    case FAILED = 16;

    public static function label(int $type)
    {
        return match ($type) {
            self::FIRST_CALL->value => __('enum.interaction_type.first_call'),
            self::SECOND_CALL->value => __('enum.interaction_type.second_call'),
            self::THIRD_CALL->value => __('enum.interaction_type.third_call'),
            self::FOURTH_CALL->value => __('enum.interaction_type.fourth_call'),
            self::FIFTH_CALL->value => __('enum.interaction_type.fifth_call'),
            self::SIXTH_CALL->value => __('enum.interaction_type.sixth_call'),
            self::USER_MANUAL->value => __('enum.interaction_type.user_manual'),
            self::SECOND_CARE->value => __('enum.interaction_type.second_care'),
            self::THIRD_CARE->value => __('enum.interaction_type.third_care'),
            self::PASS->value => __('enum.interaction_type.pass'),
            self::UN_CARE->value => __('enum.interaction_type.un_care'),
            self::INEFFICIENT->value => __('enum.interaction_type.inefficient'),
            self::RECEIVED->value => __('enum.interaction_type.received'),
            self::COMPLETED->value => __('enum.interaction_type.completed'),
            self::MISSED->value => __('enum.interaction_type.missed'),
            self::FAILED->value => __('enum.interaction_type.failed'),
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

    public static function getLabel(int $type): string
    {
        return match ($type) {
            self::FIRST_CALL->value => __('enum.interaction_type.first_call'),
            self::SECOND_CALL->value => __('enum.interaction_type.second_call'),
            self::THIRD_CALL->value => __('enum.interaction_type.third_call'),
            self::FOURTH_CALL->value => __('enum.interaction_type.fourth_call'),
            self::FIFTH_CALL->value => __('enum.interaction_type.fifth_call'),
            self::SIXTH_CALL->value => __('enum.interaction_type.sixth_call'),
            self::USER_MANUAL->value => __('enum.interaction_type.user_manual'),
            self::SECOND_CARE->value => __('enum.interaction_type.second_care'),
            self::THIRD_CARE->value => __('enum.interaction_type.third_care'),
            self::PASS->value => __('enum.interaction_type.pass'),
            self::UN_CARE->value => __('enum.interaction_type.un_care'),
            self::INEFFICIENT->value => __('enum.interaction_type.inefficient'),
            self::RECEIVED->value => __('enum.interaction_type.received'),
            self::COMPLETED->value => __('enum.interaction_type.completed'),
            self::MISSED->value => __('enum.interaction_type.missed'),
            self::FAILED->value => __('enum.interaction_type.failed'),
        };
    }

    public static function getLabelStatus(int $type): string
    {
        return match ($type) {
            self::FIRST_CALL->value => __('enum.interaction_type.first_call_label'),
            self::SECOND_CALL->value => __('enum.interaction_type.second_call_label'),
            self::THIRD_CALL->value => __('enum.interaction_type.third_call_label'),
            self::FOURTH_CALL->value => __('enum.interaction_type.fourth_call_label'),
            self::FIFTH_CALL->value => __('enum.interaction_type.fifth_call_label'),
            self::SIXTH_CALL->value => __('enum.interaction_type.sixth_call_label'),
            self::USER_MANUAL->value => __('enum.interaction_type.user_manual_label'),
            self::SECOND_CARE->value => __('enum.interaction_type.second_care_label'),
            self::THIRD_CARE->value => __('enum.interaction_type.third_care_label'),
            self::PASS->value => __('enum.interaction_type.pass_label'),
            self::UN_CARE->value => __('enum.interaction_type.un_care_label'),
            self::RECEIVED->value => __('enum.interaction_type.received'),
            self::COMPLETED->value => __('enum.interaction_type.completed_label'),
            self::MISSED->value => __('enum.interaction_type.missed_label'),
            self::FAILED->value => __('enum.interaction_type.failed_label'),
        };
     }

    public static function getStyle(?int $type): string
    {
        return match ($type) {
            self::COMPLETED->value => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            self::MISSED->value => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
            self::FAILED->value => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
            default => 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
        };
    }
 }
