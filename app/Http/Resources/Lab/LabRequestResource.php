<?php

namespace App\Http\Resources\Lab;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LabRequestResource extends JsonResource
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
            'request_uuid' => $this->request_uuid,
            'visit_id' => $this->visit_id,
            'patient_id' => $this->patient_id,
            'facility_id' => $this->facility_id,
            'requested_by_staff_id' => $this->requested_by_staff_id,
            'priority' => $this->priority,
            'status' => $this->status,
            'clinical_notes' => $this->clinical_notes,
            'diagnosis_context' => $this->diagnosis_context,
            'requested_at' => $this->requested_at?->toISOString(),
            'collected_at' => $this->collected_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'reviewed_by_staff_id' => $this->reviewed_by_staff_id,
            'cancellation_reason' => $this->cancellation_reason,
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
            
            // Relationships
            'visit' => $this->whenLoaded('visit', function () {
                return [
                    'id' => $this->visit->id,
                    'visit_uuid' => $this->visit->visit_uuid,
                    'visit_type' => $this->visit->visit_type,
                    'visit_phase' => $this->visit->current_phase,
                ];
            }),
            
            'patient' => $this->whenLoaded('patient', function () {
                return [
                    'id' => $this->patient->id,
                    'patient_uuid' => $this->patient->patient_uuid,
                    'full_name' => $this->patient->user->full_name ?? null,
                    'medical_record_number' => $this->patient->medical_record_number_encrypted,
                ];
            }),
            
            'facility' => $this->whenLoaded('facility', function () {
                return [
                    'id' => $this->facility->id,
                    'facility_uuid' => $this->facility->facility_uuid,
                    'facility_name' => $this->facility->facility_name,
                ];
            }),
            
            'requested_by' => $this->whenLoaded('requestedBy', function () {
                return [
                    'id' => $this->requestedBy->id,
                    'staff_uuid' => $this->requestedBy->staff_uuid,
                    'name' => $this->requestedBy->user->full_name ?? null,
                    'professional_title' => $this->requestedBy->professional_title,
                ];
            }),
            
            'reviewed_by' => $this->whenLoaded('reviewedBy', function () {
                return [
                    'id' => $this->reviewedBy->id,
                    'staff_uuid' => $this->reviewedBy->staff_uuid,
                    'name' => $this->reviewedBy->user->full_name ?? null,
                ];
            }),
            
            'items' => LabRequestItemResource::collection($this->whenLoaded('items')),
            
            // Statistics
            'items_count' => $this->whenCounted('items'),
            'progress_percentage' => $this->progress_percentage,
            'completed_items_count' => $this->items->whereIn('status', ['completed', 'verified'])->count(),
            'verified_items_count' => $this->items->where('status', 'verified')->count(),
            
            // Helper attributes
            'priority_label' => $this->priority_label,
            'status_label' => $this->status_label,
            'priority_badge_color' => $this->priority_badge_color,
            'status_badge_color' => $this->status_badge_color,
            'is_pending' => $this->isPending(),
            'is_in_progress' => $this->isInProgress(),
            'is_completed' => $this->isCompleted(),
            'is_reviewed' => $this->isReviewed(),
            'is_cancelled' => $this->isCancelled(),
            'is_stat' => $this->isStat(),
            'is_urgent' => $this->isUrgent(),
            'is_routine' => $this->isRoutine(),
            'all_items_completed' => $this->areAllItemsCompleted(),
            
            // URLs
            'urls' => [
                'self' => route('api.lab-requests.show', $this->request_uuid),
                'items' => route('api.lab-requests.items.index', $this->request_uuid),
                'patient' => route('api.patients.show', $this->patient_id),
                'visit' => route('api.visits.show', $this->visit_id),
            ],
        ];
    }
}