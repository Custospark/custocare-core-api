<?php

declare(strict_types=1);

namespace App\Http\Requests\Diagnosis;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

class StoreDiagnosisRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
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
            'facility_id' => 'required|exists:facilities,id',
            'visit_id' => 'required|exists:visits,id',
            'patient_id' => 'required|exists:patients,id',
            'staff_id' => 'nullable|exists:staff,id',
            'diagnosis_code' => 'required|string|max:50',
            'diagnosis_description' => 'required|string|max:500',
            'diagnosis_type' => 'nullable|in:primary,secondary,differential,admitting,discharge,provisional',
            'certainty' => 'nullable|in:confirmed,probable,possible,rule_out,suspected,uncertain',
            'clinical_status' => 'nullable|in:active,inactive,resolved,remission,chronic',
            'clinical_notes' => 'nullable|string',
            'onset_date' => 'nullable|date',
            'abatement_date' => 'nullable|date|after_or_equal:onset_date',
            'supporting_evidence' => 'nullable|array',
            'diagnostic_criteria_met' => 'nullable|string',
            'custom_fields' => 'nullable|array',
            'coding_metadata' => 'nullable|array',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'facility_id.required' => 'Facility ID is required',
            'facility_id.exists' => 'The selected facility does not exist',
            'visit_id.required' => 'Visit ID is required',
            'visit_id.exists' => 'The selected visit does not exist',
            'patient_id.required' => 'Patient ID is required',
            'patient_id.exists' => 'The selected patient does not exist',
            'staff_id.exists' => 'The selected staff member does not exist',
            'diagnosis_code.required' => 'Diagnosis code is required',
            'diagnosis_code.max' => 'Diagnosis code cannot exceed 50 characters',
            'diagnosis_description.required' => 'Diagnosis description is required',
            'diagnosis_description.max' => 'Diagnosis description cannot exceed 500 characters',
            'diagnosis_type.in' => 'Invalid diagnosis type',
            'certainty.in' => 'Invalid certainty level',
            'clinical_status.in' => 'Invalid clinical status',
            'abatement_date.after_or_equal' => 'Abatement date must be after or equal to onset date',
            'supporting_evidence.array' => 'Supporting evidence must be an array',
            'custom_fields.array' => 'Custom fields must be an array',
            'coding_metadata.array' => 'Coding metadata must be an array',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default diagnosis_type if not provided
        if (!$this->has('diagnosis_type')) {
            $this->merge([
                'diagnosis_type' => 'primary',
            ]);
        }

        // Set default certainty if not provided
        if (!$this->has('certainty')) {
            $this->merge([
                'certainty' => 'confirmed',
            ]);
        }

        // Set default clinical_status if not provided
        if (!$this->has('clinical_status')) {
            $this->merge([
                'clinical_status' => 'active',
            ]);
        }
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator): void
    {
        $response = new JsonResponse([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors(),
        ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);

        throw new HttpResponseException($response);
    }
}