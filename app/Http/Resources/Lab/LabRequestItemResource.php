<?php

namespace App\Http\Resources\Lab;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;

class LabRequestItemResource extends JsonResource
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
            'item_uuid' => $this->item_uuid,
            'lab_request_id' => $this->lab_request_id,
            'lab_test_id' => $this->lab_test_id,
            'status' => $this->status,
            'sample_type' => $this->sample_type,
            'sample_identifier' => $this->sample_identifier,
            
            // Sample Collection tracking
            'collected_at' => $this->collected_at?->toISOString(),
            'collected_by_staff_id' => $this->collected_by_staff_id,
            'collected_by' => $this->whenLoaded('collectedBy', function () {
                return $this->collectedBy ? [
                    'id' => $this->collectedBy->id,
                    'staff_uuid' => $this->collectedBy->staff_uuid,
                    'name' => $this->collectedBy->user?->full_name ?? null,
                    'professional_title' => $this->collectedBy->professional_title,
                ] : null;
            }),
            
            // Processing tracking
            'started_at' => $this->started_at?->toISOString(),
            'started_by_staff_id' => $this->started_by_staff_id,
            'started_by' => $this->whenLoaded('startedBy', function () {
                return $this->startedBy ? [
                    'id' => $this->startedBy->id,
                    'staff_uuid' => $this->startedBy->staff_uuid,
                    'name' => $this->startedBy->user?->full_name ?? null,
                    'professional_title' => $this->startedBy->professional_title,
                ] : null;
            }),
            
            // Completion tracking
            'completed_at' => $this->completed_at?->toISOString(),
            'completed_by_staff_id' => $this->completed_by_staff_id,
            'completed_by' => $this->whenLoaded('completedBy', function () {
                return $this->completedBy ? [
                    'id' => $this->completedBy->id,
                    'staff_uuid' => $this->completedBy->staff_uuid,
                    'name' => $this->completedBy->user?->full_name ?? null,
                    'professional_title' => $this->completedBy->professional_title,
                ] : null;
            }),
            
            // Verification tracking
            'verified_by_staff_id' => $this->verified_by_staff_id,
            'verified_at' => $this->verified_at?->toISOString(),
            'verified_by' => $this->whenLoaded('verifiedBy', function () {
                return $this->verifiedBy ? [
                    'id' => $this->verifiedBy->id,
                    'staff_uuid' => $this->verifiedBy->staff_uuid,
                    'name' => $this->verifiedBy->user?->full_name ?? null,
                    'professional_title' => $this->verifiedBy->professional_title,
                ] : null;
            }),
            
            // Cancellation tracking
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'cancelled_by_staff_id' => $this->cancelled_by_staff_id,
            'cancelled_by' => $this->whenLoaded('cancelledBy', function () {
                return $this->cancelledBy ? [
                    'id' => $this->cancelledBy->id,
                    'staff_uuid' => $this->cancelledBy->staff_uuid,
                    'name' => $this->cancelledBy->user?->full_name ?? null,
                    'professional_title' => $this->cancelledBy->professional_title,
                ] : null;
            }),

            // Creation tracking
            'created_by' => $this->whenLoaded('createdBy', function () {
                return $this->createdBy ? [
                    'id' => $this->createdBy->id,
                    'staff_uuid' => $this->createdBy->staff_uuid,
                    'name' => $this->createdBy->user?->full_name ?? null,
                    'professional_title' => $this->createdBy->professional_title,
                ] : null;
            }),
            
            'result_flag' => $this->result_flag,
            'notes' => $this->notes,
            'cancellation_reason' => $this->cancellation_reason,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
            
            // Relationships
            'lab_request' => new LabRequestResource($this->whenLoaded('labRequest')),
            'lab_test' => new LabTestResource($this->whenLoaded('labTest')),
            'results' => LabResultResource::collection($this->whenLoaded('results')),
            'primary_result' => new LabResultResource($this->whenLoaded('primaryResult')),
            
            // Statistics
            'results_count' => $this->whenCounted('results'),
            'turnaround_time_minutes' => $this->turnaround_time_minutes,
            'collection_to_completion_minutes' => $this->collection_to_completion_minutes,
            
            // Helper attributes
            'status_label' => $this->status_label,
            'result_flag_label' => $this->result_flag_label,
            'status_badge_color' => $this->status_badge_color,
            'result_flag_badge_color' => $this->result_flag_badge_color,
            'is_pending' => $this->isPending(),
            'is_sample_collected' => $this->isSampleCollected(),
            'is_in_progress' => $this->isInProgress(),
            'is_completed' => $this->isCompleted(),
            'is_verified' => $this->isVerified(),
            'is_cancelled' => $this->isCancelled(),
            'is_result_normal' => $this->isResultNormal(),
            'is_result_abnormal' => $this->isResultAbnormal(),
            'is_result_critical' => $this->isResultCritical(),
            'all_results_verified' => $this->areAllResultsVerified(),
        ];
    }
}