<?php

namespace App\Common\Constants\Marketing;

enum IntegrationType: int
{
    case FACEBOOK_ADS = 1;
    case LANDING_PAGE = 2;
    case WEBSITE = 3;

    public function label(): string
    {
        return match ($this) {
            self::FACEBOOK_ADS => __('filament.enum.type.facebook_ads'),
            self::LANDING_PAGE => __('filament.enum.type.landing_page'),
            self::WEBSITE => __('filament.enum.type.website'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::FACEBOOK_ADS => __('filament.enum.type.facebook_ads_desc'),
            self::LANDING_PAGE => __('filament.enum.type.landing_page_desc'),
            self::WEBSITE => __('filament.enum.type.website_desc'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::FACEBOOK_ADS => 'info',
            self::LANDING_PAGE => 'warning',
            self::WEBSITE => 'success',
        };
    }

    public function requiresFacebookAuth(): bool
    {
        return $this === self::FACEBOOK_ADS;
    }

    public function requiresWebhook(): bool
    {
        return in_array($this, [self::LANDING_PAGE, self::WEBSITE]);
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
