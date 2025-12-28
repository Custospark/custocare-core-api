<?php

namespace App\Http\Requests\ConversationParticipant;

use App\Models\ConversationParticipant;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateConversationParticipantRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        $participant = ConversationParticipant::find($this->route('conversation_participant'));
        
        if (!$participant) {
            return false;
        }

        return Gate::allows('update', $participant);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
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
            'left_at' => [
                'sometimes',
                'date',
                'after_or_equal:joined_at',
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
            'role.in' => 'The role must be one of: owner, moderator, member, or read_only.',
            'joined_at.date' => 'The joined at must be a valid date.',
            'joined_at.before_or_equal' => 'The joined at cannot be in the future.',
            'left_at.date' => 'The left at must be a valid date.',
            'left_at.after_or_equal' => 'The left at must be after or equal to the joined at.',
            'left_at.before_or_equal' => 'The left at cannot be in the future.',
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
                'message' => 'You are not authorized to update this conversation participant.',
            ], 403)
        );
    }
}