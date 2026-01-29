<?php

namespace App\Http\Requests\FacilityRoles;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreFacilityRoleRequest extends FormRequest
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
        // return auth()->user()->can('create', \App\Models\FacilityRole::class);
    }

    /**
     * Validation rules
     */
    public function rules(): array
    {
        return [
            'name'           => 'required|string|max:255',
            'code'           => [
                'required',
                'string',
                'max:100',
                function ($attribute, $value, $fail) {
                    $facilityId = $this->input('facility_id');
                    
                    // Check unique combination of code + facility_id
                    $exists = \App\Models\FacilityRole::where('code', $value)
                        ->where('facility_id', $facilityId)
                        ->exists();
                        
                    if ($exists) {
                        $fail('This role code already exists for the in your facility.');
                    }
                }
            ],
            'description'    => 'nullable|string',
            'facility_id'    => 'nullable|exists:facilities,id',
            'is_system_role' => 'sometimes|boolean',
        ];
    }

    /**
     * Custom validation messages
     */
    public function messages(): array
    {
        return [
            'name.required'      => 'Role name is required.',
            'code.required'      => 'Role code is required.',
            'code.max'           => 'Role code may not be greater than 100 characters.',
            'facility_id.exists' => 'Selected facility does not exist.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // If facility_id is not provided, set it to null for system roles
        if (!$this->has('facility_id')) {
            $this->merge([
                'facility_id' => null,
            ]);
        }
        
        // Trim string inputs
        $this->merge([
            'name' => trim($this->name),
            'code' => trim($this->code),
        ]);
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
                'message' => 'You are not authorized to create facility roles.',
            ], 403)
        );
    }
}