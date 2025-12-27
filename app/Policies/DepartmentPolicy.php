<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class DepartmentPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     *
     * @param  User  $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-departments') || 
               $user->hasRole(['admin', 'facility_manager', 'department_head']);
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param  User  $user
     * @param  Department  $department
     * @return bool
     */
    public function view(User $user, Department $department): bool
    {
        // Allow if user has global permission
        if ($user->can('view-departments')) {
            return true;
        }

        // Allow department head to view their own department
        if ($user->hasRole('department_head') && 
            $department->department_head_staff_id === $user->staff_id) {
            return true;
        }

        // Allow facility managers to view departments in their facility
        if ($user->hasRole('facility_manager')) {
            // Assuming user has a facility_id attribute
            return $department->facility_id === $user->facility_id;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     *
     * @param  User  $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return $user->can('create-departments') || 
               $user->hasRole(['admin', 'facility_manager']);
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  User  $user
     * @param  Department  $department
     * @return bool
     */
    public function update(User $user, Department $department): bool
    {
        // Allow if user has global permission
        if ($user->can('update-departments')) {
            return true;
        }

        // Allow department head to update their own department
        if ($user->hasRole('department_head') && 
            $department->department_head_staff_id === $user->staff_id) {
            return true;
        }

        // Allow facility managers to update departments in their facility
        if ($user->hasRole('facility_manager')) {
            return $department->facility_id === $user->facility_id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  User  $user
     * @param  Department  $department
     * @return bool
     */
    public function delete(User $user, Department $department): bool
    {
        // Only admins and facility managers can delete departments
        if ($user->can('delete-departments')) {
            return true;
        }

        // Facility managers can only delete departments in their facility
        if ($user->hasRole('facility_manager')) {
            return $department->facility_id === $user->facility_id;
        }

        return false;
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param  User  $user
     * @param  Department  $department
     * @return bool
     */
    public function restore(User $user, Department $department): bool
    {
        // Only admins and facility managers can restore departments
        return $user->can('restore-departments') || 
               $user->hasRole(['admin', 'facility_manager']);
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param  User  $user
     * @param  Department  $department
     * @return bool
     */
    public function forceDelete(User $user, Department $department): bool
    {
        // Only admins can permanently delete departments
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can assign a department head.
     *
     * @param  User  $user
     * @param  Department  $department
     * @return bool
     */
    public function assignHead(User $user, Department $department): bool
    {
        return $user->can('update-departments') || 
               $user->hasRole(['admin', 'facility_manager']);
    }

    /**
     * Determine whether the user can update department capacity.
     *
     * @param  User  $user
     * @param  Department  $department
     * @return bool
     */
    public function updateCapacity(User $user, Department $department): bool
    {
        return $this->update($user, $department);
    }

    /**
     * Determine whether the user can update operating hours.
     *
     * @param  User  $user
     * @param  Department  $department
     * @return bool
     */
    public function updateOperatingHours(User $user, Department $department): bool
    {
        return $this->update($user, $department);
    }
}