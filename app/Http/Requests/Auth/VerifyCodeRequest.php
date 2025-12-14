<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class VerifyCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'verification_code' => 'required|string|size:6',
            'email' => 'required|email',
        ];
    }

    public function messages(): array
    {
        return [
            'verification_code.required' => 'Verification code is required.',
            'verification_code.string' => 'Verification code must be a string.',
            'verification_code.size' => 'Verification code must be exactly 6 characters.',
            'email.required' => 'Email is required.',
            'email.email' => 'Email must be a valid email address.',
        ];
    }
}
