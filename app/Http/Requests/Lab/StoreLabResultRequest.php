<?php

namespace App\Http\Requests\Lab;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

class StoreLabResultRequest extends FormRequest
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
            'lab_request_item_id' => 'required|exists:lab_request_items,id',
            'template_field_id' => 'required|exists:lab_template_fields,id',
            'value' => 'nullable|string',
            'unit' => 'nullable|string|max:50',
            'numeric_value' => 'nullable|numeric',
            'flag' => 'sometimes|required|in:normal,low,high,critical,abnormal,pending',
            'reference_min' => 'nullable|numeric',
            'reference_max' => 'nullable|numeric',
            'interpretation' => 'nullable|string',
            'comments' => 'nullable|string',
            'recorded_by_staff_id' => 'nullable|exists:staff,id',
            'verified_by_staff_id' => 'nullable|exists:staff,id',
            'verified_at' => 'nullable|date',
            'recorded_at' => 'nullable|date',
            'is_abnormal_flagged' => 'boolean',
            'is_critical_alert_sent' => 'boolean',
            'metadata' => 'nullable|array',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'lab_request_item_id.required' => 'Lab request item ID is required',
            'lab_request_item_id.exists' => 'The selected lab request item does not exist',
            'template_field_id.required' => 'Template field ID is required',
            'template_field_id.exists' => 'The selected template field does not exist',
            'unit.max' => 'Unit must not exceed 50 characters',
            'numeric_value.numeric' => 'Numeric value must be a number',
            'flag.in' => 'Invalid flag value',
            'reference_min.numeric' => 'Reference minimum must be a number',
            'reference_max.numeric' => 'Reference maximum must be a number',
            'recorded_by_staff_id.exists' => 'The selected staff does not exist',
            'verified_by_staff_id.exists' => 'The selected staff does not exist',
            'is_abnormal_flagged.boolean' => 'Is abnormal flagged must be true or false',
            'is_critical_alert_sent.boolean' => 'Is critical alert sent must be true or false',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default flag if not provided
        if (!$this->has('flag')) {
            $this->merge([
                'flag' => 'pending',
            ]);
        }
        
        // Set default recorded_at if not provided
        if (!$this->has('recorded_at')) {
            $this->merge([
                'recorded_at' => now(),
            ]);
        }
        
        // Convert boolean fields
        $booleanFields = ['is_abnormal_flagged', 'is_critical_alert_sent'];
        foreach ($booleanFields as $field) {
            if ($this->has($field) && is_string($this->$field)) {
                $this->merge([
                    $field => filter_var($this->$field, FILTER_VALIDATE_BOOLEAN),
                ]);
            }
        }
        
        // Calculate numeric value if value is numeric
        if ($this->has('value') && is_numeric($this->value)) {
            $this->merge([
                'numeric_value' => (float) $this->value,
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