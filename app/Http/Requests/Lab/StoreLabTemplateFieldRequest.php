<?php

namespace App\Http\Requests\Lab;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

class StoreLabTemplateFieldRequest extends FormRequest
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
            'name' => 'required|string|max:150',
            'code' => 'nullable|string|max:50',
            'template_id' => 'required|exists:lab_templates,id',
            'data_type' => 'required|in:number,text,boolean,select',
            'unit' => 'nullable|string|max:50',
            'reference_min' => 'nullable|numeric',
            'reference_max' => 'nullable|numeric',
            'display_order' => 'integer|min:0|max:65535',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'is_critical' => 'boolean',
            'clinical_notes' => 'nullable|string',
            'metadata' => 'nullable|array',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Field name is required',
            'name.max' => 'Field name must not exceed 150 characters',
            'code.max' => 'Code must not exceed 50 characters',
            'template_id.required' => 'Template ID is required',
            'template_id.exists' => 'The selected template does not exist',
            'data_type.required' => 'Data type is required',
            'data_type.in' => 'Invalid data type. Must be number, text, boolean, or select',
            'unit.max' => 'Unit must not exceed 50 characters',
            'reference_min.numeric' => 'Reference minimum must be a number',
            'reference_max.numeric' => 'Reference maximum must be a number',
            'display_order.integer' => 'Display order must be an integer',
            'is_required.boolean' => 'Is required must be true or false',
            'is_active.boolean' => 'Is active must be true or false',
            'is_critical.boolean' => 'Is critical must be true or false',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $booleanFields = ['is_required', 'is_active', 'is_critical'];
        foreach ($booleanFields as $field) {
            if ($this->has($field) && is_string($this->$field)) {
                $this->merge([
                    $field => filter_var($this->$field, FILTER_VALIDATE_BOOLEAN),
                ]);
            }
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