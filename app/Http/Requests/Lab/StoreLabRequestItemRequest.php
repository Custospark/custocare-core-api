<?php

namespace App\Http\Requests\Lab;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

class StoreLabRequestItemRequest extends FormRequest
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
            'lab_request_id' => 'required|exists:lab_requests,id',
            'lab_test_id' => 'required|exists:lab_tests,id',
            'status' => 'sometimes|required|in:pending,sample_collected,in_progress,completed,verified,cancelled',
            'sample_type' => 'nullable|string|max:100',
            'sample_identifier' => 'nullable|string|max:100',
            'collected_at' => 'nullable|date',
            'collected_by_staff_id' => 'nullable|exists:staff,id',
            'started_at' => 'nullable|date',
            'completed_at' => 'nullable|date',
            'verified_by_staff_id' => 'nullable|exists:staff,id',
            'verified_at' => 'nullable|date',
            'result_flag' => 'sometimes|required|in:normal,abnormal,critical,pending',
            'notes' => 'nullable|string',
            'cancellation_reason' => 'nullable|string',
            'cancelled_at' => 'nullable|date',
            'metadata' => 'nullable|array',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'lab_request_id.required' => 'Lab request ID is required',
            'lab_request_id.exists' => 'The selected lab request does not exist',
            'lab_test_id.required' => 'Lab test ID is required',
            'lab_test_id.exists' => 'The selected lab test does not exist',
            'status.in' => 'Invalid status',
            'sample_type.max' => 'Sample type must not exceed 100 characters',
            'sample_identifier.max' => 'Sample identifier must not exceed 100 characters',
            'collected_by_staff_id.exists' => 'The selected staff does not exist',
            'verified_by_staff_id.exists' => 'The selected staff does not exist',
            'result_flag.in' => 'Invalid result flag. Must be normal, abnormal, critical, or pending',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default status and result flag if not provided
        if (!$this->has('status')) {
            $this->merge([
                'status' => 'pending',
            ]);
        }
        
        if (!$this->has('result_flag')) {
            $this->merge([
                'result_flag' => 'pending',
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