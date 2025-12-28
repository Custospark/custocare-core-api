<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepartmentQueueViewResource extends JsonResource
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
            'department_id' => $this->department_id,
            'queue_type' => $this->queue_type,
            'queue_type_display' => $this->getQueueTypeDisplayName(),
            
            // Patient metrics
            'patients_waiting_count' => $this->patients_waiting_count,
            'patients_in_treatment_count' => $this->patients_in_treatment_count,
            'total_active_patients' => $this->total_active_patients,
            
            // Wait time statistics
            'wait_times' => [
                'average_minutes' => $this->average_wait_minutes,
                'median_minutes' => $this->median_wait_minutes,
                'longest_minutes' => $this->longest_wait_minutes,
                'longest_waiting_visit_id' => $this->longest_waiting_visit_id,
            ],
            
            // Patient information
            'next_patient_ids' => $this->next_patient_ids ?? [],
            'critical_patients' => $this->critical_patients ?? [],
            
            // Staffing information
            'staffing' => [
                'available_count' => $this->staff_available_count,
                'total_count' => $this->staff_total_count,
                'available_staff_ids' => $this->available_staff_ids ?? [],
                'staffing_ratio' => $this->staff_total_count > 0 ? 
                    round(($this->staff_available_count / $this->staff_total_count) * 100, 2) : 0,
            ],
            
            // Capacity information
            'capacity' => [
                'percentage' => $this->capacity_percentage,
                'bed_utilization_percentage' => $this->bed_utilization_percentage,
                'status' => $this->capacity_status,
                'status_display' => $this->getCapacityStatusDisplayName(),
                'is_critical' => $this->isCritical(),
                'level' => $this->capacity_level,
            ],
            
            // Predictions
            'predictions' => [
                'wait_times' => $this->predicted_wait_times ?? [],
                'next_available_at' => $this->predicted_next_available_at?->toIso8601String(),
            ],
            
            // Metadata
            'snapshot_at' => $this->snapshot_at->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            
            // Relationships (loaded when needed)
            'facility' => $this->whenLoaded('facility', function () {
                return new FacilityResource($this->facility);
            }),
            
            'department' => $this->whenLoaded('department', function () {
                return new DepartmentResource($this->department);
            }),
            
            // Derived metrics
            'alerts' => $this->when($request->has('include_alerts'), function () {
                return [
                    'has_excessive_wait_times' => $this->hasExcessiveWaitTimes(),
                    'recommended_staffing' => $this->calculateRecommendedStaffing(),
                ];
            }),
            
            // Links
            'links' => [
                'self' => route('api.department-queue-views.show', $this->id),
                'department' => route('api.departments.show', $this->department_id),
                'facility' => route('api.facilities.show', $this->facility_id),
            ],
        ];
    }

    /**
     * Get display name for queue type
     */
    private function getQueueTypeDisplayName(): string
    {
        $displayNames = [
            'triage' => 'Triage',
            'consultation' => 'Consultation',
            'procedures' => 'Procedures',
            'diagnostic_imaging' => 'Diagnostic Imaging',
            'laboratory' => 'Laboratory',
            'pharmacy' => 'Pharmacy',
            'discharge' => 'Discharge',
        ];

        return $displayNames[$this->queue_type] ?? ucfirst(str_replace('_', ' ', $this->queue_type));
    }

    /**
     * Get display name for capacity status
     */
    private function getCapacityStatusDisplayName(): string
    {
        $displayNames = [
            'normal' => 'Normal',
            'busy' => 'Busy',
            'critical' => 'Critical',
            'at_capacity' => 'At Capacity',
        ];

        return $displayNames[$this->capacity_status] ?? ucfirst($this->capacity_status);
    }

    /**
     * Calculate recommended staffing (simplified calculation)
     */
    private function calculateRecommendedStaffing(): int
    {
        $baseStaff = 2;
        $patientsPerStaff = 3;
        
        $recommended = $baseStaff + ceil($this->patients_waiting_count / $patientsPerStaff);
        
        return min($recommended, 10);
    }

    /**
     * Add metadata to the resource response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Http\JsonResponse  $response
     * @return void
     */
    public function withResponse($request, $response)
    {
        $response->header('X-API-Version', '1.0')
                 ->header('X-API-Resource', 'DepartmentQueueView');
    }
}