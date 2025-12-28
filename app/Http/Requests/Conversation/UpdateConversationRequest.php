<?php

namespace App\Http\Requests\Conversation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateConversationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $conversation = $this->route('conversation');
        
        // Check if user has permission to update this conversation
        return Auth::check() && $this->user()->can('update', $conversation);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $conversation = $this->route('conversation');
        $conversationId = $conversation ? $conversation->id : null;

        return [
            'facility_id' => ['sometimes', 'integer', 'exists:facilities,id'],
            'conversation_type' => [
                'sometimes',
                'string',
                Rule::in(['direct', 'group', 'broadcast', 'system', 'care_context'])
            ],
            'visit_id' => ['nullable', 'integer', 'exists:visits,id'],
            'appointment_id' => ['nullable', 'integer', 'exists:appointments,id'],
            'department_code' => ['nullable', 'string', 'max:50'],
            'title' => ['nullable', 'string', 'max:255'],
            'contains_phi' => ['boolean'],
            'is_emergency' => ['boolean'],
            'status' => ['string', Rule::in(['active', 'archived', 'locked'])],
            'conversation_uuid' => [
                'sometimes',
                'string',
                'uuid',
                Rule::unique('conversations', 'conversation_uuid')->ignore($conversationId)
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'facility_id.exists' => 'Selected facility does not exist',
            'conversation_type.in' => 'Invalid conversation type. Must be one of: direct, group, broadcast, system, care_context',
            'visit_id.exists' => 'Selected visit does not exist',
            'appointment_id.exists' => 'Selected appointment does not exist',
            'department_code.max' => 'Department code cannot exceed 50 characters',
            'title.max' => 'Title cannot exceed 255 characters',
            'status.in' => 'Status must be one of: active, archived, locked',
            'conversation_uuid.uuid' => 'Invalid UUID format',
            'conversation_uuid.unique' => 'This conversation UUID already exists',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'facility_id' => 'facility',
            'conversation_type' => 'conversation type',
            'visit_id' => 'visit',
            'appointment_id' => 'appointment',
            'department_code' => 'department code',
            'contains_phi' => 'contains PHI',
            'is_emergency' => 'emergency flag',
            'conversation_uuid' => 'conversation UUID',
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param Validator $validator
     * @return void
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
                'data' => null
            ], 422)
        );
    }

    /**
     * Handle a failed authorization attempt.
     *
     * @return void
     */
    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'You are not authorized to update this conversation',
                'errors' => ['authorization' => ['Unauthorized action']],
                'data' => null
            ], 403)
        );
    }
}