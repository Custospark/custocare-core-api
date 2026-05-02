<?php

declare(strict_types=1);

namespace App\Http\Requests\ClinicalNote;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

class UpdateClinicalNoteRequest extends FormRequest
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
            'subjective' => 'nullable|string',
            'objective' => 'nullable|string',
            'assessment' => 'nullable|string',
            'plan' => 'nullable|string',
            'review_of_systems' => 'nullable|string',
            'past_medical_history' => 'nullable|string',
            'note_type' => 'nullable|in:initial,follow_up,progress,discharge,consultation',
            'note_status' => 'nullable|in:draft,final,amended,cancelled',
            'noted_at' => 'nullable|date',
            'signature' => 'nullable|string|max:255',
            'custom_fields' => 'nullable|array',
            'structured_data' => 'nullable|array',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'note_type.in' => 'Invalid note type. Must be: initial, follow_up, progress, discharge, consultation',
            'note_status.in' => 'Invalid note status. Must be: draft, final, amended, cancelled',
            'noted_at.date' => 'Invalid date format for noted_at',
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