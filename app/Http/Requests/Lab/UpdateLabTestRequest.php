<?php

namespace App\Http\Requests\Lab;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

class UpdateLabTestRequest extends FormRequest
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
            'code' => 'nullable|string|max:50',
            'template_id' => 'sometimes|required|exists:lab_templates,id',
            'facility_id' => 'nullable|exists:facilities,id',
            'is_shared' => 'boolean',
            'category' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'requires_fasting' => 'boolean',
            'turnaround_time_hours' => 'nullable|integer|min:0|max:65535',
            'metadata' => 'nullable|array',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Test name is required',
            'name.max' => 'Test name must not exceed 150 characters',
            'code.max' => 'Code must not exceed 50 characters',
            'template_id.required' => 'Template ID is required',
            'template_id.exists' => 'The selected template does not exist',
            'facility_id.exists' => 'The selected facility does not exist',
            'is_shared.boolean' => 'Is shared must be true or false',
            'category.max' => 'Category must not exceed 100 characters',
            'turnaround_time_hours.integer' => 'Turnaround time must be an integer',
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