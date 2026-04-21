<?php

namespace App\Common\Constants\Marketing;

enum FacebookConnectionStatus: int
{
    case PENDING = 1;
    case APPROVED = 2;
    case REJECTED = 3;
    case DISCONNECTED = 4;
    case EXPIRED = 5;

    public function label(): string
    {
        return match ($this) {
            self::PENDING => __('filament.integration.facebook.page_status.pending'),
            self::APPROVED => __('filament.integration.facebook.page_status.approved'),
            self::REJECTED => __('filament.integration.facebook.page_status.rejected'),
            self::DISCONNECTED => __('filament.integration.facebook.page_status.disconnected'),
            self::EXPIRED => __('filament.integration.facebook.page_status.expired'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::APPROVED => 'success',
            self::REJECTED => 'danger',
            self::DISCONNECTED => 'gray',
            self::EXPIRED => 'gray',
        };
    }
}
