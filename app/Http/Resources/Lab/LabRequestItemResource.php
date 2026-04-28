<?php

namespace App\Http\Resources\Lab;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'collected_at' => $this->collected_at?->toISOString(),
            'collected_by_staff_id' => $this->collected_by_staff_id,
            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'verified_by_staff_id' => $this->verified_by_staff_id,
            'verified_at' => $this->verified_at?->toISOString(),
            'result_flag' => $this->result_flag,
            'notes' => $this->notes,
            'cancellation_reason' => $this->cancellation_reason,
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
            
            // Relationships
            'lab_request' => new LabRequestResource($this->whenLoaded('labRequest')),
            'lab_test' => new LabTestResource($this->whenLoaded('labTest')),
            'collected_by' => $this->whenLoaded('collectedBy', function () {
                return [
                    'id' => $this->collectedBy->id,
                    'staff_uuid' => $this->collectedBy->staff_uuid,
                    'name' => $this->collectedBy->user->full_name ?? null,
                ];
            }),
            'verified_by' => $this->whenLoaded('verifiedBy', function () {
                return [
                    'id' => $this->verifiedBy->id,
                    'staff_uuid' => $this->verifiedBy->staff_uuid,
                    'name' => $this->verifiedBy->user->full_name ?? null,
                ];
            }),
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
            
            // URLs
            'urls' => [
                'self' => route('api.lab-request-items.show', $this->item_uuid),
                'request' => route('api.lab-requests.show', $this->labRequest->request_uuid ?? ''),
                'test' => route('api.lab-tests.show', $this->labTest->test_uuid ?? ''),
                'results' => route('api.lab-request-items.results.index', $this->item_uuid),
            ],
        ];
    }
}