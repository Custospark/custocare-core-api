<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\FacilityResource;
use App\Http\Resources\ClinicalEncounterResource;
use App\Http\Resources\VisitResource;
use App\Http\Resources\PatientResource;
use App\Http\Resources\StaffResource;

class AiAssessmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'assessment_uuid' => $this->assessment_uuid,
            
            // Relationships
            'facility' => new FacilityResource($this->whenLoaded('facility')),
            'clinical_encounter' => new ClinicalEncounterResource($this->whenLoaded('clinicalEncounter')),
            'visit' => new VisitResource($this->whenLoaded('visit')),
            'patient' => new PatientResource($this->whenLoaded('patient')),
            'reviewer' => new StaffResource($this->whenLoaded('reviewer')),
            
            // AI model identification
            'ai_model' => [
                'name' => $this->ai_model_name,
                'version' => $this->ai_model_version,
                'vendor' => $this->ai_model_vendor,
                'type' => [
                    'value' => $this->model_type->value,
                    'label' => $this->model_type->label(),
                ],
            ],
            
            // Regulatory compliance
            'regulatory_compliance' => [
                'is_fda_cleared' => $this->is_fda_cleared,
                'fda_clearance_number' => $this->fda_clearance_number,
                'is_ce_marked' => $this->is_ce_marked,
                'ce_marking_number' => $this->ce_marking_number,
                'classification' => $this->regulatory_classification ? [
                    'value' => $this->regulatory_classification->value,
                    'label' => $this->regulatory_classification->label(),
                    'is_medical_device' => $this->regulatory_classification->isMedicalDevice(),
                ] : null,
            ],
            
            // Input data
            'input_data' => [
                'features' => $this->input_features,
                'hash' => $this->input_features_hash,
                'sources' => $this->input_data_sources,
            ],
            
            // AI output
            'output' => [
                'predictions' => $this->output_predictions,
                'confidence_scores' => $this->output_confidence_scores,
                'recommendations' => $this->recommendations,
                'risk_scores' => $this->risk_scores,
                'alternative_suggestions' => $this->alternative_suggestions,
            ],
            
            // Explainability (XAI - Explainable AI)
            'explainability' => [
                'feature_importance' => $this->feature_importance,
                'explanation_text' => $this->explanation_text,
                'supporting_evidence' => $this->supporting_evidence,
            ],
            
            // Human review & validation
            'human_review' => [
                'status' => [
                    'value' => $this->human_review_status->value,
                    'label' => $this->human_review_status->label(),
                    'is_completed' => $this->human_review_status->isCompleted(),
                ],
                'reviewed_at' => $this->reviewed_at?->toISOString(),
                'review_notes' => $this->review_notes,
                'modifications_made' => $this->modifications_made,
                'rejection_reason' => $this->rejection_reason,
            ],
            
            // Clinical action taken
            'clinical_action' => [
                'recommendation_implemented' => $this->recommendation_implemented,
                'actions_taken' => $this->actions_taken,
                'reason_not_implemented' => $this->reason_not_implemented,
            ],
            
            // Performance tracking
            'performance' => [
                'clinical_outcome_recorded' => $this->clinical_outcome_recorded,
                'actual_outcome' => $this->actual_outcome,
                'prediction_accuracy' => $this->prediction_accuracy,
            ],
            
            // Safety & monitoring
            'safety' => [
                'adverse_event_flagged' => $this->adverse_event_flagged,
                'adverse_event_description' => $this->adverse_event_description,
                'safety_alerts' => $this->safety_alerts,
            ],
            
            // Processing metadata
            'processing' => [
                'time_ms' => $this->processing_time_ms,
                'server' => $this->processing_server,
            ],
            
            // Calculated attributes
            'calculated_attributes' => $this->when($request->has('include_calculated'), [
                'requires_human_review' => $this->requires_human_review,
                'is_regulatory_approved' => $this->is_regulatory_approved,
                'assessment_age_days' => $this->assessment_age_days,
                'has_high_confidence' => $this->has_high_confidence,
                'risk_level' => $this->risk_scores ? $this->calculateRiskLevel($this->risk_scores) : null,
            ]),
            
            // Timestamps
            'assessed_at' => $this->assessed_at->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            
            // Metadata
            'metadata' => $this->metadata,
            
            // Links
            'links' => [
                'self' => route('api.ai-assessments.show', $this->assessment_uuid),
                'clinical_encounter' => $this->clinical_encounter_id ? route('api.clinical-encounters.show', $this->clinical_encounter_id) : null,
                'patient' => $this->patient_id ? route('api.patients.show', $this->patient_id) : null,
                'reviewer' => $this->reviewed_by_staff_id ? route('api.staff.show', $this->reviewed_by_staff_id) : null,
            ],
        ];
    }

    /**
     * Calculate risk level from risk scores
     *
     * @param array|null $riskScores
     * @return string|null
     */
    private function calculateRiskLevel(?array $riskScores): ?string
    {
        if (empty($riskScores)) {
            return null;
        }
        
        $averageRisk = array_sum($riskScores) / count($riskScores);
        
        if ($averageRisk >= 0.8) {
            return 'high';
        } elseif ($averageRisk >= 0.5) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Customize the outgoing response for the resource.
     *
     * @param Request $request
     * @param \Illuminate\Http\JsonResponse $response
     * @return void
     */
    public function withResponse(Request $request, $response): void
    {
        $response->header('X-AI-Assessment-Version', '1.0');
        $response->header('X-Response-Time', microtime(true) - LARAVEL_START);
        
        // Add pagination metadata if resource is a collection
        if (isset($this->resource->resource) && method_exists($this->resource->resource, 'currentPage')) {
            $response->header('X-Pagination-Total', $this->resource->total());
            $response->header('X-Pagination-Per-Page', $this->resource->perPage());
            $response->header('X-Pagination-Current-Page', $this->resource->currentPage());
            $response->header('X-Pagination-Last-Page', $this->resource->lastPage());
        }
    }
}