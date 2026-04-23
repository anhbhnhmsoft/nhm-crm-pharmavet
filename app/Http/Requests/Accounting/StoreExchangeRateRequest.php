<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

class StoreExchangeRateRequest extends FormRequest
{
    private const MAX_RATE = 999999999.999999;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rate_date' => ['required', 'date'],
            'from_currency' => ['required', 'string', 'size:3'],
            'to_currency' => ['required', 'string', 'size:3'],
            'rate' => ['required', 'numeric', 'min:0.000001', 'lte:' . self::MAX_RATE],
            'source' => ['nullable', 'string', 'in:manual,api'],
            'note' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'rate.required' => __('common.error.required'),
            'rate.lte' => __('accounting.exchange_rate.rate_max_error'),
            'rate.numeric' => __('accounting.exchange_rate.rate_numeric_error'),
            'rate.min' => __('accounting.exchange_rate.rate_positive_error'),
        ];
    }
}
