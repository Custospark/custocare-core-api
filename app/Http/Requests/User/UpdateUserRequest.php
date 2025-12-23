<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        $user = $this->route('user') ?? User::find($this->route('id'));
        return $this->user()->can('update', $user);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->route('user')?->id ?? $this->route('id');

        return [
            'national_id_hash' => [
                'sometimes',
                'string',
                'max:128',
                Rule::unique('users', 'national_id_hash')->ignore($userId),
            ],
            'national_id_encrypted' => [
                'sometimes',
                'string',
                'max:512',
            ],
            'national_id_country_code' => [
                'sometimes',
                'string',
                'size:3',
            ],
            'identity_state' => [
                'sometimes',
                'string',
                Rule::in(['pending', 'verified', 'suspended', 'archived']),
            ],
            'identity_verification_method' => [
                'nullable',
                'string',
                'max:50',
            ],
            'identity_verified_by_staff_id' => [
                'nullable',
                'integer',
                'exists:staff,id',
            ],
            'data_residency_region' => [
                'sometimes',
                'string',
                'max:10',
            ],
            'allowed_processing_regions' => [
                'nullable',
                'array',
            ],
            'allowed_processing_regions.*' => [
                'string',
                'max:10',
            ],
            'created_from_facility_id' => [
                'nullable',
                'integer',
                'exists:facilities,id',
            ],
            'email_encrypted' => [
                'nullable',
                'string',
                'max:512',
            ],
            'email_hash' => [
                'nullable',
                'string',
                'max:128',
                Rule::unique('users', 'email_hash')->ignore($userId),
            ],
            'phone_encrypted' => [
                'nullable',
                'string',
                'max:512',
            ],
            'phone_hash' => [
                'nullable',
                'string',
                'max:128',
                Rule::unique('users', 'phone_hash')->ignore($userId),
            ],
            'password_hash' => [
                'nullable',
                'string',
                'max:255',
            ],
            'requires_password_change' => [
                'boolean',
            ],
            'mfa_enabled' => [
                'boolean',
            ],
            'mfa_secret_encrypted' => [
                'nullable',
                'string',
                'max:512',
            ],
            'metadata' => [
                'nullable',
                'array',
            ],
            'updated_by_staff_id' => [
                'nullable',
                'integer',
                'exists:staff,id',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'national_id_hash.unique' => 'A user with this national ID already exists.',
            'national_id_country_code.size' => 'Country code must be exactly 3 characters.',
            'identity_state.in' => 'Invalid identity state.',
            'email_hash.unique' => 'This email is already registered.',
            'phone_hash.unique' => 'This phone number is already registered.',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('allowed_processing_regions') && is_string($this->allowed_processing_regions)) {
            $this->merge([
                'allowed_processing_regions' => json_decode($this->allowed_processing_regions, true),
            ]);
        }

        if ($this->has('metadata') && is_string($this->metadata)) {
            $this->merge([
                'metadata' => json_decode($this->metadata, true),
            ]);
        }

        // Convert boolean strings to actual booleans
        if ($this->has('requires_password_change')) {
            $this->merge([
                'requires_password_change' => filter_var($this->requires_password_change, FILTER_VALIDATE_BOOLEAN),
            ]);
        }

        if ($this->has('mfa_enabled')) {
            $this->merge([
                'mfa_enabled' => filter_var($this->mfa_enabled, FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }
}