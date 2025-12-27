<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class VisitEventResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'event_uuid' => $this->event_uuid,
            'facility_id' => $this->facility_id,
            'visit_id' => $this->visit_id,
            'event_type' => $this->event_type,
            'event_payload' => $this->event_payload,
            'payload_schema_version' => $this->payload_schema_version,
            'actor_type' => $this->actor_type,
            'actor_id' => $this->actor_id,
            'actor_identifier' => $this->actor_identifier,
            'department_id_at_time' => $this->department_id_at_time,
            'system_component' => $this->system_component,
            'client_ip' => $this->client_ip,
            'client_user_agent' => $this->client_user_agent,
            'preceding_event_id' => $this->preceding_event_id,
            'integrity_hash' => $this->integrity_hash,
            'event_occurred_at' => $this->event_occurred_at->toISOString(),
            'event_recorded_at' => $this->event_recorded_at->toISOString(),
            'processing_latency_ms' => $this->processing_latency_ms,
            'created_at' => $this->created_at->toISOString(),
            'metadata' => $this->metadata,
            
            // Relationships (loaded only when requested)
            'visit' => $this->whenLoaded('visit', function () {
                return new VisitResource($this->visit);
            }),
            'preceding_event' => $this->whenLoaded('precedingEvent', function () {
                return new VisitEventResource($this->precedingEvent);
            }),
            
            // Computed properties
            'is_clinical_event' => $this->isClinicalEvent(),
            'is_visit_state_event' => $this->isVisitStateEvent(),
            'time_since_event' => Carbon::parse($this->event_occurred_at)->diffForHumans(),
            
            // Audit information
            '_links' => [
                'self' => [
                    'href' => route('api.visit-events.show', ['visit_event' => $this->event_uuid]),
                ],
                'visit' => [
                    'href' => $this->visit_id ? route('api.visits.show', ['visit' => $this->visit_id]) : null,
                ],
                'preceding_event' => [
                    'href' => $this->preceding_event_id ? route('api.visit-events.show', ['visit_event' => $this->precedingEvent->event_uuid ?? $this->preceding_event_id]) : null,
                ],
            ],
        ];
    }

    /**
     * Customize the outgoing response for the resource.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Illuminate\Http\JsonResponse $response
     * @return void
     */
    public function withResponse($request, $response): void
    {
        $response->header('X-Event-Schema-Version', '1.0');
        
        // Add cache headers for immutable events
        $response->header('Cache-Control', 'public, max-age=3600, immutable');
    }
}