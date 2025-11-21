<?php

namespace App\Common\Constants\Marketing;

enum IntegrationTokenType: int
{
    case PAGE_ACCESS_TOKEN = 1;
    case USER_LONG_LIVED_TOKEN = 2;

    public function label(): string
    {
        return match ($this) {
            self::PAGE_ACCESS_TOKEN => __('integration.token.page_access'),
            self::USER_LONG_LIVED_TOKEN => __('integration.token.user_long_lived'),
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
