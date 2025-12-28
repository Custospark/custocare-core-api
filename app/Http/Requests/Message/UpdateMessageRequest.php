<?php

namespace App\Http\Requests\Message;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\JsonResponse;

class UpdateMessageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'content' => [
                'sometimes',
                'required',
                'string',
                'max:10000',
            ],
            'contains_phi' => [
                'sometimes',
                'boolean',
            ],
            'is_clinical' => [
                'sometimes',
                'boolean',
            ],
            'requires_acknowledgement' => [
                'sometimes',
                'boolean',
            ],
            'delivery_status' => [
                'sometimes',
                'string',
                'in:pending,sent,delivered,failed',
            ],
            'edited_by_user_id' => [
                'nullable',
                'integer',
                'exists:users,id',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'content.required' => 'Message content is required when updating.',
            'content.max' => 'Message content cannot exceed 10,000 characters.',
            'delivery_status.in' => 'The delivery status is invalid.',
            'edited_by_user_id.exists' => 'The editor user does not exist.',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator): void
    {
        $errors = $validator->errors();
        
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $errors->messages(),
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY)
        );
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set edited_by_user_id to current user if not provided
        if ($this->has('content') && !$this->has('edited_by_user_id') && $this->user()) {
            $this->merge([
                'edited_by_user_id' => $this->user()->id,
            ]);
        }
    }
}