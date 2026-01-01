<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'patient_uuid' => $this->patient_uuid,
            'user_id' => $this->user_id,
            
            // Demographics
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'age' => $this->age,
            'biological_sex' => $this->biological_sex,
            'gender_identity' => $this->gender_identity,
            'blood_type' => $this->blood_type,
            'ethnicity' => $this->ethnicity,
            
            // Clinical information (filtered based on consent)
            'known_allergies' => $this->when(
                $this->default_consent_level === 'full',
                $this->known_allergies
            ),
            'chronic_conditions' => $this->when(
                $this->default_consent_level === 'full',
                $this->chronic_conditions
            ),
            'active_medications' => $this->when(
                $this->default_consent_level === 'full' || $this->default_consent_level === 'restricted',
                $this->active_medications
            ),
            'is_organ_donor' => $this->is_organ_donor,
            'advance_directives' => $this->when(
                $this->default_consent_level === 'full',
                $this->advance_directives
            ),
            
            // Risk stratification
            'acuity_baseline' => $this->acuity_baseline,
            'requires_isolation' => $this->requires_isolation,
            'isolation_type' => $this->isolation_type,
            
            // Consent & privacy
            'default_consent_level' => $this->default_consent_level,
            'research_participation_allowed' => $this->research_participation_allowed,
            'data_sharing_allowed' => $this->data_sharing_allowed,
            
            // Insurance
            'primary_insurance_provider' => $this->primary_insurance_provider,
            'secondary_insurance_provider' => $this->secondary_insurance_provider,
            'payment_responsibility' => $this->payment_responsibility,
            
            // Care coordination
            'primary_care_provider_staff_id' => $this->primary_care_provider_staff_id,
            'primary_care_facility_id' => $this->primary_care_facility_id,
            'last_wellness_visit_at' => $this->last_wellness_visit_at?->format('Y-m-d H:i:s'),
            'next_scheduled_appointment_at' => $this->next_scheduled_appointment_at?->format('Y-m-d H:i:s'),
            
            // Patient portal
            'portal_access_enabled' => $this->portal_access_enabled,
            'portal_terms_accepted_at' => $this->portal_terms_accepted_at?->format('Y-m-d H:i:s'),
            'preferred_language' => $this->preferred_language,
            'preferred_communication_method' => $this->preferred_communication_method,
            
            // Status tracking
            'status' => $this->status,
            'deceased_at' => $this->deceased_at?->format('Y-m-d H:i:s'),
            
            // Audit trail
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            
            // Relationships
            'user' => new UserResource($this->whenLoaded('user')),
        ];
    }

    /**
     * Get additional data that should be returned with the resource array.
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'consent_level' => $this->default_consent_level,
                'data_restrictions_applied' => $this->default_consent_level !== 'full',
                'can_update' => $this->status !== 'deceased' && $this->status !== 'merged',
            ],
        ];
    }
}