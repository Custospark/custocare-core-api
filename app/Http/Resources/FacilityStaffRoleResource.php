<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class FacilityStaffRoleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'assignment_uuid' => $this->assignment_uuid,
            'facility_id' => $this->facility_id,
            'staff_id' => $this->staff_id,
            'role_code' => $this->role_code,
            'role_label' => $this->getRoleLabel($this->role_code),
            'department_ids' => $this->department_ids ?? [],
            'is_primary_facility' => $this->is_primary_facility,
            'privileges_bitmask' => $this->privileges_bitmask ?? [],
            'accessible_patient_populations' => $this->accessible_patient_populations ?? [],
            'prescribing_authority_at_facility' => $this->prescribing_authority_at_facility ?? [],
            'shift_schedule' => $this->shift_schedule ?? [],
            'shift_type' => $this->shift_type,
            'hours_per_week' => $this->hours_per_week,
            'effective_from' => $this->effective_from->format('Y-m-d'),
            'effective_to' => $this->effective_to ? $this->effective_to->format('Y-m-d') : null,
            'assignment_status' => $this->assignment_status,
            'credentialing_completed_at' => $this->credentialing_completed_at?->format('Y-m-d H:i:s'),
            'credentialed_by_staff_id' => $this->credentialed_by_staff_id,
            'privileging_approved_at' => $this->privileging_approved_at?->format('Y-m-d H:i:s'),
            'next_reappointment_date' => $this->next_reappointment_date?->format('Y-m-d H:i:s'),
            'patients_treated_at_facility' => $this->patients_treated_at_facility,
            'facility_satisfaction_score' => $this->facility_satisfaction_score,
            'created_by_staff_id' => $this->created_by_staff_id,
            'metadata' => $this->metadata ?? [],
            'is_currently_active' => $this->isCurrentlyActive(),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),
            
            // Relationships (only loaded if eager loaded)
            'facility' => $this->whenLoaded('facility', function () {
                return new FacilityResource($this->facility);
            }),
            'staff' => $this->whenLoaded('staff', function () {
                return new StaffResource($this->staff);
            }),
            'created_by' => $this->whenLoaded('createdBy', function () {
                return new StaffResource($this->createdBy);
            }),
            'credentialed_by' => $this->whenLoaded('credentialedBy', function () {
                return new StaffResource($this->credentialedBy);
            })
        ];
    }

    /**
     * Get human-readable label for role code
     *
     * @param string $roleCode
     * @return string
     */
    private function getRoleLabel(string $roleCode): string
    {
        $labels = [
            'attending_physician' => 'Attending Physician',
            'resident_physician' => 'Resident Physician',
            'consulting_physician' => 'Consulting Physician',
            'surgeon' => 'Surgeon',
            'anesthesiologist' => 'Anesthesiologist',
            'nurse_practitioner' => 'Nurse Practitioner',
            'physician_assistant' => 'Physician Assistant',
            'registered_nurse' => 'Registered Nurse',
            'charge_nurse' => 'Charge Nurse',
            'nurse_manager' => 'Nurse Manager',
            'pharmacist' => 'Pharmacist',
            'pharmacy_technician' => 'Pharmacy Technician',
            'radiologist' => 'Radiologist',
            'radiologic_technician' => 'Radiologic Technician',
            'laboratory_scientist' => 'Laboratory Scientist',
            'respiratory_therapist' => 'Respiratory Therapist',
            'physical_therapist' => 'Physical Therapist',
            'occupational_therapist' => 'Occupational Therapist',
            'social_worker' => 'Social Worker',
            'case_manager' => 'Case Manager',
            'receptionist' => 'Receptionist',
            'medical_assistant' => 'Medical Assistant',
            'facility-administrator' => 'Facility Administrator',
            'department_manager' => 'Department Manager',
            'quality_coordinator' => 'Quality Coordinator',
            'infection_control' => 'Infection Control',
            'it_support' => 'IT Support'
        ];

        return $labels[$roleCode] ?? ucwords(str_replace('_', ' ', $roleCode));
    }

    /**
     * Get additional data that should be returned with the resource array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function with($request): array
    {
        return [
            'success' => true,
            'message' => 'Role assignment retrieved successfully'
        ];
    }
}