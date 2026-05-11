<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Visit Resource
 *
 * Transforms Visit model to API response format
 */
class VisitResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'visit_uuid' => $this->visit_uuid,
            'patient_id' => $this->patient_id,
            'facility_id' => $this->facility_id,
            'facility' => $this->whenLoaded('facility', new FacilityResource($this->facility)),
            'patient' => $this->whenLoaded('patient', new PatientResource($this->patient)),
            'visit_type' => $this->visit_type,
            'visit_subtype' => $this->visit_subtype,
            'acuity_score' => $this->acuity_score,
            'chief_complaints' => $this->chief_complaints,
            'symptoms_on_arrival' => $this->symptoms_on_arrival,
            'patient_reported_history' => $this->patient_reported_history,
            'arrived_at' => $this->arrived_at,
            'registered_at' => $this->registered_at,
            'mode_of_arrival' => $this->mode_of_arrival,
            'accompanying_person' => $this->accompanying_person,
            'referring_facility' => $this->whenLoaded('referringFacility', new FacilityResource($this->referringFacility)),
            'referring_provider' => $this->whenLoaded('referringProvider', new StaffResource($this->referringProvider)),
            'external_referral_id' => $this->external_referral_id,
            'referral_reason' => $this->referral_reason,
            'current_department' => $this->whenLoaded('currentDepartment', new DepartmentResource($this->currentDepartment)),
            'current_phase' => $this->current_phase,
            'care_delivery_workflow' => $this->care_delivery_workflow,
            'waiting_since' => $this->waiting_since,
            'clinical_care_started_at' => $this->clinical_care_started_at,
            'clinical_care_ended_at' => $this->clinical_care_ended_at,
            'expected_duration_minutes' => $this->expected_duration_minutes,
            'actual_duration_minutes' => $this->actual_duration_minutes,
            'scheduled_appointment_id' => $this->scheduled_appointment_id,
            'is_walk_in' => $this->is_walk_in,
            'scheduled_time' => $this->scheduled_time,
            'insurance_preauth_id' => $this->insurance_preauth_id,
            'insurance_verification_status' => $this->insurance_verification_status,
            'insurance_verified_at' => $this->insurance_verified_at,
            'vital_signs_summary' => $this->vital_signs_summary,
            'diagnosis_codes' => $this->diagnosis_codes,
            'procedure_codes' => $this->procedure_codes,
            'medications_administered' => $this->medications_administered,
            'discharged_at' => $this->discharged_at,
            'discharged_by' => $this->whenLoaded('dischargedBy', new StaffResource($this->dischargedBy)),
            'discharge_disposition' => $this->discharge_disposition,
            'discharge_instructions' => $this->discharge_instructions,
            'discharge_medications' => $this->discharge_medications,
            'followup_scheduled_at' => $this->followup_scheduled_at,
            'followup_provider' => $this->whenLoaded('followupProvider', new StaffResource($this->followupProvider)),
            'sentinel_event_flagged' => $this->sentinel_event_flagged,
            'safety_alerts' => $this->safety_alerts,
            'requires_interpreter' => $this->requires_interpreter,
            'interpreter_language' => $this->interpreter_language,
            'isolation_required' => $this->isolation_required,
            'isolation_type' => $this->isolation_type,
            'estimated_total_charges' => $this->estimated_total_charges,
            'patient_estimated_responsibility' => $this->patient_estimated_responsibility,
            'payment_status' => $this->payment_status,
            'status' => $this->status,
            'cancellation_reason' => $this->cancellation_reason,
            'cancelled_at' => $this->cancelled_at,
            'created_by' => $this->whenLoaded('createdBy', new StaffResource($this->createdBy)),
            'updated_by' => $this->whenLoaded('updatedBy', new StaffResource($this->updatedBy)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'metadata' => $this->metadata,
            'links' => [
                // 'self' => route('visits.show', $this->visit_uuid),
                'patient' => $this->patient_id ? route('patients.show', $this->patient_id) : null,
                'facility' => $this->facility_id ? route('facilities.show', $this->facility_id) : null,
            ],
        ];
    }

    /**
     * Customize the outgoing response for the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Http\Response  $response
     * @return void
     */
    public function withResponse($request, $response)
    {
        $response->header('Content-Type', 'application/json');
    }

    /**
     * Add additional meta data to the resource response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function with($request)
    {
        return [
            'success' => true,
            'message' => 'Visit retrieved successfully.',
            'meta' => [
                'version' => '1.0',
                'timestamp' => now()->toIso8601String(),
            ],
        ];
    }
}