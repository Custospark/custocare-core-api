<?php

namespace App\Http\Requests\Referral;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReferralRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Check if user has permission to update referrals
        return auth()->check() && 
               (auth()->user()->can('update referrals') || 
                auth()->user()->role->hasPermission('update referrals'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'patient_id' => ['nullable', 'exists:patients,id'],
            'facility_id' => ['nullable', 'exists:facilities,id'],
            'referring_staff_id' => ['nullable', 'exists:staff,id'],
            'receiving_staff_id' => ['nullable', 'exists:staff,id'],
            'referral_type' => ['nullable', Rule::in(['internal', 'external'])],
            'referral_reason' => ['nullable', 'string', 'max:1000'],
            'clinical_notes' => ['nullable', 'string'],
            'external_referral_id' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['pending', 'accepted', 'rejected', 'completed', 'cancelled'])],
            'priority' => ['nullable', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'referral_date' => ['nullable', 'date'],
            'response_date' => ['nullable', 'date'],
            'completed_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:today'],
            'metadata' => ['nullable', 'array'],
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
            'patient_id.exists' => 'Patient does not exist.',
            'facility_id.exists' => 'Facility does not exist.',
            'referring_staff_id.exists' => 'Referring staff does not exist.',
            'receiving_staff_id.exists' => 'Receiving staff does not exist.',
            'referral_type.in' => 'Referral type must be either internal or external.',
            'status.in' => 'Status must be one of: pending, accepted, rejected, completed, cancelled.',
            'priority.in' => 'Priority must be one of: low, medium, high, urgent.',
            'referral_date.date' => 'Referral date must be a valid date.',
            'response_date.date' => 'Response date must be a valid date.',
            'completed_date.date' => 'Completed date must be a valid date.',
            'expiry_date.date' => 'Expiry date must be a valid date.',
            'expiry_date.after_or_equal' => 'Expiry date must be today or in the future.',
        ];
    }
}