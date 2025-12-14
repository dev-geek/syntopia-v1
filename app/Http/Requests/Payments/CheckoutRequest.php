<?php

namespace App\Http\Requests\Payments;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'is_upgrade' => 'nullable|boolean',
            'is_downgrade' => 'nullable|boolean',
            'fp_tid' => 'nullable|string',
        ];
    }
}
