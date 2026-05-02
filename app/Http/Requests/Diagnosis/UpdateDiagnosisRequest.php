<?php

declare(strict_types=1);

namespace App\Http\Requests\Diagnosis;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

class UpdateDiagnosisRequest extends FormRequest
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
            'diagnosis_code' => 'nullable|string|max:50',
            'diagnosis_description' => 'nullable|string|max:500',
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
            'diagnosis_code.max' => 'Diagnosis code cannot exceed 50 characters',
            'diagnosis_description.max' => 'Diagnosis description cannot exceed 500 characters',
            'diagnosis_type.in' => 'Invalid diagnosis type',
            'certainty.in' => 'Invalid certainty level',
            'clinical_status.in' => 'Invalid clinical status',
            'abatement_date.after_or_equal' => 'Abatement date must be after or equal to onset date',
        ];
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