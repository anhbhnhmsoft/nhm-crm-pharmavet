<?php

namespace App\Common\Constants\Organization;

enum ProductField: int
{
/**
     * Quần áo
     */
    case FASHION = 1;
/**
     * Spa clinic
     */
    case SPA_CLINIC = 2;
/**
     * Thực phẩm detox
     */
    case DETOX_FOOD = 3;
/**
     * Bất động sản
     */
    case REAL_ESTATE = 4;
/**
     * Đào tạo offline
     */
    case OFFLINE_TRAINING = 5;
/**
     * Đồ dùng nhà bếp
     */
    case KITCHEN_ACCESSORY = 6;
/**
     * Thiết bị y tế
     */
    case MEDICAL_EQUIPMENT = 7;
/**
     * Materal xây dựng
     */
    case CONSTRUCTION_MATERIAL = 8;
/**
     * Bán hàng cá nhân
     */
    case PERSONAL_SALES = 9;
/**
     * Bán hàng tạp hóa
     */
    case RETAIL = 10;
/**
     * Phòng gym, thể hình
     */
    case GYM_FITNESS = 11;
/**
     * Nha khoa
     */
    case DENTISTRY = 12;
/**
     * Phong thủy
     */
    case FENG_SHUI = 13;
/**
     * Dịch vụ du lịch
     */
    case TRAVEL_SERVICE = 14;
/**
     * Mỹ phẩm
     */
    case COSMETICS = 15;
/**
     * Khóa học online
     */
    case ONLINE_COURSE = 16;
/**
     * Dược phẩm
     */
    case PHARMACEUTICAL = 17;
    case OTHER = 99;

    public function label(): string
    {
        return match ($this) {
            self::FASHION => __('enum.product_field.fashion'),
            self::SPA_CLINIC => __('enum.product_field.spa_clinic'),
            self::DETOX_FOOD => __('enum.product_field.detox_food'),
            self::REAL_ESTATE => __('enum.product_field.real_estate'),
            self::OFFLINE_TRAINING => __('enum.product_field.offline_training'),
            self::KITCHEN_ACCESSORY => __('enum.product_field.kitchen_accessory'),
            self::MEDICAL_EQUIPMENT => __('enum.product_field.medical_equipment'),
            self::CONSTRUCTION_MATERIAL => __('enum.product_field.construction_material'),
            self::PERSONAL_SALES => __('enum.product_field.personal_sales'),
            self::RETAIL => __('enum.product_field.retail'),
            self::GYM_FITNESS => __('enum.product_field.gym_fitness'),
            self::DENTISTRY => __('enum.product_field.dentistry'),
            self::FENG_SHUI => __('enum.product_field.feng_shui'),
            self::TRAVEL_SERVICE => __('enum.product_field.travel_service'),
            self::COSMETICS => __('enum.product_field.cosmetics'),
            self::ONLINE_COURSE => __('enum.product_field.online_course'),
            self::PHARMACEUTICAL => __('enum.product_field.pharmaceutical'),
            self::OTHER => __('enum.product_field.other'),
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

    public static function getLabel(int|string|null $value): string
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return __('common.unknown');
        }

        return self::tryFrom((int) $value)?->label() ?? __('common.unknown');
    }
}
