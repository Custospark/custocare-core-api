<?php

namespace App\Policies;

use App\Models\FacilityStaffRole;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class FacilityStaffRolePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        // Typically, staff with HR or admin roles can view all assignments
        return $user->hasRole(['administrator', 'hr_manager', 'facility_manager']);
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param User $user
     * @param FacilityStaffRole $facilityStaffRole
     * @return bool
     */
    public function view(User $user, FacilityStaffRole $facilityStaffRole): bool
    {
        // Users can view their own assignments
        if ($user->id === $facilityStaffRole->staff_id) {
            return true;
        }

        // Facility managers can view assignments at their facilities
        if ($user->hasRole('facility_manager')) {
            // Check if user manages this facility
            // This would require additional logic to check facility management
            return true;
        }

        // HR and admins can view all assignments
        return $user->hasRole(['administrator', 'hr_manager']);
    }

    /**
     * Determine whether the user can create models.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        // Only HR and admins can create assignments
        return $user->hasRole(['administrator', 'hr_manager', 'facility_manager']);
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param User $user
     * @param FacilityStaffRole $facilityStaffRole
     * @return bool
     */
    public function update(User $user, FacilityStaffRole $facilityStaffRole): bool
    {
        // HR and admins can update any assignment
        if ($user->hasRole(['administrator', 'hr_manager'])) {
            return true;
        }

        // Facility managers can update assignments at their facilities
        if ($user->hasRole('facility_manager')) {
            // Check if user manages this facility
            // This would require additional logic
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param User $user
     * @param FacilityStaffRole $facilityStaffRole
     * @return bool
     */
    public function delete(User $user, FacilityStaffRole $facilityStaffRole): bool
    {
        // Only admins can delete assignments
        // Note: In practice, we usually soft delete or change status
        return $user->hasRole('administrator');
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param User $user
     * @param FacilityStaffRole $facilityStaffRole
     * @return bool
     */
    public function restore(User $user, FacilityStaffRole $facilityStaffRole): bool
    {
        // Only admins can restore assignments
        return $user->hasRole('administrator');
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param User $user
     * @param FacilityStaffRole $facilityStaffRole
     * @return bool
     */
    public function forceDelete(User $user, FacilityStaffRole $facilityStaffRole): bool
    {
        // Only admins can permanently delete
        return $user->hasRole('administrator');
    }

    /**
     * Determine whether the user can update assignment status.
     *
     * @param User $user
     * @param FacilityStaffRole $facilityStaffRole
     * @return bool
     */
    public function updateStatus(User $user, FacilityStaffRole $facilityStaffRole): bool
    {
        // HR, admins, and facility managers can update status
        if ($user->hasRole(['administrator', 'hr_manager', 'facility_manager'])) {
            return true;
        }

        // Staff can only update their own status to "on_leave"
        if ($user->id === $facilityStaffRole->staff_id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can update credentialing information.
     *
     * @param User $user
     * @param FacilityStaffRole $facilityStaffRole
     * @return bool
     */
    public function updateCredentialing(User $user, FacilityStaffRole $facilityStaffRole): bool
    {
        // Only credentialing managers and admins can update credentialing
        return $user->hasRole(['administrator', 'credentialing_manager']);
    }
}