<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email',
            'password' => [
                'required',
                'string',
                'min:8',
                'max:30',
                'confirmed',
                'regex:/^(?=.*[0-9])(?=.*[A-Z])(?=.*[a-z])(?=.*[,.<>{}~!@#$%^&_])[0-9A-Za-z,.<>{}~!@#$%^&_]{8,30}$/'
            ],
            'password_confirmation' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Email address is required.',
            'email.email' => 'Please enter a valid email address.',
            'password.required' => 'Password is required.',
            'password.string' => 'Password must be a valid string.',
            'password.min' => 'Password must be at least 8 characters long.',
            'password.max' => 'Password cannot exceed 30 characters.',
            'password.confirmed' => 'Password confirmation does not match.',
            'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character (,.<>{}~!@#$%^&_).',
            'password_confirmation.required' => 'Password confirmation is required.',
            'password_confirmation.string' => 'Password confirmation must be a valid string.',
        ];
    }

    public function attributes(): array
    {
        return [
            'email' => 'email address',
            'password' => 'password',
            'password_confirmation' => 'password confirmation',
        ];
    }
}
