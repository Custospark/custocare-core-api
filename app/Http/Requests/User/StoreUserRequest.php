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
            'national_id_hash' => [
                'required',
                'string',
                'max:128',
                'unique:users,national_id_hash',
            ],
            'national_id_encrypted' => [
                'required',
                'string',
                'max:512',
            ],
            'national_id_country_code' => [
                'required',
                'string',
                'size:3',
            ],
            'data_residency_region' => [
                'required',
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
                'unique:users,email_hash',
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
                'unique:users,phone_hash',
            ],
            'metadata' => [
                'nullable',
                'array',
            ],
            'created_by_staff_id' => [
                'nullable',
                'integer',
                'exists:staff,id',
            ],
            'created_ip' => [
                'nullable',
                'ip',
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
    }
}