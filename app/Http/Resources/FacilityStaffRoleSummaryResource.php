<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FacilityStaffRoleSummaryResource extends JsonResource
{
    private function formatRoleAtFacility(?string $roleCode): ?string
    {
        if (!$roleCode) return null;

        $clean = preg_replace('/[-_]+/', ' ', trim($roleCode));
        $clean = preg_replace('/\s+/', ' ', $clean);

        return strtoupper($clean);
    }

    public function toArray(Request $request): array
    {
        $staff    = $this->whenLoaded('staff');
        $user     = $staff?->user;
        $facility = $this->whenLoaded('facility');

        $displayName = $user?->display_name
            ?? trim(($user?->first_name ?? '') . ' ' . ($user?->last_name ?? ''));

        $roleCode = $this->role_code;

        // ✅ Departments are stored as JSON ids on facility_staff_roles.department_ids
        $departmentIds = is_array($this->department_ids) ? $this->department_ids : [];

        // ✅ We expect controller to inject a lookup map into request: departmentsById[id] => dept array
        $departmentsById = (array) $request->attributes->get('departmentsById', []);

        $departments = collect($departmentIds)
            ->map(function ($id) use ($departmentsById) {
                return $departmentsById[$id] ?? null;
            })
            ->filter()
            ->values()
            ->all();

        return [
            'assignment_uuid'     => $this->assignment_uuid,

            'facility_id'         => $this->facility_id,
            'facility_uuid'       => $facility?->facility_uuid,
            'facility_name'       => $facility?->facility_name,

            'staff_id'            => $this->staff_id,
            'staff_uuid'          => $staff?->staff_uuid,

            'global_user_uuid'    => $user?->global_user_uuid,
            'staff_name'          => $displayName !== '' ? $displayName : null,

            'professional_title'  => $staff?->professional_title,
            'global_role_level'   => $staff?->global_role_level,
            'employment_status'   => $staff?->employment_status,

            'role_code'           => $roleCode,
            'role_at_facility'    => $this->formatRoleAtFacility($roleCode),

            'assignment_status'   => $this->assignment_status,
            'employment_status'   => $this->employment_status,
            'employment_type'   => $this->employment_type,
            'assignment_status'   => $this->assignment_status,
            'hire_date'   => $this->hire_date,
            'is_primary_facility' => (bool) $this->is_primary_facility,

            // ✅ now works (from JSON ids + map)
            'department_ids'      => $departmentIds,
            'departments'         => $departments,

            'module_codes'        => $this->module_code,

            'shift_type'          => $this->shift_type,
            'hours_per_week'      => $this->hours_per_week,

            'effective_from'      => optional($this->effective_from)->format('Y-m-d'),
            'effective_to'        => optional($this->effective_to)->format('Y-m-d'),

            'created_at'          => optional($this->created_at)->toISOString(),
        ];
    }
}
