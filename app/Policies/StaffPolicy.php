<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Staff;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class StaffPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): Response
    {
        // Only users with admin or HR roles can view all staff
        $allowedRoles = ['super_admin', 'facility_admin', 'department_head', 'hr_admin'];
        
        if (in_array($user->staff?->global_role_level ?? '', $allowedRoles)) {
            return Response::allow();
        }
        
        return Response::deny('You do not have permission to view staff records.');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Staff $staff): Response
    {
        // Users can view their own staff record
        if ($user->staff && $user->staff->id === $staff->id) {
            return Response::allow();
        }
        
        // Supervisors can view their subordinates
        if ($user->staff && $staff->reports_to_staff_id === $user->staff->id) {
            return Response::allow();
        }
        
        // Admin roles can view any staff
        $adminRoles = ['super_admin', 'facility_admin', 'department_head'];
        if (in_array($user->staff?->global_role_level ?? '', $adminRoles)) {
            return Response::allow();
        }
        
        return Response::deny('You do not have permission to view this staff record.');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): Response
    {
        // Only HR admins and facility admins can create staff records
        $allowedRoles = ['super_admin', 'facility_admin', 'hr_admin'];
        
        if (in_array($user->staff?->global_role_level ?? '', $allowedRoles)) {
            return Response::allow();
        }
        
        return Response::deny('You do not have permission to create staff records.');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Staff $staff): Response
    {
        // Users can update their own basic information
        if ($user->staff && $user->staff->id === $staff->id) {
            // But not employment status or role level
            return Response::allow();
        }
        
        // HR and admin roles can update any staff
        $adminRoles = ['super_admin', 'facility_admin', 'hr_admin'];
        if (in_array($user->staff?->global_role_level ?? '', $adminRoles)) {
            return Response::allow();
        }
        
        // Supervisors can update their subordinates (limited fields)
        if ($user->staff && $staff->reports_to_staff_id === $user->staff->id) {
            return Response::allow();
        }
        
        return Response::deny('You do not have permission to update this staff record.');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Staff $staff): Response
    {
        // Cannot delete active staff
        if ($staff->employment_status === 'active') {
            return Response::deny('Cannot delete active staff. Update employment status first.');
        }
        
        // Only super admins and facility admins can delete staff
        $allowedRoles = ['super_admin', 'facility_admin'];
        
        if (in_array($user->staff?->global_role_level ?? '', $allowedRoles)) {
            return Response::allow();
        }
        
        return Response::deny('You do not have permission to delete staff records.');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Staff $staff): Response
    {
        // Only super admins can restore deleted staff
        if ($user->staff?->global_role_level === 'super_admin') {
            return Response::allow();
        }
        
        return Response::deny('You do not have permission to restore staff records.');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Staff $staff): Response
    {
        // Only super admins can permanently delete staff
        if ($user->staff?->global_role_level === 'super_admin') {
            return Response::allow();
        }
        
        return Response::deny('You do not have permission to permanently delete staff records.');
    }

    /**
     * Determine whether the user can update employment status.
     */
    public function updateEmploymentStatus(User $user, Staff $staff): Response
    {
        // Only HR admins and facility admins can update employment status
        $allowedRoles = ['super_admin', 'facility_admin', 'hr_admin'];
        
        if (in_array($user->staff?->global_role_level ?? '', $allowedRoles)) {
            return Response::allow();
        }
        
        return Response::deny('You do not have permission to update employment status.');
    }

    /**
     * Determine whether the user can update clinical privileges.
     */
    public function updateClinicalPrivileges(User $user, Staff $staff): Response
    {
        // Only department heads and facility admins can update clinical privileges
        $allowedRoles = ['super_admin', 'facility_admin', 'department_head'];
        
        if (in_array($user->staff?->global_role_level ?? '', $allowedRoles)) {
            return Response::allow();
        }
        
        return Response::deny('You do not have permission to update clinical privileges.');
    }

    /**
     * Determine whether the user can view sensitive information.
     */
    public function viewSensitiveInfo(User $user, Staff $staff): Response
    {
        // Users can view their own sensitive info
        if ($user->staff && $user->staff->id === $staff->id) {
            return Response::allow();
        }
        
        // Only HR and admin roles can view others' sensitive info
        $allowedRoles = ['super_admin', 'facility_admin', 'hr_admin'];
        
        if (in_array($user->staff?->global_role_level ?? '', $allowedRoles)) {
            return Response::allow();
        }
        
        return Response::deny('You do not have permission to view sensitive staff information.');
    }
}