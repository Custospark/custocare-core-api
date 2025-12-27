<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepartmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'department_uuid' => $this->department_uuid,
            'facility_id' => $this->facility_id,
            
            // Department identification
            'department_code' => $this->department_code,
            'department_name' => $this->department_name,
            'department_type' => $this->department_type,
            'department_type_label' => $this->getDepartmentTypeLabel(),
            
            // Hierarchy
            'parent_department_id' => $this->parent_department_id,
            'department_head_staff_id' => $this->department_head_staff_id,
            
            // Capacity & resources
            'bed_count' => $this->bed_count,
            'treatment_room_count' => $this->treatment_room_count,
            'max_concurrent_capacity' => $this->max_concurrent_capacity,
            
            // Location
            'building' => $this->building,
            'floor' => $this->floor,
            'wing_section' => $this->wing_section,
            
            // Operational
            'operating_hours' => $this->operating_hours,
            'formatted_operating_hours' => $this->getFormattedOperatingHours(),
            'accepts_walk_ins' => $this->accepts_walk_ins,
            'requires_appointment' => $this->requires_appointment,
            'average_wait_time_minutes' => $this->average_wait_time_minutes,
            
            // Status
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            
            // Audit
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
            'metadata' => $this->metadata,
            
            // Relationships (loaded only when requested)
            'facility' => $this->whenLoaded('facility', function () {
                return new FacilityResource($this->facility);
            }),
            'parent_department' => $this->whenLoaded('parentDepartment', function () {
                return new DepartmentResource($this->parentDepartment);
            }),
            'child_departments' => $this->whenLoaded('childDepartments', function () {
                return DepartmentResource::collection($this->childDepartments);
            }),
            'department_head' => $this->whenLoaded('departmentHead', function () {
                return new StaffResource($this->departmentHead);
            }),
            
            // Additional computed attributes
            'has_available_capacity' => $this->hasAvailableCapacity(),
            'is_active' => $this->status === 'active',
        ];
    }

    /**
     * Get the department type label.
     *
     * @return string
     */
    private function getDepartmentTypeLabel(): string
    {
        $labels = [
            'emergency' => 'Emergency',
            'intensive_care' => 'Intensive Care',
            'surgery' => 'Surgery',
            'outpatient' => 'Outpatient',
            'inpatient' => 'Inpatient',
            'radiology' => 'Radiology',
            'laboratory' => 'Laboratory',
            'pharmacy' => 'Pharmacy',
            'physical_therapy' => 'Physical Therapy',
            'cardiology' => 'Cardiology',
            'oncology' => 'Oncology',
            'pediatrics' => 'Pediatrics',
            'obstetrics' => 'Obstetrics',
            'psychiatry' => 'Psychiatry',
            'administration' => 'Administration',
            'support_services' => 'Support Services',
        ];

        return $labels[$this->department_type] ?? $this->department_type;
    }

    /**
     * Get the status label.
     *
     * @return string
     */
    private function getStatusLabel(): string
    {
        $labels = [
            'active' => 'Active',
            'inactive' => 'Inactive',
            'temporarily_closed' => 'Temporarily Closed',
        ];

        return $labels[$this->status] ?? $this->status;
    }

    /**
     * Customize the outgoing response for the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Http\JsonResponse  $response
     * @return void
     */
    public function withResponse($request, $response): void
    {
        $response->header('Content-Type', 'application/json');
        
        // Add custom headers if needed
        $response->header('X-Department-API-Version', '1.0');
    }

    /**
     * Get any additional data that should be returned with the resource array.
     *
     * @param  Request  $request
     * @return array
     */
    public function with(Request $request): array
    {
        return [
            'success' => true,
            'meta' => [
                'api_version' => '1.0',
                'timestamp' => now()->toISOString(),
            ],
        ];
    }
}