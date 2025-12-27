<?php

namespace App\Http\Requests\AiAssessment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use App\Enums\ModelType;
USE Illuminate\Support\Facades\Auth;
use App\Enums\RegulatoryClassification;
use App\Enums\HumanReviewStatus;

class UpdateAiAssessmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        $aiAssessment = $this->route('ai_assessment');
        return $this->user() && $this->user()->can('update', $aiAssessment);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // AI model identification updates
            'ai_model_name' => 'sometimes|string|max:200',
            'ai_model_version' => 'sometimes|string|max:50',
            'ai_model_vendor' => 'nullable|string|max:200',
            'model_type' => 'sometimes|string|in:' . implode(',', array_column(ModelType::cases(), 'value')),
            
            // Regulatory compliance updates
            'is_fda_cleared' => 'sometimes|boolean',
            'fda_clearance_number' => 'nullable|string|max:100|required_if:is_fda_cleared,true',
            'is_ce_marked' => 'sometimes|boolean',
            'ce_marking_number' => 'nullable|string|max:100|required_if:is_ce_marked,true',
            'regulatory_classification' => 'nullable|string|in:' . implode(',', array_column(RegulatoryClassification::cases(), 'value')),
            
            // Input data updates (restricted)
            'input_features' => 'sometimes|array',
            'input_features.*' => 'present',
            'input_data_sources' => 'nullable|array',
            
            // AI output updates (restricted)
            'output_predictions' => 'sometimes|array',
            'output_confidence_scores' => 'sometimes|array',
            'recommendations' => 'sometimes|array',
            'risk_scores' => 'nullable|array',
            'alternative_suggestions' => 'nullable|array',
            
            // Explainability updates
            'feature_importance' => 'nullable|array',
            'explanation_text' => 'nullable|string',
            'supporting_evidence' => 'nullable|array',
            
            // Human review & validation updates
            'human_review_status' => 'sometimes|string|in:' . implode(',', array_column(HumanReviewStatus::cases(), 'value')),
            'reviewed_by_staff_id' => 'nullable|integer|exists:staff,id',
            'reviewed_at' => 'nullable|date',
            'review_notes' => 'nullable|string',
            'modifications_made' => 'nullable|array',
            'rejection_reason' => 'nullable|string|required_if:human_review_status,rejected',
            
            // Clinical action taken updates
            'recommendation_implemented' => 'nullable|boolean',
            'actions_taken' => 'nullable|array',
            'reason_not_implemented' => 'nullable|string',
            
            // Performance tracking updates
            'clinical_outcome_recorded' => 'sometimes|boolean',
            'actual_outcome' => 'nullable|array|required_if:clinical_outcome_recorded,true',
            'prediction_accuracy' => 'nullable|numeric|min:0|max:1',
            
            // Safety & monitoring updates
            'adverse_event_flagged' => 'sometimes|boolean',
            'adverse_event_description' => 'nullable|string|required_if:adverse_event_flagged,true',
            'safety_alerts' => 'nullable|array',
            
            // Processing metadata updates
            'processing_time_ms' => 'nullable|integer|min:0',
            'processing_server' => 'nullable|string|max:100',
            
            // Metadata updates
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
            'fda_clearance_number.required_if' => 'FDA clearance number is required when FDA clearance is claimed.',
            'ce_marking_number.required_if' => 'CE marking number is required when CE marking is claimed.',
            'rejection_reason.required_if' => 'Rejection reason is required when rejecting an assessment.',
            'actual_outcome.required_if' => 'Actual outcome is required when clinical outcome is recorded.',
            'adverse_event_description.required_if' => 'Adverse event description is required when flagging an adverse event.',
            'human_review_status.in' => 'Invalid human review status.',
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
                'message' => 'You are not authorized to update this AI assessment.',
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
        // Prevent updating immutable fields
        $immutableFields = [
            'assessment_uuid',
            'facility_id',
            'clinical_encounter_id',
            'visit_id',
            'patient_id',
            'input_features_hash',
            'assessed_at',
            'created_at',
        ];

        foreach ($immutableFields as $field) {
            if ($this->has($field)) {
                $this->offsetUnset($field);
            }
        }

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

        // Set reviewed_by_staff_id if human_review_status is being updated and user is authenticated
        if ($this->has('human_review_status') && auth::check()) {
            $this->merge([
                'reviewed_by_staff_id' => auth::id(),
                'reviewed_at' => now(),
            ]);
        }
    }
}