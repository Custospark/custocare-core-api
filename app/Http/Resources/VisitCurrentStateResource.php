<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VisitCurrentStateResource extends JsonResource
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
            'visit_id' => $this->visit_id,
            'facility_id' => $this->facility_id,
            'patient_id' => $this->patient_id,
            
            // Location & phase information
            'current_department' => $this->whenLoaded('currentDepartment', function () {
                return new DepartmentResource($this->currentDepartment);
            }),
            'current_department_id' => $this->current_department_id,
            'current_phase' => $this->current_phase,
            'current_phase_label' => $this->current_phase_label,
            
            // Wait time tracking
            'waiting_since' => $this->waiting_since?->toISOString(),
            'total_wait_minutes' => $this->total_wait_minutes,
            'current_phase_duration_minutes' => $this->current_phase_duration_minutes,
            'current_wait_time_minutes' => $this->calculateCurrentWaitTime(),
            
            // Next action information
            'next_scheduled_action_at' => $this->next_scheduled_action_at?->toISOString(),
            'next_action_type' => $this->next_action_type,
            'pending_tasks' => $this->pending_tasks,
            'pending_tasks_count' => $this->pending_tasks_count,
            
            // Critical alerts
            'critical_alerts' => $this->critical_alerts,
            'has_critical_alerts' => $this->has_critical_alerts,
            'acuity_score' => $this->acuity_score,
            'acuity_label' => $this->getAcuityLabel($this->acuity_score),
            
            // Staff assignment
            'staff_assigned_ids' => $this->staff_assigned_ids,
            'primary_provider' => $this->whenLoaded('primaryProvider', function () {
                return new StaffResource($this->primaryProvider);
            }),
            'primary_provider_staff_id' => $this->primary_provider_staff_id,
            'primary_nurse' => $this->whenLoaded('primaryNurse', function () {
                return new StaffResource($this->primaryNurse);
            }),
            'primary_nurse_staff_id' => $this->primary_nurse_staff_id,
            
            // Clinical snapshot
            'recent_vitals_last_reading' => $this->recent_vitals_last_reading,
            'vitals_last_recorded_at' => $this->vitals_last_recorded_at?->toISOString(),
            'active_orders' => $this->active_orders,
            'active_orders_count' => $this->active_orders_count,
            
            // Estimated completion
            'estimated_completion_time' => $this->estimated_completion_time?->toISOString(),
            'estimated_minutes_remaining' => $this->estimated_minutes_remaining,
            
            // Update tracking
            'last_event_at' => $this->last_event_at?->toISOString(),
            'last_event_id' => $this->last_event_id,
            'materialized_at' => $this->materialized_at?->toISOString(),
            
            // Relationships (loaded on demand)
            'visit' => $this->whenLoaded('visit', function () {
                return new VisitResource($this->visit);
            }),
            'facility' => $this->whenLoaded('facility', function () {
                return new FacilityResource($this->facility);
            }),
            'patient' => $this->whenLoaded('patient', function () {
                return new PatientResource($this->patient);
            }),
            
            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Get acuity label based on score.
     *
     * @param int $score
     * @return string
     */
    private function getAcuityLabel(int $score): string
    {
        $labels = [
            1 => 'Low (Routine)',
            2 => 'Low-Moderate',
            3 => 'Moderate',
            4 => 'High',
            5 => 'Critical',
        ];
        
        return $labels[$score] ?? 'Unknown';
    }

    /**
     * Get additional data that should be returned with the resource array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'success' => true,
            'message' => 'Visit current state retrieved successfully.',
            'meta' => [
                'api_version' => '1.0',
                'timestamp' => now()->toISOString(),
            ],
        ];
    }
}