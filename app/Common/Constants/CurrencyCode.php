<?php

namespace App\Common\Constants;

enum CurrencyCode: string
{
    case USD = 'USD';
    case VND = 'VND';
    case EUR = 'EUR';
    case GBP = 'GBP';
    case JPY = 'JPY';
    case CNY = 'CNY';
    case THB = 'THB';
    case SGD = 'SGD';
    case AUD = 'AUD';

    public function label(): string
    {
        return match ($this) {
            self::USD => __('enum.currency_code.usd'),
            self::VND => __('enum.currency_code.vnd'),
            self::EUR => __('enum.currency_code.eur'),
            self::GBP => __('enum.currency_code.gbp'),
            self::JPY => __('enum.currency_code.jpy'),
            self::CNY => __('enum.currency_code.cny'),
            self::THB => __('enum.currency_code.thb'),
            self::SGD => __('enum.currency_code.sgd'),
            self::AUD => __('enum.currency_code.aud'),
        };
    }

    public function displayLabel(): string
    {
        return "{$this->value} - {$this->label()}";
    }

    /**
     * @param  array<self>  $currencies
     * @return array<string, string>
     */
    public static function options(array $currencies): array
    {
        $options = [];

        foreach ($currencies as $currency) {
            $options[$currency->value] = $currency->displayLabel();
        }

        return $options;
    }
}
