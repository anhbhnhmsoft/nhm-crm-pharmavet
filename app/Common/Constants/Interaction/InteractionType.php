<?php

namespace App\Common\Constants\Interaction;

enum InteractionType: int
{
    case CALL = 1;
    case SMS = 2;
    case EMAIL = 3;
    case NOTE = 4;
    case MEETING = 5;

    public static function label(int $type){
        return match ($type) {
            self::CALL->value => __('enum.interaction_type.call'),
            self::SMS->value => __('enum.interaction_type.sms'),
            self::EMAIL->value => __('enum.interaction_type.email'),
            self::NOTE->value => __('enum.interaction_type.note'),
            self::MEETING->value => __('enum.interaction_type.meeting'),
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
            self::CALL->value => __('enum.interaction_type.call'),
            self::SMS->value => __('enum.interaction_type.sms'),
            self::EMAIL->value => __('enum.interaction_type.email'),
            self::NOTE->value => __('enum.interaction_type.note'),
            self::MEETING->value => __('enum.interaction_type.meeting'),
        };
    }

    public static function getLabelStatus(int $type): string
    {
        return match ($type) {
            self::CALL->value => __('enum.interaction_type.call'),
            self::SMS->value => __('enum.interaction_type.sms'),
            self::EMAIL->value => __('enum.interaction_type.email'),
            self::NOTE->value => __('enum.interaction_type.note'),
            self::MEETING->value => __('enum.interaction_type.meeting'),
        };
    }

    public function getIcon(): array
    {
        return match ($this) {
            self::CALL => [
                'bg' => 'bg-blue-100 dark:bg-blue-900',
                'text' => 'text-blue-600 dark:text-blue-400',
                'path' => 'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z',
            ],
            self::SMS => [
                'bg' => 'bg-green-100 dark:bg-green-900',
                'text' => 'text-green-600 dark:text-green-400',
                'path' => 'M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z',
            ],
            self::EMAIL => [
                'bg' => 'bg-purple-100 dark:bg-purple-900',
                'text' => 'text-purple-600 dark:text-purple-400',
                'path' => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
            ],
            self::NOTE => [
                'bg' => 'bg-yellow-100 dark:bg-yellow-900',
                'text' => 'text-yellow-600 dark:text-yellow-400',
                'path' => 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
            ],
            default => [
                'bg' => 'bg-gray-100 dark:bg-gray-700',
                'text' => 'text-gray-600 dark:text-gray-400',
                'path' => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
            ],
        };
    }
}
