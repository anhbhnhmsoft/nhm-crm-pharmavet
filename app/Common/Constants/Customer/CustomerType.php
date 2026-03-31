<?php

namespace App\Common\Constants\Customer;

enum CustomerType: int
{
    case NEW = 1; // Sổ mới
    case NEW_DUPLICATE = 2; // Sổ mới trùng
    case OLD_CUSTOMER = 3; // Sổ khách cũ
    case PARTNER_REQUEST = 4; // Yêu cầu từ đối tác

    public function label(): string
    {
        return match ($this) {
            self::NEW => __('filament.lead.customer.new'),
            self::NEW_DUPLICATE => __('filament.lead.customer.new_duplicate'),
            self::OLD_CUSTOMER => __('filament.lead.customer.old_customer'),
            self::PARTNER_REQUEST => __('filament.lead.customer.partner_request'),
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

    public static function colors($type): string
    {
        return match ($type) {
            self::NEW->value => 'primary',
            self::NEW_DUPLICATE->value => 'warning',
            self::OLD_CUSTOMER->value => 'danger',
            self::PARTNER_REQUEST->value => 'info',
        };
    }

    public static function getLabel($type): string
    {
        return match ($type) {
            self::NEW->value => __('filament.lead.customer.new'),
            self::NEW_DUPLICATE->value => __('filament.lead.customer.new_duplicate'),
            self::OLD_CUSTOMER->value => __('filament.lead.customer.old_customer'),
            self::PARTNER_REQUEST->value => __('filament.lead.customer.partner_request'),
        };
    }
}
