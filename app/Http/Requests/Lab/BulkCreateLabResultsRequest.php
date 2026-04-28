<?php

namespace App\Http\Requests\Lab;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

class BulkCreateLabResultsRequest extends FormRequest
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
            'results' => 'required|array|min:1',
            'results.*.template_field_id' => 'required|exists:lab_template_fields,id',
            'results.*.value' => 'nullable|string',
            'results.*.unit' => 'nullable|string|max:50',
            'results.*.numeric_value' => 'nullable|numeric',
            'results.*.flag' => 'sometimes|required|in:normal,low,high,critical,abnormal,pending',
            'results.*.reference_min' => 'nullable|numeric',
            'results.*.reference_max' => 'nullable|numeric',
            'results.*.interpretation' => 'nullable|string',
            'results.*.comments' => 'nullable|string',
            'results.*.recorded_by_staff_id' => 'nullable|exists:staff,id',
            'results.*.metadata' => 'nullable|array',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'results.required' => 'Results array is required',
            'results.array' => 'Results must be an array',
            'results.min' => 'At least one result is required',
            'results.*.template_field_id.required' => 'Template field ID is required for each result',
            'results.*.template_field_id.exists' => 'One or more template fields do not exist',
            'results.*.unit.max' => 'Unit must not exceed 50 characters',
            'results.*.flag.in' => 'Invalid flag value for result',
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