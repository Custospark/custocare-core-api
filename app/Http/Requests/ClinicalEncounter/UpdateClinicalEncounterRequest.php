<?php

namespace App\Http\Requests\ClinicalEncounter;

use App\Models\ClinicalEncounter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateClinicalEncounterRequest extends FormRequest
{
    /**
     * The clinical encounter instance.
     *
     * @var ClinicalEncounter|null
     */
    protected ?ClinicalEncounter $encounter = null;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $this->encounter = $this->route('clinicalEncounter');
        
        if (!$this->encounter) {
            return false;
        }
        
        return Auth::check() && Auth::user()->can('update', $this->encounter);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'facility_id' => ['sometimes', 'integer', 'exists:facilities,id'],
            'visit_id' => ['sometimes', 'integer', 'exists:visits,id'],
            'patient_id' => ['sometimes', 'integer', 'exists:patients,id'],
            'encounter_type' => [
                'sometimes',
                'string',
                Rule::in([
                    'initial_consultation',
                    'followup_consultation',
                    'procedure',
                    'diagnostic_review',
                    'medication_review',
                    'telehealth_visit',
                    'specialist_consultation',
                    'pre_operative_assessment',
                    'post_operative_followup',
                    'discharge_summary'
                ])
            ],
            'primary_provider_staff_id' => ['sometimes', 'integer', 'exists:staff,id'],
            'supervising_provider_staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'department_id' => ['sometimes', 'integer', 'exists:departments,id'],
            
            // Subjective components
            'subjective_assessment' => ['nullable', 'string', 'max:5000'],
            'chief_complaints' => ['nullable', 'array'],
            'chief_complaints.*.complaint' => ['required_with:chief_complaints', 'string', 'max:500'],
            'chief_complaints.*.duration' => ['nullable', 'string', 'max:100'],
            'history_present_illness' => ['nullable', 'string', 'max:5000'],
            'review_of_systems' => ['nullable', 'array'],
            'review_of_systems.*.system' => ['required_with:review_of_systems', 'string', 'max:100'],
            'review_of_systems.*.findings' => ['nullable', 'string', 'max:500'],
            'patient_concerns' => ['nullable', 'string', 'max:2000'],
            
            // Objective components
            'objective_findings' => ['nullable', 'string', 'max:5000'],
            'vital_signs' => ['nullable', 'array'],
            'vital_signs.bp_systolic' => ['nullable', 'integer', 'between:50,250'],
            'vital_signs.bp_diastolic' => ['nullable', 'integer', 'between:30,150'],
            'vital_signs.heart_rate' => ['nullable', 'integer', 'between:30,200'],
            'vital_signs.respiratory_rate' => ['nullable', 'integer', 'between:8,60'],
            'vital_signs.temperature' => ['nullable', 'numeric', 'between:30,45'],
            'vital_signs.oxygen_saturation' => ['nullable', 'numeric', 'between:70,100'],
            'physical_examination' => ['nullable', 'array'],
            'laboratory_results' => ['nullable', 'array'],
            'imaging_results' => ['nullable', 'array'],
            'diagnostic_test_results' => ['nullable', 'array'],
            
            // Assessment components
            'assessment_diagnosis_codes' => ['sometimes', 'array', 'min:1'],
            'assessment_diagnosis_codes.*.code' => ['required_with:assessment_diagnosis_codes', 'string', 'max:20'],
            'assessment_diagnosis_codes.*.description' => ['required_with:assessment_diagnosis_codes', 'string', 'max:500'],
            'assessment_diagnosis_codes.*.primary' => ['nullable', 'boolean'],
            'clinical_impression' => ['sometimes', 'string', 'max:5000'],
            'differential_diagnoses' => ['nullable', 'array'],
            'differential_diagnoses.*.diagnosis' => ['required_with:differential_diagnoses', 'string', 'max:500'],
            'differential_diagnoses.*.probability' => ['nullable', 'string', 'in:high,medium,low'],
            'severity_score' => ['nullable', 'integer', 'between:1,10'],
            'risk_factors' => ['nullable', 'array'],
            'comorbidities' => ['nullable', 'array'],
            
            // Plan components
            'plan_treatment_codes' => ['nullable', 'array'],
            'plan_treatment_codes.*.code' => ['required_with:plan_treatment_codes', 'string', 'max:20'],
            'plan_treatment_codes.*.description' => ['required_with:plan_treatment_codes', 'string', 'max:500'],
            'treatment_plan' => ['sometimes', 'string', 'max:5000'],
            'medications_prescribed' => ['nullable', 'array'],
            'medications_prescribed.*.name' => ['required_with:medications_prescribed', 'string', 'max:200'],
            'medications_prescribed.*.dosage' => ['required_with:medications_prescribed', 'string', 'max:100'],
            'medications_prescribed.*.frequency' => ['required_with:medications_prescribed', 'string', 'max:100'],
            'procedures_planned' => ['nullable', 'array'],
            'referrals_ordered' => ['nullable', 'array'],
            'followup_instructions' => ['nullable', 'array'],
            'next_review_scheduled_at' => ['nullable', 'date', 'after_or_equal:today'],
            
            // Additional notes
            'clinical_notes_structured' => ['nullable', 'array'],
            'clinical_notes_free_text' => ['nullable', 'string', 'max:5000'],
            'provider_comments' => ['nullable', 'string', 'max:2000'],
            
            // Risk flags
            'risk_flags' => ['nullable', 'array'],
            'risk_flags.*.type' => ['required_with:risk_flags', 'string', 'max:50'],
            'risk_flags.*.level' => ['required_with:risk_flags', 'string', 'in:high,medium,low'],
            'safety_alerts' => ['nullable', 'array'],
            'requires_immediate_attention' => ['nullable', 'boolean'],
            
            // Quality metrics
            'meets_quality_measures' => ['nullable', 'boolean'],
            'quality_measure_codes' => ['nullable', 'array'],
            
            // Clinical decision support
            'ai_assistance_used' => ['nullable', 'boolean'],
            'clinical_decision_support_alerts' => ['nullable', 'array'],
            
            // Documentation
            'documentation_status' => ['sometimes', 'string', 'in:in_progress,completed,signed,amended,corrected,entered_in_error'],
            'documented_at' => ['nullable', 'date'],
            'signed_at' => ['nullable', 'date'],
            'electronic_signature_hash' => ['nullable', 'string', 'max:128'],
            
            // Amendments
            'amended_from_encounter_id' => ['nullable', 'integer', 'exists:clinical_encounters,id'],
            'amendment_reason' => ['nullable', 'string', 'max:1000', 'required_with:amended_at'],
            'amended_at' => ['nullable', 'date'],
            
            // Billing
            'is_billable' => ['nullable', 'boolean'],
            'billing_code' => ['nullable', 'string', 'max:20'],
            
            // Metadata
            'metadata' => ['nullable', 'array'],
        ];

        // Additional validation for signed encounters
        if ($this->encounter && $this->encounter->signed_at) {
            // For signed encounters, require amendment reason for updates
            if ($this->hasAny(array_keys($rules)) && !$this->has('amendment_reason')) {
                $rules['amendment_reason'] = ['required', 'string', 'max:1000'];
            }
        }

        return $rules;
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
            'visit_id' => 'visit',
            'patient_id' => 'patient',
            'primary_provider_staff_id' => 'primary provider',
            'supervising_provider_staff_id' => 'supervising provider',
            'department_id' => 'department',
            'assessment_diagnosis_codes' => 'diagnosis codes',
            'plan_treatment_codes' => 'treatment codes',
            'next_review_scheduled_at' => 'next review date',
            'amended_from_encounter_id' => 'amended from encounter',
            'amendment_reason' => 'amendment reason',
            'electronic_signature_hash' => 'electronic signature',
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'assessment_diagnosis_codes.min' => 'At least one diagnosis code is required.',
            'encounter_type.in' => 'The selected encounter type is invalid.',
            'documentation_status.in' => 'The selected documentation status is invalid.',
            'amendment_reason.required' => 'Amendment reason is required for updating signed encounters.',
            'amendment_reason.required_with' => 'Amendment reason is required when amending an encounter.',
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param  \Illuminate\Contracts\Validation\Validator  $validator
     * @return void
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function failedValidation(Validator $validator): void
    {
        $response = new JsonResponse([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors(),
        ], 422);

        throw new HttpResponseException($response);
    }

    /**
     * Handle a failed authorization attempt.
     *
     * @return void
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function failedAuthorization(): void
    {
        $response = new JsonResponse([
            'success' => false,
            'message' => 'You are not authorized to update this clinical encounter.',
        ], 403);

        throw new HttpResponseException($response);
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure JSON fields are properly formatted
        $jsonFields = [
            'chief_complaints',
            'review_of_systems',
            'vital_signs',
            'physical_examination',
            'laboratory_results',
            'imaging_results',
            'diagnostic_test_results',
            'assessment_diagnosis_codes',
            'differential_diagnoses',
            'risk_factors',
            'comorbidities',
            'plan_treatment_codes',
            'medications_prescribed',
            'procedures_planned',
            'referrals_ordered',
            'followup_instructions',
            'clinical_notes_structured',
            'risk_flags',
            'safety_alerts',
            'quality_measure_codes',
            'clinical_decision_support_alerts',
            'metadata',
        ];

        foreach ($jsonFields as $field) {
            if ($this->has($field) && is_string($this->$field)) {
                try {
                    $this->merge([
                        $field => json_decode($this->$field, true, 512, JSON_THROW_ON_ERROR)
                    ]);
                } catch (\JsonException $e) {
                    // If JSON is invalid, set to null and let validation handle it
                    $this->merge([$field => null]);
                }
            }
        }
    }
}