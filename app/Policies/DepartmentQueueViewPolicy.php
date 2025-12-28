<?php

namespace App\Policies;

use App\Models\DepartmentQueueView;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class DepartmentQueueViewPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view department queue views') || 
               $user->hasRole(['admin', 'hospital_admin', 'department_head']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, DepartmentQueueView $departmentQueueView): bool
    {
        // User can view if they have permission or belong to the same facility
        return $user->hasPermission('view department queue views') ||
               $user->facility_id === $departmentQueueView->facility_id ||
               $user->hasRole(['admin', 'hospital_admin']);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('create department queue views') ||
               $user->hasRole(['admin', 'hospital_admin', 'system_integration']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, DepartmentQueueView $departmentQueueView): bool
    {
        // Only admins, hospital admins, or department heads from same facility can update
        return $user->hasPermission('edit department queue views') &&
               ($user->facility_id === $departmentQueueView->facility_id ||
                $user->hasRole(['admin', 'hospital_admin']));
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, DepartmentQueueView $departmentQueueView): bool
    {
        // Only admins or hospital admins can delete
        return $user->hasPermission('delete department queue views') &&
               $user->hasRole(['admin', 'hospital_admin']);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, DepartmentQueueView $departmentQueueView): bool
    {
        return $user->hasRole(['admin']);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, DepartmentQueueView $departmentQueueView): bool
    {
        return $user->hasRole(['admin']);
    }

    /**
     * Determine whether the user can view critical queues.
     */
    public function viewCritical(User $user): bool
    {
        return $user->hasPermission('view critical queues') ||
               $user->hasRole(['admin', 'hospital_admin', 'emergency_coordinator']);
    }

    /**
     * Determine whether the user can view dashboard statistics.
     */
    public function viewDashboard(User $user): bool
    {
        return $user->hasPermission('view dashboard') ||
               $user->hasRole(['admin', 'hospital_admin', 'department_head']);
    }

    /**
     * Determine whether the user can perform batch updates.
     */
    public function batchUpdate(User $user): bool
    {
        return $user->hasPermission('batch update queue views') ||
               $user->hasRole(['admin', 'system_integration']);
    }

    /**
     * Determine whether the user can generate predictions.
     */
    public function generatePredictions(User $user): bool
    {
        return $user->hasPermission('generate predictions') ||
               $user->hasRole(['admin', 'hospital_admin', 'analyst']);
    }
}