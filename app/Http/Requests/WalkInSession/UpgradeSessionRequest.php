<?php

namespace App\Http\Requests\WalkInSession;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpgradeSessionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // TODO: Add proper authorization logic
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'facility_id' => ['required', 'integer', 'exists:facilities,id'],
            
            // minimum identity capture
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:200'],

            // patient clinical basics (optional but recommended)
            'date_of_birth' => ['nullable', 'date'],
            'biological_sex' => ['nullable', 'in:male,female,intersex,unknown'],
            'gender_identity' => ['nullable', 'string', 'max:50'],

            'country_code' => ['nullable', 'string', 'max:3'],
            'data_residency_region' => ['nullable', 'string', 'max:10'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'facility_id.required' => 'Facility ID is required.',
            'facility_id.exists' => 'The selected facility does not exist.',
            'email.email' => 'Please provide a valid email address.',
            'date_of_birth.date' => 'Please provide a valid date of birth.',
            'biological_sex.in' => 'Please select a valid biological sex.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'first_name' => 'first name',
            'last_name' => 'last name',
            'date_of_birth' => 'date of birth',
            'biological_sex' => 'biological sex',
            'gender_identity' => 'gender identity',
            'country_code' => 'country code',
            'data_residency_region' => 'data residency region',
        ];
    }
}