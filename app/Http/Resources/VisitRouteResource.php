<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VisitRouteResource extends JsonResource
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
            
            // Routing details
            'from_department_id' => $this->from_department_id,
            'to_department_id' => $this->to_department_id,
            'routing_reason' => $this->routing_reason,
            'routing_reason_label' => $this->routing_reason_label,
            'routing_notes' => $this->routing_notes,
            'routing_staff_id' => $this->routing_staff_id,
            
            // Queue metrics
            'queue_position_at_move' => $this->queue_position_at_move,
            'estimated_wait_minutes' => $this->estimated_wait_minutes,
            'actual_wait_minutes' => $this->actual_wait_minutes,
            
            // Timing
            'routed_at' => $this->routed_at?->toIso8601String(),
            'arrived_at_department' => $this->arrived_at_department?->toIso8601String(),
            'departed_department' => $this->departed_department?->toIso8601String(),
            'actual_transfer_duration_minutes' => $this->actual_transfer_duration_minutes,
            'total_duration_minutes' => $this->total_duration_minutes,
            
            // Handoff documentation
            'handoff_summary' => $this->handoff_summary,
            'sending_staff_id' => $this->sending_staff_id,
            'receiving_staff_id' => $this->receiving_staff_id,
            'handoff_acknowledged' => $this->handoff_acknowledged,
            'handoff_acknowledged_at' => $this->handoff_acknowledged_at?->toIso8601String(),
            
            // Transport
            'transport_method' => $this->transport_method,
            'transport_method_label' => $this->transport_method_label,
            'requires_escort' => $this->requires_escort,
            
            // Status flags
            'is_active' => $this->isActive(),
            'is_complete' => $this->isComplete(),
            
            // Audit
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'metadata' => $this->metadata,
            
            // Relationships (loaded only when requested)
            'facility' => $this->whenLoaded('facility', function () {
                return new FacilityResource($this->facility);
            }),
            
            'visit' => $this->whenLoaded('visit', function () {
                return new VisitResource($this->visit);
            }),
            
            'from_department' => $this->whenLoaded('fromDepartment', function () {
                return new DepartmentResource($this->fromDepartment);
            }),
            
            'to_department' => $this->whenLoaded('toDepartment', function () {
                return new DepartmentResource($this->toDepartment);
            }),
            
            'routing_staff' => $this->whenLoaded('routingStaff', function () {
                return new UserResource($this->routingStaff);
            }),
            
            'sending_staff' => $this->whenLoaded('sendingStaff', function () {
                return new UserResource($this->sendingStaff);
            }),
            
            'receiving_staff' => $this->whenLoaded('receivingStaff', function () {
                return new UserResource($this->receivingStaff);
            }),
            
            // Links
            '_links' => [
                'self' => route('visit-routes.show', $this->id),
                'visit' => route('visits.show', $this->visit_id),
                'facility' => route('facilities.show', $this->facility_id),
            ]
        ];
    }

    /**
     * Customize the outgoing response for the resource.
     */
    public function withResponse($request, $response): void
    {
        $response->header('X-Resource-Type', 'VisitRoute');
        
        if ($this->resource instanceof \Illuminate\Pagination\AbstractPaginator) {
            $response->header('X-Total-Count', $this->resource->total());
            $response->header('X-Per-Page', $this->resource->perPage());
            $response->header('X-Current-Page', $this->resource->currentPage());
            $response->header('X-Last-Page', $this->resource->lastPage());
        }
    }

    /**
     * Additional data returned with the resource array.
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'api_version' => '1.0',
                'timestamp' => now()->toIso8601String(),
                'resource_type' => 'visit_route',
            ]
        ];
    }
}