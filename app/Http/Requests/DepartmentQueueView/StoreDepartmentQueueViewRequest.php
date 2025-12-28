<?php

namespace App\Http\Requests\DepartmentQueueView;

use App\Models\DepartmentQueueView;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreDepartmentQueueViewRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('create', DepartmentQueueView::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'facility_id' => 'required|integer|exists:facilities,id',
            'department_id' => 'required|integer|exists:departments,id',
            'queue_type' => 'required|in:triage,consultation,procedures,diagnostic_imaging,laboratory,pharmacy,discharge',
            'patients_waiting_count' => 'required|integer|min:0|max:1000',
            'patients_in_treatment_count' => 'required|integer|min:0|max:500',
            'average_wait_minutes' => 'nullable|integer|min:0|max:480',
            'median_wait_minutes' => 'nullable|integer|min:0|max:480',
            'longest_wait_minutes' => 'nullable|integer|min:0|max:1440',
            'longest_waiting_visit_id' => 'nullable|integer|exists:visits,id',
            'next_patient_ids' => 'nullable|array',
            'next_patient_ids.*' => 'integer|exists:patients,id',
            'critical_patients' => 'nullable|array',
            'critical_patients.*.patient_id' => 'integer|exists:patients,id',
            'critical_patients.*.priority_level' => 'string|in:low,medium,high,critical',
            'staff_available_count' => 'required|integer|min:0|max:100',
            'staff_total_count' => 'required|integer|min:0|max:100',
            'available_staff_ids' => 'nullable|array',
            'available_staff_ids.*' => 'integer|exists:staff,id',
            'capacity_percentage' => 'nullable|integer|min:0|max:100',
            'bed_utilization_percentage' => 'nullable|integer|min:0|max:100',
            'capacity_status' => 'required|in:normal,busy,critical,at_capacity',
            'predicted_wait_times' => 'nullable|array',
            'predicted_next_available_at' => 'nullable|date|after:now',
            'snapshot_at' => 'nullable|date|before_or_equal:now',
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
            'department_id.required' => 'Department ID is required',
            'department_id.exists' => 'The selected department does not exist',
            'queue_type.required' => 'Queue type is required',
            'queue_type.in' => 'Invalid queue type specified',
            'patients_waiting_count.required' => 'Patients waiting count is required',
            'patients_waiting_count.min' => 'Patients waiting count cannot be negative',
            'patients_waiting_count.max' => 'Patients waiting count exceeds maximum allowed',
            'staff_available_count.required' => 'Available staff count is required',
            'staff_available_count.max' => 'Available staff count exceeds maximum allowed',
            'staff_total_count.required' => 'Total staff count is required',
            'staff_total_count.max' => 'Total staff count exceeds maximum allowed',
            'capacity_status.required' => 'Capacity status is required',
            'capacity_status.in' => 'Invalid capacity status specified',
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
    protected function failedValidation(Validator $validator)
    {
        $errors = $validator->errors();
        
        // Add custom error for business logic
        if ($this->has('staff_available_count') && $this->has('staff_total_count')) {
            if ($this->input('staff_available_count') > $this->input('staff_total_count')) {
                $errors->add('staff_available_count', 'Available staff cannot exceed total staff');
            }
        }

        if ($this->has('patients_waiting_count') && $this->has('patients_in_treatment_count')) {
            $total = $this->input('patients_waiting_count') + $this->input('patients_in_treatment_count');
            if ($total > 1500) {
                $errors->add('total_active_patients', 'Total patients exceeds system limits');
            }
        }

        $response = new JsonResponse([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $errors->messages()
        ], 422);

        throw new HttpResponseException($response);
    }

    /**
     * Get the validated data from the request.
     *
     * @return array<string, mixed>
     */
    public function validatedData(): array
    {
        $validated = $this->validated();

        // Ensure arrays are properly formatted
        if (isset($validated['next_patient_ids']) && is_string($validated['next_patient_ids'])) {
            $validated['next_patient_ids'] = json_decode($validated['next_patient_ids'], true);
        }

        if (isset($validated['critical_patients']) && is_string($validated['critical_patients'])) {
            $validated['critical_patients'] = json_decode($validated['critical_patients'], true);
        }

        if (isset($validated['available_staff_ids']) && is_string($validated['available_staff_ids'])) {
            $validated['available_staff_ids'] = json_decode($validated['available_staff_ids'], true);
        }

        if (isset($validated['predicted_wait_times']) && is_string($validated['predicted_wait_times'])) {
            $validated['predicted_wait_times'] = json_decode($validated['predicted_wait_times'], true);
        }

        return $validated;
    }
}