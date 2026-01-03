<?php

namespace App\Services;

use App\Models\User;
use App\Models\Patient;
use App\Models\Staff;
use App\Models\StaffRole;
use App\Http\Resources\UserResource;
use App\Http\Resources\PatientResource;
use App\Http\Resources\FacilityStaffRoleResource;
use App\Models\FacilityStaffRole;

class UserContextResolverService
{
    public function resolve(int $userId): array
    {
        $user = User::findOrFail($userId);

        return [
            'user' => new UserResource($user),
            'capabilities' => $this->resolveCapabilities($userId),
            'facility_roles' => $this->resolveFacilityRoles($userId),
        ];
    }

    protected function resolveCapabilities(int $userId): array
    {
        $capabilities = [];

        // PATIENT CAPABILITY
        $patient = Patient::where('user_id', $userId)->first();
        if ($patient) {
            $capabilities['patient'] = [
                'patient_id' => $patient->id,
            ];
        }

        // STAFF CAPABILITY
        $staff = Staff::where('user_id', $userId)->first();
        if ($staff) {
            $capabilities['staff'] = [
                'staff_id' => $staff->id,
            ];
        }

        return $capabilities;
    }

    protected function resolveFacilityRoles(int $userId): array
    {
        $staff = Staff::where('user_id', $userId)->first();

        if (!$staff) {
            return [];
        }

        $roles = FacilityStaffRole::with([
                'facility',
                'staff',
            ])
            ->where('staff_id', $staff->id)
            ->where('assignment_status', 'active')
            ->get();

        return FacilityStaffRoleResource::collection($roles)->resolve();
    }
}
