<?php

namespace App\Http\Requests\Lab;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

class UpdateLabTemplateRequest extends FormRequest
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
            'name' => 'sometimes|required|string|max:150',
            'description' => 'nullable|string',
            'facility_id' => 'nullable|exists:facilities,id',
            'is_shared' => 'boolean',
            'structure_type' => 'sometimes|required|in:standard,simple,panel',
            'is_active' => 'boolean',
            'metadata' => 'nullable|array',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Template name is required',
            'name.max' => 'Template name must not exceed 150 characters',
            'structure_type.required' => 'Structure type is required',
            'structure_type.in' => 'Invalid structure type. Must be standard, simple, or panel',
            'facility_id.exists' => 'The selected facility does not exist',
            'is_shared.boolean' => 'Is shared must be true or false',
            'is_active.boolean' => 'Is active must be true or false',
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