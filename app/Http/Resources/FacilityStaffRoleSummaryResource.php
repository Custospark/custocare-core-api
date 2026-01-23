<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FacilityStaffRoleSummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * Keep this lean: <= 20 fields. Aggregated from related tables.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $staff = $this->whenLoaded('staff');
        $user = $staff?->user;
        $facility = $this->whenLoaded('facility');

        // Prefer display_name, fallback to first + last
        $displayName = $user?->display_name
            ?? trim(($user?->first_name ?? '') . ' ' . ($user?->last_name ?? ''));

        // Departments list is optionally preloaded as a mapped list
        $departments = $this->whenLoaded('departments');

        return [
            // 1) Assignment public ID
            'assignment_uuid' => $this->assignment_uuid,

            // 2) Facility (id + public)
            'facility_id' => $this->facility_id,
            'facility_uuid' => $facility?->facility_uuid,

            // 3) Facility name
            'facility_name' => $facility?->facility_name,

            // 4) Staff (id + public)
            'staff_id' => $this->staff_id,
            'staff_uuid' => $staff?->staff_uuid,

            // 5) Staff identity anchor
            'global_user_uuid' => $user?->global_user_uuid,

            // 6) Staff display name
            'staff_name' => $displayName !== '' ? $displayName : null,

            // 7) Core staff attributes (useful in UI)
            'professional_title' => $staff?->professional_title,
            'global_role_level' => $staff?->global_role_level,
            'employment_status' => $staff?->employment_status,

            // 8) Assignment role
            'role_code' => $this->role_code,

            // 9) Assignment status & primacy
            'assignment_status' => $this->assignment_status,
            'is_primary_facility' => (bool) $this->is_primary_facility,

            // 10) Department scope (ids + names)
            'department_ids' => $this->department_ids,
            'departments' => $departments
                ? $departments->map(fn ($d) => [
                    'id' => $d->id,
                    'department_uuid' => $d->department_uuid,
                    'department_code' => $d->department_code,
                    'department_name' => $d->department_name,
                    'department_type' => $d->department_type,
                ])->values()
                : [],

            // 11) Module access (summary)
            'module_codes' => $this->module_code,

            // 12) Shift summary
            'shift_type' => $this->shift_type,
            'hours_per_week' => $this->hours_per_week,

            // 13) Effective range
            'effective_from' => optional($this->effective_from)->format('Y-m-d'),
            'effective_to' => optional($this->effective_to)->format('Y-m-d'),

            // 14) Useful sort/debug timestamp
            'created_at' => optional($this->created_at)->toISOString(),
        ];
    }
}
