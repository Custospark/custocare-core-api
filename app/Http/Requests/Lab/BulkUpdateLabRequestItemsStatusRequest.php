<?php

namespace App\Http\Requests\Lab;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

class BulkUpdateLabRequestItemsStatusRequest extends FormRequest
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
            'item_uuids' => 'required|array|min:1',
            'item_uuids.*' => 'required|uuid|exists:lab_request_items,item_uuid',
            'status' => 'required|string|in:pending,sample_collected,in_progress,completed,verified,cancelled',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'item_uuids.required' => 'Item UUIDs are required',
            'item_uuids.array' => 'Item UUIDs must be an array',
            'item_uuids.min' => 'At least one item UUID is required',
            'item_uuids.*.required' => 'Each item UUID is required',
            'item_uuids.*.uuid' => 'Each item must be a valid UUID',
            'item_uuids.*.exists' => 'One or more lab request items do not exist',
            'status.required' => 'Status is required',
            'status.in' => 'Invalid status value',
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