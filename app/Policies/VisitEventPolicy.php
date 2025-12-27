<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VisitEvent;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class VisitEventPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     *
     * @param User $user
     * @return Response|bool
     */
    public function viewAny(User $user): Response|bool
    {
        // Only users with appropriate permissions can view visit events
        return $user->hasPermission('view_visit_events')
            ? Response::allow()
            : Response::deny('You do not have permission to view visit events.');
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param User $user
     * @param VisitEvent $visitEvent
     * @return Response|bool
     */
    public function view(User $user, VisitEvent $visitEvent): Response|bool
    {
        // Users can view events if they have permission AND belong to the same facility
        return $user->hasPermission('view_visit_events') && 
               $this->userBelongsToFacility($user, $visitEvent->facility_id)
            ? Response::allow()
            : Response::deny('You do not have permission to view this visit event.');
    }

    /**
     * Determine whether the user can create models.
     *
     * @param User $user
     * @return Response|bool
     */
    public function create(User $user): Response|bool
    {
        // Only authenticated users with specific roles can create events
        $allowedRoles = ['admin', 'clinician', 'nurse', 'receptionist', 'system'];
        
        return in_array($user->role, $allowedRoles) && 
               $user->hasPermission('create_visit_events')
            ? Response::allow()
            : Response::deny('You do not have permission to create visit events.');
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param User $user
     * @param VisitEvent $visitEvent
     * @return Response|bool
     */
    public function update(User $user, VisitEvent $visitEvent): Response|bool
    {
        // Visit events are immutable, but we allow metadata updates for authorized users
        // Only system administrators can update event metadata
        return $user->hasRole('admin') && 
               $user->hasPermission('update_visit_events') &&
               $this->userBelongsToFacility($user, $visitEvent->facility_id)
            ? Response::allow()
            : Response::deny('Visit events are immutable and cannot be updated.');
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param User $user
     * @param VisitEvent $visitEvent
     * @return Response|bool
     */
    public function delete(User $user, VisitEvent $visitEvent): Response|bool
    {
        // Visit events are immutable and cannot be deleted
        // Only system administrators in extreme cases can delete
        return $user->hasRole('admin') && 
               $user->hasPermission('delete_visit_events') &&
               $this->userBelongsToFacility($user, $visitEvent->facility_id)
            ? Response::allow()
            : Response::deny('Visit events are immutable and cannot be deleted.');
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param User $user
     * @param VisitEvent $visitEvent
     * @return Response|bool
     */
    public function restore(User $user, VisitEvent $visitEvent): Response|bool
    {
        // Visit events are immutable and cannot be restored (no soft deletes)
        return Response::deny('Visit events are immutable and cannot be restored.');
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param User $user
     * @param VisitEvent $visitEvent
     * @return Response|bool
     */
    public function forceDelete(User $user, VisitEvent $visitEvent): Response|bool
    {
        // Only system administrators in extreme cases can permanently delete
        return $user->hasRole('admin') && 
               $user->hasPermission('force_delete_visit_events') &&
               $this->userBelongsToFacility($user, $visitEvent->facility_id)
            ? Response::allow()
            : Response::deny('You do not have permission to permanently delete visit events.');
    }

    /**
     * Determine whether the user can verify event chain integrity.
     *
     * @param User $user
     * @param VisitEvent $visitEvent
     * @return Response|bool
     */
    public function verifyChain(User $user, VisitEvent $visitEvent): Response|bool
    {
        // Users with view permission can verify chain integrity
        return $this->view($user, $visitEvent);
    }

    /**
     * Determine whether the user can recalculate integrity hash.
     *
     * @param User $user
     * @param VisitEvent $visitEvent
     * @return Response|bool
     */
    public function recalculateHash(User $user, VisitEvent $visitEvent): Response|bool
    {
        // Only system administrators can recalculate integrity hashes
        return $user->hasRole('admin') && 
               $user->hasPermission('recalculate_integrity_hash') &&
               $this->userBelongsToFacility($user, $visitEvent->facility_id)
            ? Response::allow()
            : Response::deny('You do not have permission to recalculate integrity hashes.');
    }

    /**
     * Determine whether the user belongs to the same facility as the event.
     *
     * @param User $user
     * @param int $facilityId
     * @return bool
     */
    private function userBelongsToFacility(User $user, int $facilityId): bool
    {
        // Check if user is associated with the facility
        // This assumes users have a facility_id field or a many-to-many relationship with facilities
        if ($user->facility_id === $facilityId) {
            return true;
        }

        // If user has multiple facilities, check the relationship
        if ($user->facilities && $user->facilities->contains('id', $facilityId)) {
            return true;
        }

        // System administrators can access all facilities
        if ($user->hasRole('admin')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view clinical events.
     *
     * @param User $user
     * @return Response|bool
     */
    public function viewClinicalEvents(User $user): Response|bool
    {
        // Only clinical staff can view clinical events
        $clinicalRoles = ['clinician', 'nurse', 'doctor', 'therapist'];
        
        return in_array($user->role, $clinicalRoles) && 
               $user->hasPermission('view_clinical_events')
            ? Response::allow()
            : Response::deny('You do not have permission to view clinical events.');
    }

    /**
     * Determine whether the user can generate reports.
     *
     * @param User $user
     * @return Response|bool
     */
    public function generateReports(User $user): Response|bool
    {
        // Managers and administrators can generate reports
        $reportRoles = ['admin', 'manager', 'supervisor'];
        
        return in_array($user->role, $reportRoles) && 
               $user->hasPermission('generate_event_reports')
            ? Response::allow()
            : Response::deny('You do not have permission to generate event reports.');
    }
}