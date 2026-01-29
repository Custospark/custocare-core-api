<?php

namespace App\Http\Requests\FacilityRoles;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateFacilityRoleRequest extends FormRequest
{
    /**
     * Authorization check
     */
    public function authorize(): bool
    {
        // Example: allow only authenticated users
        // return auth()->check();
        return true;

        // Later you can replace with:
        // return auth()->user()->can('update', $this->route('facility_role'));
    }

    /**
     * Validation rules
     */
    public function rules(): array
    {
        $facilityRoleId = $this->route('facility_role') ? $this->route('facility_role')->id : null;

        return [
            'name' => 'sometimes|string|max:255',
            'code' => [
                'sometimes',
                'string',
                'max:100',
                function ($attribute, $value, $fail) use ($facilityRoleId) {
                    $facilityId = $this->input('facility_id', $this->route('facility_role')->facility_id ?? null);
                    
                    // Check unique combination of code + facility_id, excluding current record
                    $exists = \App\Models\FacilityRole::where('code', $value)
                        ->where('facility_id', $facilityId)
                        ->where('id', '!=', $facilityRoleId)
                        ->exists();
                        
                    if ($exists) {
                        $fail('This role code already exists for this facility.');
                    }
                }
            ],
            'description'    => 'nullable|string',
            'facility_id'    => 'sometimes|nullable|exists:facilities,id',
            'is_system_role' => 'sometimes|boolean',
        ];
    }

    /**
     * Custom validation messages
     */
    public function messages(): array
    {
        return [
            'name.string'        => 'Role name must be a string.',
            'name.max'           => 'Role name may not be greater than 255 characters.',
            'code.string'        => 'Role code must be a string.',
            'code.max'           => 'Role code may not be greater than 100 characters.',
            'facility_id.exists' => 'Selected facility does not exist.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Trim string inputs
        if ($this->has('name')) {
            $this->merge(['name' => trim($this->name)]);
        }
        
        if ($this->has('code')) {
            $this->merge(['code' => trim($this->code)]);
        }
        
        // Handle facility_id specifically for updates
        if ($this->has('facility_id') && $this->input('facility_id') === '') {
            $this->merge(['facility_id' => null]);
        }
    }

    /**
     * FAILED VALIDATION RESPONSE (422)
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422)
        );
    }

    /**
     * FAILED AUTHORIZATION RESPONSE (403)
     */
    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'You are not authorized to update this facility role.',
            ], 403)
        );
    }
}