<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SubAdminRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'name'   => ['required', 'string', 'max:255'],
            'status' => ['boolean'],
        ];

        if ($this->isMethod('POST')) {
            $rules['email']    = ['required', 'string', 'email', 'max:255', 'unique:users'];
            $rules['password'] = ['required', 'string', 'min:6', 'confirmed'];
        }

        return $rules;
    }
}
