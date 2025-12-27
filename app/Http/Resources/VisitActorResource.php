<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VisitActorResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'facility_id' => $this->facility_id,
            'visit_id' => $this->visit_id,
            'staff_id' => $this->staff_id,
            'role_at_time' => $this->role_at_time,
            'credential_snapshot_id' => $this->credential_snapshot_id,
            'participation_type' => $this->participation_type,
            'participation_started_at' => $this->participation_started_at?->toIso8601String(),
            'participation_ended_at' => $this->participation_ended_at?->toIso8601String(),
            'time_involvement_minutes' => $this->time_involvement_minutes,
            'department_id_at_time' => $this->department_id_at_time,
            'services_performed' => $this->services_performed ?? [],
            'procedures_assisted' => $this->procedures_assisted ?? [],
            'is_billable_provider' => $this->is_billable_provider,
            'provider_charge_amount' => $this->provider_charge_amount ? (float) $this->provider_charge_amount : null,
            'is_teaching_case' => $this->is_teaching_case,
            'supervising_staff_id' => $this->supervising_staff_id,
            'metadata' => $this->metadata ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            
            // Relationships (only loaded when needed)
            'visit' => $this->whenLoaded('visit', function () {
                return new VisitResource($this->visit);
            }),
            
            'staff' => $this->whenLoaded('staff', function () {
                return new StaffResource($this->staff);
            }),
            
            'supervising_staff' => $this->whenLoaded('supervisingStaff', function () {
                return new StaffResource($this->supervisingStaff);
            }),
            
            'credential_snapshot' => $this->whenLoaded('credentialSnapshot', function () {
                return new StaffCredentialResource($this->credentialSnapshot);
            }),
            
            'facility' => $this->whenLoaded('facility', function () {
                return new FacilityResource($this->facility);
            }),
            
            // Computed properties
            'is_active' => $this->isActive(),
            'is_billable' => $this->isBillable(),
            'duration_hours' => $this->time_involvement_minutes ? round($this->time_involvement_minutes / 60, 2) : null,
        ];
    }

    /**
     * Customize the outgoing response for the resource.
     *
     * @param Request $request
     * @param \Illuminate\Http\JsonResponse $response
     * @return void
     */
    public function withResponse(Request $request, $response): void
    {
        $response->header('X-Resource-Version', '1.0.0');
    }

    /**
     * Get any additional data that should be returned with the resource array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'version' => '1.0.0',
                'api_version' => 'v1',
                'copyright' => config('app.name'),
                'authors' => ['Healthcare API Team'],
            ],
            'links' => [
                'self' => $request->fullUrl(),
                'documentation' => url('/api/docs/visit-actors'),
            ]
        ];
    }
}