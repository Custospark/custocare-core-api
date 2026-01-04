<?php

namespace App\Services;

use App\Models\User;
use App\Models\Patient;
use App\Models\Staff;
use App\Models\FacilityStaffRole;

class UserContextResolverService
{
    public function resolve(int $userId): array
    {
        $user = User::findOrFail($userId);

        return [
            'user' => $this->minimalUserData($user),
            'capabilities' => $this->resolveCapabilities($userId),
            'facility_roles' => $this->resolveFacilityRoles($userId),
        ];
    }

    /**
     * Return minimal user info
     */
    protected function minimalUserData(User $user): array
    {
        return [
            'id' => $user->id,
            'uuid' => $user->global_user_uuid,
            'full_name' => $user->first_name . ' ' . $user->last_name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email_encrypted ? decrypt($user->email_encrypted) : null,
            'phone' => $user->phone_encrypted ? decrypt($user->phone_encrypted) : null,
            'national_id_country_code' => $user->national_id_country_code,
        ];
    }

    /**
     * Resolve patient/staff capabilities
     */
    protected function resolveCapabilities(int $userId): array
    {
        $capabilities = [];

        $patient = Patient::where('user_id', $userId)->first();
        if ($patient) {
            $capabilities['patient'] = [
                'patient_id' => $patient->id,
                'primary_facility_id' => $patient->primary_facility_id,
            ];
        }

        $staff = Staff::where('user_id', $userId)->first();
        if ($staff) {
            $capabilities['staff'] = [
                'staff_id' => $staff->id,
                'employee_id' => $staff->employee_id,
            ];
        }

        return $capabilities;
    }

    /**
     * Resolve active facility roles
     */
    protected function resolveFacilityRoles(int $userId): array
    {
        $staff = Staff::where('user_id', $userId)->first();
        if (!$staff) return [];

        $roles = FacilityStaffRole::with('facility')
            ->where('staff_id', $staff->id)
            ->where('assignment_status', 'active')
            ->get();

        return $roles->map(function ($role) {
            return [
                'facility_id' => $role->facility_id,
                'facility_name' => $role->facility->facility_name ?? null,
                'staff_id' => $role->staff_id,
                'role_code' => $role->role_code,
                'is_primary_facility' => $role->is_primary_facility,
            ];
        })->toArray();
    }
}
