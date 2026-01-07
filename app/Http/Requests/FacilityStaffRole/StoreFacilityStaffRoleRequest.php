<?php

namespace App\Http\Requests\FacilityStaffRole;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

class StoreFacilityStaffRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // In a real application, you would check permissions
        // return $this->user()->can('create', FacilityStaffRole::class);
        
        // For now, allow all authenticated users
        // return $this->user() !== null;
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
            'facility_id' => 'nullable|integer|exists:facilities,id',
            'staff_id' => 'nullable|integer|exists:staff,id',
            'role_code' => 'required|string',
            'department_ids' => 'nullable|array',
            'department_ids.*' => 'integer',
            'is_primary_facility' => 'boolean',
            'privileges_bitmask' => 'nullable|array',
            'privileges_bitmask.*' => 'string',
            'accessible_patient_populations' => 'nullable|array',
            'accessible_patient_populations.*' => 'string',
            'prescribing_authority_at_facility' => 'nullable|array',
            'prescribing_authority_at_facility.*' => 'string',
            'shift_schedule' => 'nullable|array',
            'shift_type' => 'nullable|string|in:day,night,rotating,on_call,flexible',
            'hours_per_week' => 'nullable|integer|min:1|max:168',
            'effective_from' => 'required|date|after_or_equal:today',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'assignment_status' => 'nullable|string|in:active,on_leave,suspended,terminated',
            'credentialing_completed_at' => 'nullable|date',
            'credentialed_by_staff_id' => 'nullable|integer|exists:staff,id',
            'privileging_approved_at' => 'nullable|date',
            'next_reappointment_date' => 'nullable|date',
            'facility_satisfaction_score' => 'nullable|numeric|min:0|max:5',
            'created_by_staff_id' => 'nullable|integer|exists:staff,id',
            'metadata' => 'nullable|array'
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'facility_id.required' => 'Facility ID is required',
            'facility_id.exists' => 'The selected facility does not exist',
            'staff_id.required' => 'Staff ID is required',
            'staff_id.exists' => 'The selected staff member does not exist',
            'role_code.required' => 'Role code is required',
            'role_code.in' => 'Invalid role code selected',
            'effective_from.required' => 'Effective from date is required',
            'effective_from.after_or_equal' => 'Effective from date must be today or a future date',
            'effective_to.after_or_equal' => 'Effective to date must be after or equal to effective from date',
            'hours_per_week.min' => 'Hours per week must be at least 1',
            'hours_per_week.max' => 'Hours per week cannot exceed 168',
            'facility_satisfaction_score.min' => 'Satisfaction score must be at least 0',
            'facility_satisfaction_score.max' => 'Satisfaction score cannot exceed 5',
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param Validator $validator
     * @return void
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY)
        );
    }

    /**
     * Handle a failed authorization attempt.
     *
     * @return void
     */
    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'You are not authorized to create role assignments'
            ], JsonResponse::HTTP_FORBIDDEN)
        );
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        // Set created_by_staff_id from authenticated user if not provided
        if (!$this->has('created_by_staff_id') && $this->user()) {
            $this->merge([
                'created_by_staff_id' => $this->user()->id
            ]);
        }

        // Ensure boolean fields are properly cast
        if ($this->has('is_primary_facility')) {
            $this->merge([
                'is_primary_facility' => filter_var($this->is_primary_facility, FILTER_VALIDATE_BOOLEAN)
            ]);
        }

        // Ensure array fields are properly formatted
        $arrayFields = [
            'department_ids',
            'privileges_bitmask',
            'accessible_patient_populations',
            'prescribing_authority_at_facility',
            'shift_schedule',
            'metadata'
        ];

        foreach ($arrayFields as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                try {
                    $decoded = json_decode($this->input($field), true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $this->merge([$field => $decoded]);
                    }
                } catch (\Exception $e) {
                    // Keep as is if JSON decoding fails
                }
            }
        }
    }
}