<?php

namespace App\Http\Requests\Department;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreDepartmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // Authorization is handled by Policy in Controller
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
            'facility_id' => 'required|integer|exists:facilities,id',
            'department_code' => 'nullable|string|max:50',
            'department_name' => 'required|string|max:200',
            'department_type' => [
                'required',
                'string',
                'in:emergency,intensive_care,surgery,outpatient,inpatient,radiology,laboratory,pharmacy,physical_therapy,cardiology,oncology,pediatrics,obstetrics,psychiatry,administration,support_services'
            ],
            'parent_department_id' => 'nullable|integer|exists:departments,id',
            'department_head_staff_id' => 'nullable|integer|exists:staff,id',
            'bed_count' => 'nullable|integer|min:0',
            'treatment_room_count' => 'nullable|integer|min:0',
            'max_concurrent_capacity' => 'nullable|integer|min:1|max:1000',
            'building' => 'nullable|string|max:100',
            'floor' => 'nullable|string|max:20',
            'wing_section' => 'nullable|string|max:50',
            'operating_hours' => 'nullable|json',
            'accepts_walk_ins' => 'boolean',
            'requires_appointment' => 'boolean',
            'average_wait_time_minutes' => 'nullable|integer|min:0|max:1440', // 24 hours in minutes
            'status' => 'nullable|string|in:active,inactive,temporarily_closed',
            'metadata' => 'nullable|json',
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
            'facility_id.required' => 'The facility is required.',
            'facility_id.exists' => 'The selected facility does not exist.',
            'department_code.required' => 'The department code is required.',
            'department_code.max' => 'The department code may not be greater than 50 characters.',
            'department_name.required' => 'The department name is required.',
            'department_name.max' => 'The department name may not be greater than 200 characters.',
            'department_type.required' => 'The department type is required.',
            'department_type.in' => 'The selected department type is invalid.',
            'parent_department_id.exists' => 'The selected parent department does not exist.',
            'department_head_staff_id.exists' => 'The selected department head does not exist.',
            'bed_count.min' => 'The bed count must be at least 0.',
            'treatment_room_count.min' => 'The treatment room count must be at least 0.',
            'max_concurrent_capacity.min' => 'The maximum concurrent capacity must be at least 1.',
            'max_concurrent_capacity.max' => 'The maximum concurrent capacity may not be greater than 1000.',
            'average_wait_time_minutes.max' => 'The average wait time may not be greater than 1440 minutes (24 hours).',
            'status.in' => 'The status must be either active, inactive, or temporarily closed.',
            'operating_hours.json' => 'The operating hours must be a valid JSON string.',
            'metadata.json' => 'The metadata must be a valid JSON string.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes(): array
    {
        return [
            'facility_id' => 'facility',
            'department_code' => 'department code',
            'department_name' => 'department name',
            'department_type' => 'department type',
            'parent_department_id' => 'parent department',
            'department_head_staff_id' => 'department head',
            'bed_count' => 'bed count',
            'treatment_room_count' => 'treatment room count',
            'max_concurrent_capacity' => 'maximum concurrent capacity',
            'building' => 'building',
            'floor' => 'floor',
            'wing_section' => 'wing section',
            'operating_hours' => 'operating hours',
            'average_wait_time_minutes' => 'average wait time',
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param  \Illuminate\Contracts\Validation\Validator  $validator
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function failedValidation(Validator $validator): void
    {
        $errors = $validator->errors();

        $response = new JsonResponse([
            'success' => false,
            'message' => 'Validation failed.',
            'errors' => $errors->messages(),
        ], 422);

        throw new HttpResponseException($response);
    }
}