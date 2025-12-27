<?php

namespace App\Http\Requests\AiAssessment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use App\Enums\ModelType;
use App\Enums\RegulatoryClassification;

class StoreAiAssessmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('create', \App\Models\AiAssessment::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'facility_id' => 'required|integer|exists:facilities,id',
            'clinical_encounter_id' => 'required|integer|exists:clinical_encounters,id',
            'visit_id' => 'required|integer|exists:visits,id',
            'patient_id' => 'required|integer|exists:patients,id',
            
            // AI model identification
            'ai_model_name' => 'required|string|max:200',
            'ai_model_version' => 'required|string|max:50',
            'ai_model_vendor' => 'nullable|string|max:200',
            'model_type' => 'required|string|in:' . implode(',', array_column(ModelType::cases(), 'value')),
            
            // Regulatory compliance
            'is_fda_cleared' => 'boolean',
            'fda_clearance_number' => 'nullable|string|max:100|required_if:is_fda_cleared,true',
            'is_ce_marked' => 'boolean',
            'ce_marking_number' => 'nullable|string|max:100|required_if:is_ce_marked,true',
            'regulatory_classification' => 'nullable|string|in:' . implode(',', array_column(RegulatoryClassification::cases(), 'value')),
            
            // Input data
            'input_features' => 'required|array',
            'input_features.*' => 'present',
            'input_data_sources' => 'nullable|array',
            
            // AI output
            'output_predictions' => 'required|array',
            'output_confidence_scores' => 'required|array',
            'recommendations' => 'required|array',
            'risk_scores' => 'nullable|array',
            'alternative_suggestions' => 'nullable|array',
            
            // Explainability (XAI - Explainable AI)
            'feature_importance' => 'nullable|array',
            'explanation_text' => 'nullable|string',
            'supporting_evidence' => 'nullable|array',
            
            // Human review & validation
            'human_review_status' => 'sometimes|string|in:pending_review,accepted,modified,rejected,overridden,not_applicable',
            'reviewed_by_staff_id' => 'nullable|integer|exists:staff,id',
            'reviewed_at' => 'nullable|date',
            'review_notes' => 'nullable|string',
            'modifications_made' => 'nullable|array',
            'rejection_reason' => 'nullable|string|required_if:human_review_status,rejected',
            
            // Clinical action taken
            'recommendation_implemented' => 'nullable|boolean',
            'actions_taken' => 'nullable|array',
            'reason_not_implemented' => 'nullable|string',
            
            // Performance tracking
            'clinical_outcome_recorded' => 'boolean',
            'actual_outcome' => 'nullable|array|required_if:clinical_outcome_recorded,true',
            'prediction_accuracy' => 'nullable|numeric|min:0|max:1',
            
            // Safety & monitoring
            'adverse_event_flagged' => 'boolean',
            'adverse_event_description' => 'nullable|string|required_if:adverse_event_flagged,true',
            'safety_alerts' => 'nullable|array',
            
            // Processing metadata
            'processing_time_ms' => 'nullable|integer|min:0',
            'processing_server' => 'nullable|string|max:100',
            'assessed_at' => 'required|date',
            
            // Metadata
            'metadata' => 'nullable|array',
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
            'facility_id.required' => 'Facility ID is required.',
            'facility_id.exists' => 'The selected facility does not exist.',
            'clinical_encounter_id.required' => 'Clinical encounter ID is required.',
            'clinical_encounter_id.exists' => 'The selected clinical encounter does not exist.',
            'patient_id.required' => 'Patient ID is required.',
            'patient_id.exists' => 'The selected patient does not exist.',
            'ai_model_name.required' => 'AI model name is required.',
            'ai_model_version.required' => 'AI model version is required.',
            'model_type.required' => 'Model type is required.',
            'model_type.in' => 'Invalid model type selected.',
            'input_features.required' => 'Input features are required.',
            'output_predictions.required' => 'Output predictions are required.',
            'output_confidence_scores.required' => 'Output confidence scores are required.',
            'recommendations.required' => 'Recommendations are required.',
            'assessed_at.required' => 'Assessment timestamp is required.',
            'assessed_at.date' => 'Assessment timestamp must be a valid date.',
            'fda_clearance_number.required_if' => 'FDA clearance number is required when FDA clearance is claimed.',
            'ce_marking_number.required_if' => 'CE marking number is required when CE marking is claimed.',
            'rejection_reason.required_if' => 'Rejection reason is required when rejecting an assessment.',
            'actual_outcome.required_if' => 'Actual outcome is required when clinical outcome is recorded.',
            'adverse_event_description.required_if' => 'Adverse event description is required when flagging an adverse event.',
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
            'facility_id' => 'facility',
            'clinical_encounter_id' => 'clinical encounter',
            'patient_id' => 'patient',
            'ai_model_name' => 'AI model name',
            'ai_model_version' => 'AI model version',
            'model_type' => 'model type',
            'input_features' => 'input features',
            'output_predictions' => 'output predictions',
            'output_confidence_scores' => 'confidence scores',
            'assessed_at' => 'assessment timestamp',
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param Validator $validator
     * @return void
     *
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator): void
    {
        $errors = $validator->errors();
        
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $errors->messages(),
                'error_code' => 'VALIDATION_FAILED'
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY)
        );
    }

    /**
     * Handle a failed authorization attempt.
     *
     * @return void
     *
     * @throws HttpResponseException
     */
    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'You are not authorized to create AI assessments.',
                'error_code' => 'UNAUTHORIZED'
            ], JsonResponse::HTTP_FORBIDDEN)
        );
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        // Generate UUID if not provided
        if (!$this->has('assessment_uuid')) {
            $this->merge([
                'assessment_uuid' => (string) \Illuminate\Support\Str::uuid(),
            ]);
        }

        // Set default values
        $this->merge([
            'is_fda_cleared' => $this->boolean('is_fda_cleared', false),
            'is_ce_marked' => $this->boolean('is_ce_marked', false),
            'clinical_outcome_recorded' => $this->boolean('clinical_outcome_recorded', false),
            'adverse_event_flagged' => $this->boolean('adverse_event_flagged', false),
            'human_review_status' => $this->input('human_review_status', 'pending_review'),
        ]);

        // Validate JSON fields
        $jsonFields = [
            'input_features',
            'input_data_sources',
            'output_predictions',
            'output_confidence_scores',
            'recommendations',
            'risk_scores',
            'alternative_suggestions',
            'feature_importance',
            'supporting_evidence',
            'modifications_made',
            'actions_taken',
            'actual_outcome',
            'safety_alerts',
            'metadata'
        ];

        foreach ($jsonFields as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                try {
                    $decoded = json_decode($this->input($field), true, 512, JSON_THROW_ON_ERROR);
                    $this->merge([$field => $decoded]);
                } catch (\JsonException $e) {
                    // Let validation handle the error
                }
            }
        }
    }
}