<?php

namespace App\Http\Requests\StaffInvitation;

use App\Models\StaffInvitation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;

class StoreStaffInvitationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Check if user can create staff invitations
        // In production, you would check specific permissions
        // return Auth::check() && Auth::user()->can('create', StaffInvitation::class);
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
            'staff_id' => [
                'required',
                'integer',
                'exists:staff,id',
                // Additional validation to ensure staff can be invited
                function ($attribute, $value, $fail) {
                    // Check if staff exists and is active
                    // This would be more complex in production
                    if (!\App\Models\Staff::where('id', $value)->exists()) {
                        $fail('The selected staff member does not exist.');
                    }
                },
            ],
            'facility_id' => [
                'required',
                'integer',
                'exists:facilities,id',
                // Check if facility is active
                function ($attribute, $value, $fail) {
                    $facility = \App\Models\Facility::find($value);
                    if (!$facility || !$facility->is_active) {
                        $fail('The selected facility is not available for invitations.');
                    }
                },
            ],
            'department_id' => [
                'nullable',
                'integer',
                'exists:departments,id',
                // Validate department belongs to facility
                function ($attribute, $value, $fail) {
                    $facilityId = $this->input('facility_id');
                    if ($value && $facilityId) {
                        $department = \App\Models\Department::where('id', $value)
                            ->where('facility_id', $facilityId)
                            ->first();
                        
                        if (!$department) {
                            $fail('The selected department does not belong to the specified facility.');
                        }
                    }
                },
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
                function ($attribute, $value, $fail) {
                    // Ensure expiration is not too far in the future
                    $maxDays = config('staff_invitations.max_expiration_days', 30);
                    $expirationDate = \Carbon\Carbon::parse($value);
                    
                    if ($expirationDate->diffInDays(now()) > $maxDays) {
                        $fail("Invitation cannot expire more than {$maxDays} days from now.");
                    }
                },
            ],
            'metadata' => [
                'nullable',
                'array',
                function ($attribute, $value, $fail) {
                    // Validate metadata structure
                    if ($value && !is_array($value)) {
                        $fail('Metadata must be a valid JSON object.');
                    }
                },
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
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'staff_id.required' => 'Please select a staff member to invite.',
            'staff_id.exists' => 'The selected staff member does not exist.',
            'facility_id.required' => 'Please select a facility.',
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
            'message' => 'You are not authorized to create staff invitations.',
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