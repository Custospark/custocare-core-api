<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DiagnosisResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'facility_id' => $this->facility_id,
            'visit_id' => $this->visit_id,
            'patient_id' => $this->patient_id,
            'staff_id' => $this->staff_id,
            
            // Diagnosis Data
            'diagnosis_code' => $this->diagnosis_code,
            'diagnosis_description' => $this->diagnosis_description,
            'diagnosis_type' => $this->diagnosis_type,
            'diagnosis_type_text' => $this->diagnosis_type_text,
            'certainty' => $this->certainty,
            'certainty_text' => $this->certainty_text,
            'clinical_status' => $this->clinical_status,
            'clinical_status_text' => $this->clinical_status_text,
            'clinical_notes' => $this->clinical_notes,
            'onset_date' => $this->onset_date?->toDateString(),
            'abatement_date' => $this->abatement_date?->toDateString(),
            
            // Supporting Evidence
            'supporting_evidence' => $this->supporting_evidence,
            'diagnostic_criteria_met' => $this->diagnostic_criteria_met,
            
            // Custom Fields
            'custom_fields' => $this->custom_fields,
            'coding_metadata' => $this->coding_metadata,
            
            // Workflow
            'verification_status' => $this->verification_status,
            'verified_at' => $this->verified_at?->toISOString(),
            'verified_by' => $this->verified_by,
            'dispute_reason' => $this->dispute_reason,
            
            // Status Flags
            'is_primary' => $this->isPrimary(),
            'is_secondary' => $this->isSecondary(),
            'is_active' => $this->isActive(),
            'is_resolved' => $this->isResolved(),
            'is_verified' => $this->isVerified(),
            'is_disputed' => $this->isDisputed(),
            'is_confirmed' => $this->isConfirmed(),
            
            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
            
            // Relationships (when loaded)
            'facility' => $this->whenLoaded('facility', function () {
                return [
                    'id' => $this->facility->id,
                    'name' => $this->facility->facility_name,
                    'code' => $this->facility->facility_code,
                ];
            }),
            
            'visit' => $this->whenLoaded('visit', function () {
                return [
                    'id' => $this->visit->id,
                    'visit_date_time' => $this->visit->visit_date_time?->toISOString(),
                ];
            }),
            
            'patient' => $this->whenLoaded('patient', function () {
                return [
                    'id' => $this->patient->id,
                    'first_name' => $this->patient->user->first_name ?? null,
                    'last_name' => $this->patient->user->last_name ?? null,
                    'full_name' => ($this->patient->user->first_name ?? '') . ' ' . ($this->patient->last_name ?? ''),
                ];
            }),
            
            'staff' => $this->whenLoaded('staff', function () {
                return [
                    'id' => $this->staff->id,
                    'first_name' => $this->staff->user->first_name ?? null,
                    'last_name' => $this->staff->user->last_name ?? null,
                    'full_name' => ($this->staff->user->first_name ?? '') . ' ' . ($this->staff->user->last_name ?? ''),
                ];
            }),
            
            'verifier' => $this->whenLoaded('verifier', function () {
                return [
                    'id' => $this->verifier->id,
                    'first_name' => $this->verifier->user->first_name ?? null,
                    'last_name' => $this->verifier->user->last_name ?? null,
                    'full_name' => ($this->verifier->user->first_name ?? '') . ' ' . ($this->verifier->user->last_name ?? ''),
                ];
            }),
        ];
    }
}