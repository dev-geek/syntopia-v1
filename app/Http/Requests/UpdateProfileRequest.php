<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'min:2', 'regex:/^[a-zA-Z\s]+$/', 'not_regex:/^\s+$/'],
            'password' => ['nullable', 'string', 'min:8', 'max:30', 'confirmed', 'regex:/^(?=.*[0-9])(?=.*[A-Z])(?=.*[a-z])(?=.*[,.<>{}~!@#$%^&_])[0-9A-Za-z,.<>{}~!@#$%^&_]{8,30}$/'],
            'password_confirmation' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Please enter your name.',
            'name.string' => 'Name must be a valid text.',
            'name.max' => 'Name cannot exceed 255 characters.',
            'name.min' => 'Name must be at least 2 characters long.',
            'name.regex' => 'Name can only contain letters and spaces.',
            'name.not_regex' => 'Name cannot contain only spaces.',
            'password.min' => 'Password must be at least 8 characters long.',
            'password.confirmed' => 'Password confirmation does not match.',
            'password.regex' => 'Password must contain at least one number, one uppercase letter, one lowercase letter, and one special character (,.<>{}~!@#$%^&_).',
            'password_confirmation.string' => 'Password confirmation must be a valid text.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'name',
            'password' => 'password',
            'password_confirmation' => 'password confirmation',
        ];
    }
}
