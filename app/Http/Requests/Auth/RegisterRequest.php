<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'national_id' => [
                'required',
                'string',
                'max:50',
                Rule::unique('users', 'national_id_hash')->whereNull('deleted_at')
            ],
            'national_id_country_code' => 'required|string|size:3',
            'email' => 'required|email|unique:users,email_hash',
            'phone' => 'nullable|string|max:20',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'password' => 'required|string|min:8|confirmed',
            'data_residency_region' => 'required|string|in:EU,US,APAC,MEA,SA',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['email' => strtolower($this->email)]);
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'national_id.unique' => 'This national ID is already registered.',
            'email.unique' => 'This email is already registered.',
        ];
    }
}