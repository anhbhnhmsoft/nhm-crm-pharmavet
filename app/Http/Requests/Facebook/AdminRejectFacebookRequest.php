<?php

namespace App\Http\Requests\Facebook;

use Illuminate\Foundation\Http\FormRequest;

class AdminRejectFacebookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'integration_id' => ['required', 'integer', 'exists:integrations,id'],
            'page_ids' => ['nullable', 'array'],
            'page_ids.*' => ['string'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'integration_id.required' => __('common.error.required'),
            'integration_id.exists' => __('messages.integration.error.not_found'),
        ];
    }
}
