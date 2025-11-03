<?php

namespace App\Common\Constants;

enum CommonStatus: int
{
    case DISABLE = 0;
    case ABLE = 1;

    public static function getOptions(): array
    {
        return [
            self::DISABLE->value => __('enum.common_status.inactive'),
            self::ABLE->value => __('enum.common_status.active'),
        ];
    }

    public function getLabel(CommonStatus $state): array
    {
        return self::getOptions()[$state->value];
    }
}
