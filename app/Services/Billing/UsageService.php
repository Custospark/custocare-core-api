<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Services\Billing\Contracts\UsageServiceInterface;
use Illuminate\Support\Facades\DB;

class UsageService implements UsageServiceInterface
{
    public function getStaffCount(int $facilityId): int
    {
        return (int) DB::table('facility_staff_roles')
            ->where('facility_id', $facilityId)
            ->whereIn('assignment_status', ['active', 'on_leave'])
            ->distinct('staff_id')
            ->count('staff_id');
    }

    public function getDepartmentCount(int $facilityId): int
    {
        return (int) DB::table('departments')
            ->where('facility_id', $facilityId)
            ->where('status', 'active')
            ->count();
    }

    public function getVisitsCount(int $facilityId): int
    {
        $thirtyDaysAgo = now()->subDays(30);

        return (int) DB::table('visits')
            ->where('facility_id', $facilityId)
            ->where('arrived_at', '>=', $thirtyDaysAgo)
            ->distinct('patient_id')
            ->count('patient_id');
    }

    public function getAll(int $facilityId): array
    {
        return [
            'staff'       => $this->getStaffCount($facilityId),
            'departments' => $this->getDepartmentCount($facilityId),
            'visits'      => $this->getVisitsCount($facilityId),
        ];
    }
}
