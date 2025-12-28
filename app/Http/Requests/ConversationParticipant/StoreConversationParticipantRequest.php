<?php

namespace App\Http\Requests\ConversationParticipant;

use App\Models\ConversationParticipant;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreConversationParticipantRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // Check if user has permission to add participants to conversation
        return Gate::allows('create', ConversationParticipant::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'conversation_id' => [
                'required',
                'integer',
                'exists:conversations,id',
            ],
            'participant_type' => [
                'required',
                'string',
                Rule::in([ConversationParticipant::PARTICIPANT_STAFF, ConversationParticipant::PARTICIPANT_PATIENT]),
            ],
            'participant_id' => [
                'required',
                'integer',
                // Validate participant exists based on type
                function ($attribute, $value, $fail) {
                    $type = $this->input('participant_type');
                    if ($type === ConversationParticipant::PARTICIPANT_STAFF) {
                        if (!\App\Models\Staff::find($value)) {
                            $fail('The selected staff member does not exist.');
                        }
                    } elseif ($type === ConversationParticipant::PARTICIPANT_PATIENT) {
                        if (!\App\Models\Patient::find($value)) {
                            $fail('The selected patient does not exist.');
                        }
                    }
                },
            ],
            'role' => [
                'sometimes',
                'string',
                Rule::in([
                    ConversationParticipant::ROLE_OWNER,
                    ConversationParticipant::ROLE_MODERATOR,
                    ConversationParticipant::ROLE_MEMBER,
                    ConversationParticipant::ROLE_READ_ONLY,
                ]),
            ],
            'joined_at' => [
                'sometimes',
                'date',
                'before_or_equal:now',
            ],
            'is_muted' => [
                'sometimes',
                'boolean',
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
            'conversation_id.required' => 'The conversation ID is required.',
            'conversation_id.exists' => 'The selected conversation does not exist.',
            'participant_type.required' => 'The participant type is required.',
            'participant_type.in' => 'The participant type must be either "staff" or "patient".',
            'participant_id.required' => 'The participant ID is required.',
            'role.in' => 'The role must be one of: owner, moderator, member, or read_only.',
            'joined_at.date' => 'The joined at must be a valid date.',
            'joined_at.before_or_equal' => 'The joined at cannot be in the future.',
            'is_muted.boolean' => 'The is muted field must be true or false.',
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
                'message' => 'Validation errors occurred.',
                'errors' => $validator->errors(),
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
                'message' => 'You are not authorized to add participants to this conversation.',
            ], 403)
        );
    }
}