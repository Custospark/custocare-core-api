<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\User::class);
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
            'title' => 'nullable|string|max:50',
            'display_name' => 'nullable|string|max:100',
            'dob' => 'nullable|date|before:today',
            'gender' => 'nullable|in:male,female,other',
            'address_line1' => 'nullable|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'password' => \App\Validation\PasswordRules::create(),
            'data_residency_region' => 'required|string|in:EU,US,APAC,MEA,SA',
            'allowed_processing_regions' => 'nullable|array',
            'allowed_processing_regions.*' => 'string|in:EU,US,APAC,MEA,SA',
            'created_from_facility_id' => 'nullable|integer|exists:facilities,id',
            'metadata' => 'nullable|array',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge(['email' => strtolower($this->email)]);
        }
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