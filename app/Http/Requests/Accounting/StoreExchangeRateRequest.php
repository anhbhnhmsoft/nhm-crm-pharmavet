<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

class StoreExchangeRateRequest extends FormRequest
{
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
            'rate' => ['required', 'numeric', 'min:0'],
            'source' => ['nullable', 'string', 'in:manual,api'],
            'note' => ['nullable', 'string'],
        ];
    }
}
