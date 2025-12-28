<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VisitCurrentState;
use Illuminate\Auth\Access\HandlesAuthorization;

class VisitCurrentStatePolicy
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
        // Healthcare staff can view visit current states
        return $user->hasRole(['admin', 'physician', 'nurse', 'receptionist', 'department_head']);
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param User $user
     * @param VisitCurrentState $visitCurrentState
     * @return bool
     */
    public function view(User $user, VisitCurrentState $visitCurrentState): bool
    {
        // Users can view if they belong to the same facility
        if ($user->facility_id && $visitCurrentState->facility_id !== $user->facility_id) {
            return false;
        }
        
        // Additional role-based restrictions
        if ($user->hasRole('receptionist')) {
            // Receptionists can only view non-clinical information
            return true;
        }
        
        if ($user->hasRole('nurse')) {
            // Nurses can view all visits in their facility
            return $visitCurrentState->facility_id === $user->facility_id;
        }
        
        if ($user->hasRole('physician')) {
            // Physicians can view visits they're assigned to or in their department
            $isAssigned = in_array($user->id, $visitCurrentState->staff_assigned_ids ?? []) ||
                         $visitCurrentState->primary_provider_staff_id === $user->id;
            
            $isSameDepartment = $visitCurrentState->current_department_id === $user->department_id;
            
            return $isAssigned || $isSameDepartment;
        }
        
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can create models.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        // Only specific roles can create visit current states
        // Typically created via CDC from visit_events, not manually
        return $user->hasRole(['admin', 'system']);
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param User $user
     * @param VisitCurrentState $visitCurrentState
     * @return bool
     */
    public function update(User $user, VisitCurrentState $visitCurrentState): bool
    {
        // Typically updated via CDC, but some staff may need to update certain fields
        if ($user->hasRole('admin')) {
            return true;
        }
        
        // Nurses can update wait times and basic info
        if ($user->hasRole('nurse') && $visitCurrentState->facility_id === $user->facility_id) {
            return true;
        }
        
        // Physicians can update visits they're assigned to
        if ($user->hasRole('physician')) {
            $isAssigned = in_array($user->id, $visitCurrentState->staff_assigned_ids ?? []) ||
                         $visitCurrentState->primary_provider_staff_id === $user->id;
            
            return $isAssigned;
        }
        
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param User $user
     * @param VisitCurrentState $visitCurrentState
     * @return bool
     */
    public function delete(User $user, VisitCurrentState $visitCurrentState): bool
    {
        // Typically not deleted manually, only via CDC or admin
        return $user->hasRole(['admin', 'system']);
    }

    /**
     * Determine whether the user can view critical alerts.
     *
     * @param User $user
     * @return bool
     */
    public function viewCriticalAlerts(User $user): bool
    {
        // Only clinical staff and admins can view critical alerts
        return $user->hasRole(['admin', 'physician', 'nurse', 'department_head']);
    }

    /**
     * Determine whether the user can view dashboard statistics.
     *
     * @param User $user
     * @return bool
     */
    public function viewDashboard(User $user): bool
    {
        // All healthcare staff can view dashboard
        return $user->hasRole(['admin', 'physician', 'nurse', 'receptionist', 'department_head']);
    }

    /**
     * Determine whether the user can process CDC events.
     *
     * @param User $user
     * @return bool
     */
    public function processEvents(User $user): bool
    {
        // Only system processes should handle CDC events
        return $user->hasRole('system');
    }
}