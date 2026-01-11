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
        // return auth()->user()->can('create', FacilityRole::class);
    }

    /**
     * Validation rules
     */
    public function rules(): array
    {
        return [
            'name'           => 'required|string|max:255',
            'code'           => 'nullable|string|max:100|unique:facility_roles,code',
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
            'code.unique'        => 'This role code already exists.',
            'facility_id.exists' => 'Selected facility does not exist.',
        ];
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
