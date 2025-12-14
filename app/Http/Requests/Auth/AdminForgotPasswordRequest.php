<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class AdminForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'email' => 'required|email|exists:users,email',
        ];

        if ($this->has('show_questions') && $this->get('show_questions')) {
            $rules['city'] = 'required|string|max:255';
            $rules['pet'] = 'required|string|max:255';
        }

        return $rules;
    }
}
