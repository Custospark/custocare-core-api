<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Visit;
use Illuminate\Auth\Access\HandlesAuthorization;

class VisitPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any visits.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user)
    {
        return $user->hasPermission('view_visits');
    }

    /**
     * Determine whether the user can view the visit.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Visit  $visit
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, Visit $visit)
    {
        // Users can view visits in their facility
        if ($user->facility_id && $visit->facility_id !== $user->facility_id) {
            return $user->hasPermission('view_all_facility_visits');
        }

        return $user->hasPermission('view_visits');
    }

    /**
     * Determine whether the user can create visits.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user)
    {
        return $user->hasPermission('create_visits');
    }

    /**
     * Determine whether the user can update the visit.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Visit  $visit
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, Visit $visit)
    {
        // Check if user has permission
        if (!$user->hasPermission('update_visits')) {
            return false;
        }

        // Users can only update visits in their facility
        if ($user->facility_id && $visit->facility_id !== $user->facility_id) {
            return $user->hasPermission('update_all_facility_visits');
        }

        // Cannot update completed or cancelled visits
        if (in_array($visit->status, ['completed', 'cancelled'])) {
            return $user->hasPermission('update_completed_visits');
        }

        return true;
    }

    /**
     * Determine whether the user can delete the visit.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Visit  $visit
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, Visit $visit)
    {
        // Check if user has permission
        if (!$user->hasPermission('delete_visits')) {
            return false;
        }

        // Users can only delete visits in their facility
        if ($user->facility_id && $visit->facility_id !== $user->facility_id) {
            return $user->hasPermission('delete_all_facility_visits');
        }

        // Cannot delete active visits
        if ($visit->isActive()) {
            return $user->hasPermission('delete_active_visits');
        }

        return true;
    }

    /**
     * Determine whether the user can restore the visit.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Visit  $visit
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, Visit $visit)
    {
        return $user->hasPermission('restore_visits');
    }

    /**
     * Determine whether the user can permanently delete the visit.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Visit  $visit
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, Visit $visit)
    {
        return $user->hasPermission('force_delete_visits');
    }

    /**
     * Determine whether the user can discharge the visit.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Visit  $visit
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function discharge(User $user, Visit $visit)
    {
        if (!$user->hasPermission('discharge_visits')) {
            return false;
        }

        // Only clinical staff can discharge
        if (!$user->hasRole('clinical_staff')) {
            return false;
        }

        // Can only discharge active visits
        if (!$visit->isActive()) {
            return false;
        }

        // Users can only discharge visits in their facility
        if ($user->facility_id && $visit->facility_id !== $user->facility_id) {
            return $user->hasPermission('discharge_all_facility_visits');
        }

        return true;
    }

    /**
     * Determine whether the user can update visit phase.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Visit  $visit
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function updatePhase(User $user, Visit $visit)
    {
        if (!$user->hasPermission('update_visit_phase')) {
            return false;
        }

        // Users can only update visits in their facility
        if ($user->facility_id && $visit->facility_id !== $user->facility_id) {
            return $user->hasPermission('update_all_facility_visit_phase');
        }

        return true;
    }

    /**
     * Determine whether the user can update visit status.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Visit  $visit
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function updateStatus(User $user, Visit $visit)
    {
        if (!$user->hasPermission('update_visit_status')) {
            return false;
        }

        // Users can only update visits in their facility
        if ($user->facility_id && $visit->facility_id !== $user->facility_id) {
            return $user->hasPermission('update_all_facility_visit_status');
        }

        return true;
    }

    /**
     * Determine whether the user can start clinical care.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Visit  $visit
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function startClinicalCare(User $user, Visit $visit)
    {
        if (!$user->hasPermission('start_clinical_care')) {
            return false;
        }

        // Only clinical staff can start clinical care
        if (!$user->hasRole('clinical_staff')) {
            return false;
        }

        // Can only start clinical care for active visits
        if (!$visit->isActive()) {
            return false;
        }

        // Users can only work with visits in their facility
        if ($user->facility_id && $visit->facility_id !== $user->facility_id) {
            return $user->hasPermission('start_all_facility_clinical_care');
        }

        return true;
    }

    /**
     * Determine whether the user can cancel the visit.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Visit  $visit
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function cancel(User $user, Visit $visit)
    {
        if (!$user->hasPermission('cancel_visits')) {
            return false;
        }

        // Can only cancel active visits
        if (!$visit->isActive()) {
            return false;
        }

        // Users can only cancel visits in their facility
        if ($user->facility_id && $visit->facility_id !== $user->facility_id) {
            return $user->hasPermission('cancel_all_facility_visits');
        }

        return true;
    }
}