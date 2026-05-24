<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\StaffInvitation;
use App\Services\Billing\Contracts\UsageServiceInterface;
use Illuminate\Support\Facades\DB;

class UsageService implements UsageServiceInterface
{
    /**
     * Active/on_leave assignments plus pending invitations for staff not yet assigned.
     */
    public function getStaffCount(int $facilityId): int
    {
        return $this->getAssignedStaffCount($facilityId) + $this->getPendingInvitationStaffCount($facilityId);
    }

    public function getDepartmentCount(int $facilityId): int
    {
        return (int) DB::table('departments')
            ->where('facility_id', $facilityId)
            ->where('status', 'active')
            ->count();
    }

    /**
     * Visits created at the facility during the current calendar month.
     */
    public function getVisitsCount(int $facilityId): int
    {
        return (int) DB::table('visits')
            ->where('facility_id', $facilityId)
            ->whereNull('deleted_at')
            ->whereYear('arrived_at', now()->year)
            ->whereMonth('arrived_at', now()->month)
            ->count();
    }

    public function isStaffCountedTowardLimit(int $facilityId, int $staffId): bool
    {
        if ($this->hasAssignedStaffMembership($facilityId, $staffId)) {
            return true;
        }

        return StaffInvitation::query()
            ->where('facility_id', $facilityId)
            ->where('staff_id', $staffId)
            ->where('status', 'pending')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    public function getAll(int $facilityId): array
    {
        return [
            'staff'       => $this->getStaffCount($facilityId),
            'departments' => $this->getDepartmentCount($facilityId),
            'visits'      => $this->getVisitsCount($facilityId),
        ];
    }

    protected function getAssignedStaffCount(int $facilityId): int
    {
        return (int) DB::table('facility_staff_roles')
            ->where('facility_id', $facilityId)
            ->whereNull('deleted_at')
            ->whereIn('assignment_status', ['active', 'on_leave'])
            ->distinct('staff_id')
            ->count('staff_id');
    }

    protected function getPendingInvitationStaffCount(int $facilityId): int
    {
        $assignedStaffIds = DB::table('facility_staff_roles')
            ->where('facility_id', $facilityId)
            ->whereNull('deleted_at')
            ->whereIn('assignment_status', ['active', 'on_leave'])
            ->pluck('staff_id');

        return (int) StaffInvitation::query()
            ->where('facility_id', $facilityId)
            ->where('status', 'pending')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->when($assignedStaffIds->isNotEmpty(), fn ($query) => $query->whereNotIn('staff_id', $assignedStaffIds))
            ->distinct('staff_id')
            ->count('staff_id');
    }

    protected function hasAssignedStaffMembership(int $facilityId, int $staffId): bool
    {
        return DB::table('facility_staff_roles')
            ->where('facility_id', $facilityId)
            ->where('staff_id', $staffId)
            ->whereNull('deleted_at')
            ->whereIn('assignment_status', ['active', 'on_leave'])
            ->exists();
    }
}
