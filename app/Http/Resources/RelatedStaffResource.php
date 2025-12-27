<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClinicalEncounterResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // Core identifiers
            'id' => $this->id,
            'uuid' => $this->encounter_uuid,
            'facility_id' => $this->facility_id,
            
            // Classification
            'encounter_type' => $this->encounter_type,
            'encounter_type_label' => $this->getEncounterTypeLabel(),
            
            // Relationships (using nested resources)
            'visit' => new VisitResource($this->whenLoaded('visit')),
            'patient' => new PatientResource($this->whenLoaded('patient')),
            'primary_provider' => new StaffResource($this->whenLoaded('primaryProvider')),
            'supervising_provider' => new StaffResource($this->whenLoaded('supervisingProvider')),
            'department' => new DepartmentResource($this->whenLoaded('department')),
            'facility' => new FacilityResource($this->whenLoaded('facility')),
            'amended_from' => new ClinicalEncounterResource($this->whenLoaded('amendedFrom')),
            'created_by' => new StaffResource($this->whenLoaded('createdBy')),
            'updated_by' => new StaffResource($this->whenLoaded('updatedBy')),
            
            // SOAP Note Components
            'subjective' => [
                'assessment' => $this->subjective_assessment,
                'chief_complaints' => $this->chief_complaints,
                'history_present_illness' => $this->history_present_illness,
                'review_of_systems' => $this->review_of_systems,
                'patient_concerns' => $this->patient_concerns,
            ],
            
            'objective' => [
                'findings' => $this->objective_findings,
                'vital_signs' => $this->vital_signs,
                'physical_examination' => $this->physical_examination,
                'laboratory_results' => $this->laboratory_results,
                'imaging_results' => $this->imaging_results,
                'diagnostic_test_results' => $this->diagnostic_test_results,
            ],
            
            'assessment' => [
                'diagnosis_codes' => $this->assessment_diagnosis_codes,
                'clinical_impression' => $this->clinical_impression,
                'differential_diagnoses' => $this->differential_diagnoses,
                'severity_score' => $this->severity_score,
                'risk_factors' => $this->risk_factors,
                'comorbidities' => $this->comorbidities,
            ],
            
            'plan' => [
                'treatment_codes' => $this->plan_treatment_codes,
                'treatment_plan' => $this->treatment_plan,
                'medications_prescribed' => $this->medications_prescribed,
                'procedures_planned' => $this->procedures_planned,
                'referrals_ordered' => $this->referrals_ordered,
                'followup_instructions' => $this->followup_instructions,
                'next_review_scheduled_at' => $this->next_review_scheduled_at,
            ],
            
            // Additional clinical notes
            'clinical_notes' => [
                'structured' => $this->clinical_notes_structured,
                'free_text' => $this->clinical_notes_free_text,
                'provider_comments' => $this->provider_comments,
            ],
            
            // Risk & Safety
            'risk_flags' => $this->risk_flags,
            'safety_alerts' => $this->safety_alerts,
            'requires_immediate_attention' => $this->requires_immediate_attention,
            
            // Quality metrics
            'quality_metrics' => [
                'meets_quality_measures' => $this->meets_quality_measures,
                'quality_measure_codes' => $this->quality_measure_codes,
            ],
            
            // Clinical decision support
            'clinical_decision_support' => [
                'ai_assistance_used' => $this->ai_assistance_used,
                'alerts' => $this->clinical_decision_support_alerts,
            ],
            
            // Documentation status
            'documentation_status' => $this->documentation_status,
            'documentation_status_label' => $this->getDocumentationStatusLabel(),
            'documented_at' => $this->documented_at,
            'signed_at' => $this->signed_at,
            'is_signed' => $this->is_signed,
            'is_completed' => $this->is_completed,
            
            // Amendments
            'amendment' => [
                'amended_from_id' => $this->amended_from_encounter_id,
                'amendment_reason' => $this->amendment_reason,
                'amended_at' => $this->amended_at,
                'requires_amendment' => $this->requires_amendment,
            ],
            
            // Billing
            'billing' => [
                'is_billable' => $this->is_billable,
                'billing_code' => $this->billing_code,
            ],
            
            // Audit trail
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
            
            // Status indicators
            'status' => [
                'is_active' => is_null($this->deleted_at),
                'can_be_edited' => is_null($this->signed_at),
                'can_be_deleted' => is_null($this->signed_at) && is_null($this->deleted_at),
            ],
        ];
    }

    /**
     * Get encounter type label
     */
    private function getEncounterTypeLabel(): string
    {
        $labels = [
            'initial_consultation' => 'Initial Consultation',
            'followup_consultation' => 'Follow-up Consultation',
            'procedure' => 'Procedure',
            'diagnostic_review' => 'Diagnostic Review',
            'medication_review' => 'Medication Review',
            'telehealth_visit' => 'Telehealth Visit',
            'specialist_consultation' => 'Specialist Consultation',
            'pre_operative_assessment' => 'Pre-operative Assessment',
            'post_operative_followup' => 'Post-operative Follow-up',
            'discharge_summary' => 'Discharge Summary',
        ];
        
        return $labels[$this->encounter_type] ?? $this->encounter_type;
    }

    /**
     * Get documentation status label
     */
    private function getDocumentationStatusLabel(): string
    {
        $labels = [
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'signed' => 'Signed',
            'amended' => 'Amended',
            'corrected' => 'Corrected',
            'entered_in_error' => 'Entered in Error',
        ];
        
        return $labels[$this->documentation_status] ?? $this->documentation_status;
    }

    /**
     * Customize the response for the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Http\JsonResponse  $response
     * @return void
     */
    public function withResponse($request, $response): void
    {
        $response->header('X-Encounter-Type', $this->encounter_type);
        
        if ($this->requires_immediate_attention) {
            $response->header('X-Requires-Attention', 'true');
        }
        
        // Add cache headers for immutable data
        if ($this->signed_at) {
            $response->header('Cache-Control', 'public, max-age=31536000, immutable');
        }
    }
}