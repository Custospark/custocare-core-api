<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConsultationResource extends JsonResource
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
            'patient_number' => $this->patient->patient_uuid,
            'requesting_staff_id' => $this->requesting_staff_id,
            'consultant_staff_id' => $this->consultant_staff_id,
            
            // Consultation Details
            'specialty_required' => $this->specialty_required,
            'consultation_type' => $this->consultation_type,
            'consultation_type_text' => $this->consultation_type_text,
            'priority' => $this->priority,
            'priority_text' => $this->priority_text,
            'priority_color' => $this->priority_color,
            'clinical_question' => $this->clinical_question,
            'background_information' => $this->background_information,
            'attached_documents' => $this->attached_documents,
            
            // Consultation Response
            'findings' => $this->findings,
            'recommendations' => $this->recommendations,
            'recommended_orders' => $this->recommended_orders,
            'consultant_notes' => $this->consultant_notes,
            
            // Workflow Status
            'request_status' => $this->request_status,
            'status_text' => $this->status_text,
            'status_color' => $this->status_color,
            'requested_at' => $this->requested_at?->toISOString(),
            'responded_at' => $this->responded_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'decline_reason' => $this->decline_reason,
            'cancellation_reason' => $this->cancellation_reason,
            
            // Scheduling
            'scheduled_for' => $this->scheduled_for?->toISOString(),
            'duration_minutes' => $this->duration_minutes,
            'location' => $this->location,
            
            // Follow-up
            'requires_followup' => $this->requires_followup,
            'followup_by' => $this->followup_by?->toISOString(),
            'followup_instructions' => $this->followup_instructions,
            
            // Custom Fields
            'custom_fields' => $this->custom_fields,
            'satisfaction_metrics' => $this->satisfaction_metrics,
            
            // Calculated Fields
            'is_pending' => $this->isPending(),
            'is_accepted' => $this->isAccepted(),
            'is_declined' => $this->isDeclined(),
            'is_completed' => $this->isCompleted(),
            'is_cancelled' => $this->isCancelled(),
            'is_urgent' => $this->isUrgent(),
            'is_overdue' => $this->isOverdue(),
            'requires_followup_flag' => $this->requiresFollowup(),
            'response_time_hours' => $this->response_time,
            'completion_time_hours' => $this->completion_time,
            
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
                    'full_name' => ($this->patient->user->first_name ?? '') . ' ' . ($this->patient->user->last_name ?? ''),
                ];
            }),
            
            'requesting_staff' => $this->whenLoaded('requestingStaff', function () {
                return [
                    'id' => $this->requestingStaff->id,
                    'first_name' => $this->requestingStaff->user->first_name ?? null,
                    'last_name' => $this->requestingStaff->user->last_name ?? null,
                    'full_name' => ($this->requestingStaff->user->first_name ?? '') . ' ' . ($this->requestingStaff->user->last_name ?? ''),
                ];
            }),
            
            'consultant_staff' => $this->whenLoaded('consultantStaff', function () {
                return [
                    'id' => $this->consultantStaff->id,
                    'first_name' => $this->consultantStaff->user->first_name ?? null,
                    'last_name' => $this->consultantStaff->user->last_name ?? null,
                    'full_name' => ($this->consultantStaff->user->first_name ?? '') . ' ' . ($this->consultantStaff->user->last_name ?? ''),
                ];
            }),
        ];
    }
}