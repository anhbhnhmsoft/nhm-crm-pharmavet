<?php

namespace App\Http\Requests\Facebook;

use Illuminate\Foundation\Http\FormRequest;

class ConnectFacebookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'userAccessToken' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'userAccessToken.required' => __('common.error.required'),
        ];
    }
}
