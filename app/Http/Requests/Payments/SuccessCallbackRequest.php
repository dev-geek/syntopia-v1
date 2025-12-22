<?php

namespace App\Http\Requests\Payments;

use Illuminate\Foundation\Http\FormRequest;

class SuccessCallbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'gateway' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'gateway.required' => 'Gateway is required',
        ];
    }
}
