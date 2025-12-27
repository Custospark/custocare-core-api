<?php

namespace App\Http\Requests\StaffInvitation;

use App\Models\StaffInvitation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

class UpdateStaffInvitationRequest extends FormRequest
{
    /**
     * The staff invitation instance.
     */
    protected ?StaffInvitation $staffInvitation = null;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $this->staffInvitation = StaffInvitation::find($this->route('staff_invitation'));
        
        if (!$this->staffInvitation) {
            return false;
        }
        
        return Auth::check() && Auth::user()->can('update', $this->staffInvitation);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $invitation = $this->staffInvitation;
        
        $rules = [
            'department_id' => [
                'nullable',
                'integer',
                'exists:departments,id',
            ],
            'role_id' => [
                'nullable',
                'integer',
                'exists:roles,id',
            ],
            'expires_at' => [
                'nullable',
                'date',
                'after_or_equal:today',
                function ($attribute, $value, $fail) use ($invitation) {
                    if ($invitation && $invitation->sent_at && $value) {
                        $expirationDate = \Carbon\Carbon::parse($value);
                        $sentDate = \Carbon\Carbon::parse($invitation->sent_at);
                        
                        // Ensure expiration is after sent date
                        if ($expirationDate->lessThanOrEqualTo($sentDate)) {
                            $fail('Expiration date must be after the invitation sent date.');
                        }
                        
                        // Ensure expiration is not too far in the future
                        $maxDays = config('staff_invitations.max_expiration_days', 30);
                        if ($expirationDate->diffInDays($sentDate) > $maxDays) {
                            $fail("Invitation cannot expire more than {$maxDays} days from the sent date.");
                        }
                    }
                },
            ],
            'metadata' => [
                'nullable',
                'array',
            ],
            'metadata.message' => [
                'nullable',
                'string',
                'max:500',
            ],
            'metadata.reason' => [
                'nullable',
                'string',
                'max:255',
            ],
        ];

        // Only allow staff_id and facility_id changes for pending invitations
        if ($invitation && $invitation->isPending()) {
            $rules['staff_id'] = [
                'sometimes',
                'integer',
                'exists:staff,id',
            ];
            
            $rules['facility_id'] = [
                'sometimes',
                'integer',
                'exists:facilities,id',
            ];
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'staff_id.exists' => 'The selected staff member does not exist.',
            'facility_id.exists' => 'The selected facility does not exist.',
            'department_id.exists' => 'The selected department does not exist.',
            'role_id.exists' => 'The selected role does not exist.',
            'expires_at.date' => 'Please provide a valid expiration date.',
            'expires_at.after_or_equal' => 'Expiration date must be today or in the future.',
            'metadata.array' => 'Metadata must be a valid JSON object.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'staff_id' => 'staff member',
            'facility_id' => 'facility',
            'department_id' => 'department',
            'role_id' => 'role',
            'expires_at' => 'expiration date',
            'metadata' => 'metadata',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator): void
    {
        $response = [
            'success' => false,
            'message' => 'Validation failed. Please check your input.',
            'errors' => $validator->errors()->toArray(),
            'data' => null
        ];

        throw new HttpResponseException(
            response()->json($response, 422)
        );
    }

    /**
     * Handle a failed authorization attempt.
     */
    protected function failedAuthorization(): void
    {
        $response = [
            'success' => false,
            'message' => 'You are not authorized to update this staff invitation.',
            'errors' => ['authorization' => ['Insufficient permissions to perform this action.']],
            'data' => null
        ];

        throw new HttpResponseException(
            response()->json($response, 403)
        );
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure metadata is properly formatted if provided
        if ($this->has('metadata') && is_string($this->metadata)) {
            $this->merge([
                'metadata' => json_decode($this->metadata, true) ?: null,
            ]);
        }
    }
}