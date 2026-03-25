<?php

namespace App\Common\Constants\Marketing;

enum IntegrationType: int
{
    case FACEBOOK_ADS = 1;
    case WEBSITE = 3;
    case MANUAL_DATA = 4;

    public function label(): string
    {
        return match ($this) {
            self::MANUAL_DATA  => __('enum.integration_type.manual_data'),
            self::FACEBOOK_ADS => __('enum.integration_type.facebook_ads'),
            self::WEBSITE      => __('enum.integration_type.website'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::MANUAL_DATA => __('enum.integration_type.manual_data_desc'),
            self::FACEBOOK_ADS => __('enum.integration_type.facebook_ads_desc'),
            self::WEBSITE => __('enum.integration_type.website_desc'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::FACEBOOK_ADS => 'info',
            self::WEBSITE => 'success',
            self::MANUAL_DATA => 'danger',
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

    public static function getLabel(int $value): string
    {
        return self::tryFrom($value)?->label();
    }
}
