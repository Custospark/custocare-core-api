<?php

declare(strict_types=1);

namespace App\Http\Requests\ClinicalNote;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

class StoreClinicalNoteRequest extends FormRequest
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
            'subjective' => 'nullable|string',
            'objective' => 'nullable|string',
            'assessment' => 'nullable|string',
            'plan' => 'nullable|string',
            'review_of_systems' => 'nullable|string',
            'past_medical_history' => 'nullable|string',
            'note_type' => 'nullable|in:initial,follow_up,progress,discharge,consultation,active',
            'note_status' => 'nullable|in:draft,final,amended,cancelled',
            'noted_at' => 'nullable|date',
            'signature' => 'nullable|string|max:255',
            'custom_fields' => 'nullable|array',
            'structured_data' => 'nullable|array',
            'parent_note_id' => 'nullable|exists:clinical_notes,id',
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
            'note_type.in' => 'Invalid note type. Must be: initial, follow_up, progress, discharge, consultation',
            'note_status.in' => 'Invalid note status. Must be: draft, final, amended, cancelled,active',
            'noted_at.date' => 'Invalid date format for noted_at',
            'parent_note_id.exists' => 'The parent note does not exist',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default note_type if not provided
        if (!$this->has('note_type')) {
            $this->merge([
                'note_type' => 'progress',
            ]);
        }

        // Set default note_status if not provided
        if (!$this->has('note_status')) {
            $this->merge([
                'note_status' => 'draft',
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