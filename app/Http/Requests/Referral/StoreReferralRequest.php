<?php

namespace App\Http\Requests\Referral;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReferralRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Check if user has permission to create referrals
        return auth()->check() && 
               (auth()->user()->can('create referrals') || 
                auth()->user()->role->hasPermission('create referrals'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'exists:patients,id'],
            'facility_id' => ['required', 'exists:facilities,id'],
            'receiving_facility_id' => ['nullable', 'exists:facilities,id'],
            'referring_staff_id' => ['nullable', 'exists:staff,id'],
            'receiving_staff_id' => ['nullable', 'exists:staff,id'],
            'referral_type' => ['required', Rule::in(['internal', 'external'])],
            'referral_reason' => ['nullable', 'string', 'max:1000'],
            'clinical_notes' => ['nullable', 'string'],
            'external_referral_id' => ['nullable', 'string', 'max:100'],
            'priority' => ['nullable', Rule::in(['low', 'medium', 'high', 'urgent']), 'default:medium'],
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
            'patient_id.required' => 'Patient ID is required.',
            'patient_id.exists' => 'Patient does not exist.',
            'facility_id.required' => 'Referring facility ID is required.',
            'facility_id.exists' => 'Referring facility does not exist.',
            'receiving_facility_id.exists' => 'Receiving facility does not exist.',
            'referring_staff_id.exists' => 'Referring staff does not exist.',
            'receiving_staff_id.exists' => 'Receiving staff does not exist.',
            'referral_type.required' => 'Referral type is required.',
            'referral_type.in' => 'Referral type must be either internal or external.',
            'priority.in' => 'Priority must be one of: low, medium, high, urgent.',
            'expiry_date.date' => 'Expiry date must be a valid date.',
            'expiry_date.after_or_equal' => 'Expiry date must be today or in the future.',
        ];
    }
}