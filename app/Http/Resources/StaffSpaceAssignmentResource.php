<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class StaffSpaceAssignmentResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'space' => $this->whenLoaded('space', function () {
                return [
                    'id' => $this->space->id,
                    'name' => $this->space->name,
                    'type' => $this->space->type,
                    'floor' => $this->space->floor,
                    'building' => $this->space->building,
                    'is_active' => $this->space->is_active,
                    'facility_id' => $this->space->facility_id,
                ];
            }),
            'staff' => $this->whenLoaded('staff', function () {
                if (!$this->staff) {
                    return null;
                }

                // Get the primary active role at this facility
                $primaryRole = $this->staff->facilityStaffRoles
                    ->where('assignment_status', 'active')
                    ->first();
                
                return [
                    'staff_id' => $this->staff->id,
                    'staff_uuid' => $this->staff->staff_uuid,
                    'employee_id' => $this->staff->employee_id,
                    'user' => $this->staff->user ? [
                        'id' => $this->staff->user->id,
                        'first_name' => $this->staff->user->first_name,
                        'last_name' => $this->staff->user->last_name,
                        'full_name' => trim("{$this->staff->user->first_name} {$this->staff->user->last_name}"),
                    ] : null,
                    'role_code' => $primaryRole ? $primaryRole->role_code : null,
                ];
            }),
            'assigned_by_user' => $this->whenLoaded('assignedByUser', function () {
                return $this->assignedByUser ? [
                    'id' => $this->assignedByUser->id,
                    'first_name' => $this->assignedByUser->first_name,
                    'last_name' => $this->assignedByUser->last_name,
                    'full_name' => trim("{$this->assignedByUser->first_name} {$this->assignedByUser->last_name}"),
                ] : null;
            }),
            'released_by_user' => $this->whenLoaded('releasedByUser', function () {
                return $this->releasedByUser ? [
                    'id' => $this->releasedByUser->id,
                    'first_name' => $this->releasedByUser->first_name,
                    'last_name' => $this->releasedByUser->last_name,
                    'full_name' => trim("{$this->releasedByUser->first_name} {$this->releasedByUser->last_name}"),
                ] : null;
            }),
            'assigned_at' => $this->assigned_at?->toISOString(),
            'released_at' => $this->released_at?->toISOString(),
            'note' => $this->note,
            'status' => $this->status,
            'facility_id' => $this->facility_id,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}