<?php

namespace App\Utils;

class Helper
{
    /**
     * Generate SKU từ tên sản phẩm
     * Format: PRD-{PREFIX}-{TIMESTAMP}
     */
    public static function generateSKU(string $name): string
    {
        // Lấy 3 ký tự đầu của tên, bỏ dấu và viết hoa
        $prefix = strtoupper(substr(str($name)->slug('')->toString(), 0, 3));

        // Timestamp ngắn gọn (6 chữ số cuối)
        $timestamp = substr(time(), -6);

        // Random 2 số
        $random = str_pad(rand(0, 99), 2, '0', STR_PAD_LEFT);

        return "PRD-{$prefix}-{$timestamp}{$random}";
    }

    /**
     * Generate Barcode EAN-13 format (13 số)
     * Hoặc có thể dùng Code128
     */
    public static function generateBarcode(): string
    {
        // EAN-13: 12 số + 1 check digit
        // Format: {Country:3}{Manufacturer:4}{Product:5}{CheckDigit:1}

        $countryCode = '893'; // Việt Nam
        $manufacturer = str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
        $product = str_pad(rand(10000, 99999), 5, '0', STR_PAD_LEFT);

        $barcode = $countryCode . $manufacturer . $product;

        // Tính check digit theo chuẩn EAN-13
        $checkDigit = self::calculateEAN13CheckDigit($barcode);

        return $barcode . $checkDigit;
    }

    /**
     * Tính check digit cho EAN-13
     */
    public static function calculateEAN13CheckDigit(string $barcode): int
    {
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $digit = (int) $barcode[$i];
            $sum += ($i % 2 === 0) ? $digit : $digit * 3;
        }

        $checkDigit = (10 - ($sum % 10)) % 10;
        return $checkDigit;
    }
}
