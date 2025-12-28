<?php

namespace App\Http\Requests\MessageReceipt;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;

class UpdateMessageReceiptRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // In production, implement proper authorization logic
        // Example: return $this->user()->can('update', $this->route('message_receipt'));
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $receiptId = $this->route('message_receipt');
        
        return [
            'delivered_at' => [
                'nullable',
                'date',
                'before_or_equal:now'
            ],
            'read_at' => [
                'nullable',
                'date',
                'before_or_equal:now'
            ],
            'acknowledged_at' => [
                'nullable',
                'date',
                'before_or_equal:now'
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'delivered_at.date' => 'The delivered at must be a valid date.',
            'delivered_at.before_or_equal' => 'The delivered at cannot be in the future.',
            
            'read_at.date' => 'The read at must be a valid date.',
            'read_at.before_or_equal' => 'The read at cannot be in the future.',
            
            'acknowledged_at.date' => 'The acknowledged at must be a valid date.',
            'acknowledged_at.before_or_equal' => 'The acknowledged at cannot be in the future.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes(): array
    {
        return [
            'delivered_at' => 'delivered at',
            'read_at' => 'read at',
            'acknowledged_at' => 'acknowledged at',
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param Validator $validator
     * @return void
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator): void
    {
        $errors = $validator->errors();
        
        Log::warning('Message receipt update validation failed', [
            'errors' => $errors->toArray(),
            'input' => $this->all(),
            'receipt_id' => $this->route('message_receipt'),
            'user_id' => $this->user()?->id
        ]);
        
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $errors->toArray(),
                'help' => 'Please check the provided data and try again.'
            ], 422)
        );
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        // Convert empty strings to null for nullable fields
        $this->merge([
            'delivered_at' => $this->delivered_at ?: null,
            'read_at' => $this->read_at ?: null,
            'acknowledged_at' => $this->acknowledged_at ?: null,
        ]);
    }
}