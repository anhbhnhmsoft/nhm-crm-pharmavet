<?php

namespace App\Common\Constants\Product;

use Filament\Support\Contracts\HasLabel;

enum TypeVAT: int implements HasLabel
{
    case NO_VAT     = 1;           // Không chịu VAT (Không nằm trong diện chịu thuế)
    case INCLUSIVE  = 2;           // Giá đã bao gồm VAT (Dùng cho tính toán)
    case EXCLUSIVE  = 3;           // Giá chưa bao gồm VAT (Dùng cho tính toán)
    case STANDARD   = 4;           // VAT tiêu chuẩn 10%
    case REDUCED    = 5;           // VAT giảm 5%
    case ZERO_RATED = 6;           // VAT 0% (Hàng xuất khẩu)
    case EIGHT_PERCENT = 7;        // VAT giảm 8% (Mức đặc biệt, thường áp dụng tạm thời)

    /**
     * Lấy label tiếng Việt
     */
    public function label(): string
    {
        return match ($this) {
            self::NO_VAT        => __('filament.vat.no'),
            self::INCLUSIVE     => __('filament.vat.inclusive'),
            self::EXCLUSIVE     => __('filament.vat.exclusive'),
            self::STANDARD      => __('filament.vat.standard'),
            self::REDUCED       => __('filament.vat.reduced'),
            self::ZERO_RATED    => __('filament.vat.zero_rate'),
            self::EIGHT_PERCENT => __('filament.vat.eight_percent')
        };
    }

    /**
     * Lấy mô tả chi tiết
     */
    public function description(): string
    {
        return match ($this) {
            self::NO_VAT        => __('filament.vat.desc.no'),
            self::INCLUSIVE     => __('filament.vat.desc.inclusive'),
            self::EXCLUSIVE     => __('filament.vat.desc.exclusive'),
            self::STANDARD      => __('filament.vat.desc.standard'),
            self::REDUCED       => __('filament.vat.desc.reduced'),
            self::ZERO_RATED    => __('filament.vat.desc.zero_rate'),
            self::EIGHT_PERCENT => __('filament.vat.desc.eight_percent')
        };
    }

    /**
     * Tỷ lệ VAT mặc định tương ứng
     */
    public function defaultRate(): float
    {
        return match ($this) {
            self::STANDARD => 10.0,
            self::REDUCED => 5.0,
            self::ZERO_RATED => 0.0,
            self::EIGHT_PERCENT => 8.0,
            // Các loại INCLUSIVE, EXCLUSIVE, NO_VAT không có tỷ lệ cố định, mặc định 0% hoặc dùng trường nhập liệu
            default => 0.0,
        };
    }

    /**
     * Tính toán chi tiết giá (Base Price, VAT Amount, Final Price)
     * Base Price là giá trước VAT, Final Price là giá sau VAT
     */
    public function calculateFinalPrice(float $basePrice, float $vatRate): array
    {
        return match ($this) {
            self::NO_VAT, self::ZERO_RATED => [
                'base_price' => $basePrice,
                'vat_amount' => 0,
                'final_price' => $basePrice,
            ],
            self::INCLUSIVE => [
                'base_price' => $basePrice / (1 + $vatRate / 100),
                'vat_amount' => $basePrice - ($basePrice / (1 + $vatRate / 100)),
                'final_price' => $basePrice,
            ],
            self::EXCLUSIVE, self::STANDARD, self::REDUCED, self::EIGHT_PERCENT => [
                'base_price' => $basePrice,
                'vat_amount' => $basePrice * ($vatRate / 100),
                'final_price' => $basePrice * (1 + $vatRate / 100),
            ],
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

    public static function toOptionsWithDescription(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = [
                'label'        => $case->label(),
                'description'  => $case->description(),
            ];
        }
        return $options;
    }

    public function getLabel(): ?string
    {
        return $this->label();
    }
}
