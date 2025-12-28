<?php

namespace App\Http\Requests\Message;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\JsonResponse;

class StoreMessageRequest extends FormRequest
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
            'conversation_id' => [
                'required',
                'integer',
                'exists:conversations,id',
            ],
            'sender_type' => [
                'required',
                'string',
                'in:staff,patient,system',
            ],
            'sender_id' => [
                'nullable',
                'integer',
                'required_if:sender_type,staff,patient',
            ],
            'message_type' => [
                'required',
                'string',
                'in:text,rich_text,system_event,clinical_note,alert,file,image',
            ],
            'content' => [
                'required',
                'string',
                'max:10000',
            ],
            'contains_phi' => [
                'boolean',
            ],
            'is_clinical' => [
                'boolean',
            ],
            'requires_acknowledgement' => [
                'boolean',
            ],
            'parent_message_id' => [
                'nullable',
                'integer',
                'exists:messages,id',
            ],
            'delivery_status' => [
                'string',
                'in:pending,sent,delivered,failed',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'conversation_id.required' => 'The conversation ID is required.',
            'conversation_id.exists' => 'The selected conversation does not exist.',
            'sender_type.required' => 'The sender type is required.',
            'sender_type.in' => 'The sender type must be one of: staff, patient, or system.',
            'sender_id.required_if' => 'The sender ID is required for staff or patient senders.',
            'message_type.required' => 'The message type is required.',
            'message_type.in' => 'The message type is invalid.',
            'content.required' => 'Message content is required.',
            'content.max' => 'Message content cannot exceed 10,000 characters.',
            'parent_message_id.exists' => 'The parent message does not exist.',
            'delivery_status.in' => 'The delivery status is invalid.',
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
        // Set default values if not provided
        $this->merge([
            'contains_phi' => $this->contains_phi ?? true,
            'is_clinical' => $this->is_clinical ?? false,
            'requires_acknowledgement' => $this->requires_acknowledgement ?? false,
            'delivery_status' => $this->delivery_status ?? 'pending',
        ]);
    }
}