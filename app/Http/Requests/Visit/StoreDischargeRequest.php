<?php

namespace App\Http\Requests\Visit;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreDischargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'discharge_disposition' => [
                'required',
                'string',
                Rule::in([
                    'home',
                    'admitted_to_hospital',
                    'transferred_to_facility',
                    'left_ama',
                    'left_without_seen',
                    'expired',
                    'hospice',
                    'skilled_nursing_facility',
                    'rehabilitation_facility',
                    'psychiatric_facility',
                    'law_enforcement_custody',
                ]),
            ],
            'discharge_diagnosis' => 'nullable|string|max:5000',
            'discharge_instructions' => 'nullable|string|max:5000',
            'discharge_medications' => 'nullable|array',
            'discharge_medications.*.name' => 'required_with:discharge_medications|string|max:255',
            'discharge_medications.*.dosage' => 'nullable|string|max:255',
            'discharge_medications.*.frequency' => 'nullable|string|max:255',
            'discharge_medications.*.route' => 'nullable|string|max:255',
            'discharge_medications.*.duration_days' => 'nullable|integer|min:1|max:365',
            'followup_scheduled_at' => 'nullable|date',
            'followup_provider_staff_id' => 'nullable|integer|exists:staff,id',
            'discharged_at' => 'nullable|date',
        ];
    }

    public function messages(): array
    {
        return [
            'discharge_disposition.required' => 'The discharge disposition is required.',
            'discharge_disposition.in' => 'The selected discharge disposition is invalid.',
            'discharge_diagnosis.max' => 'The discharge diagnosis must not exceed 5000 characters.',
            'discharge_instructions.max' => 'The discharge instructions must not exceed 5000 characters.',
            'discharge_medications.*.name.required_with' => 'Each medication must have a name.',
            'discharge_medications.*.name.max' => 'Each medication name must not exceed 255 characters.',
            'discharge_medications.*.dosage.max' => 'Each medication dosage must not exceed 255 characters.',
            'discharge_medications.*.frequency.max' => 'Each medication frequency must not exceed 255 characters.',
            'discharge_medications.*.route.max' => 'Each medication route must not exceed 255 characters.',
            'discharge_medications.*.duration_days.min' => 'Each medication duration must be at least 1 day.',
            'discharge_medications.*.duration_days.max' => 'Each medication duration must not exceed 365 days.',
            'followup_scheduled_at.date' => 'The follow-up scheduled date is invalid.',
            'followup_provider_staff_id.exists' => 'The selected follow-up provider does not exist.',
            'discharged_at.date' => 'The discharged date is invalid.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation failed.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
