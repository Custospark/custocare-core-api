<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VisitActor;
use Illuminate\Auth\Access\HandlesAuthorization;

class VisitActorPolicy
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
        // Allow viewing visit actors if user has appropriate permissions
        return $user->hasPermission('view_visit_actors') || 
               $user->hasRole(['admin', 'supervisor', 'medical_director']);
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param User $user
     * @param VisitActor $visitActor
     * @return bool
     */
    public function view(User $user, VisitActor $visitActor): bool
    {
        // User can view if they created it, are assigned to it, or have admin rights
        return $user->hasPermission('view_visit_actors') ||
               $user->id === $visitActor->staff_id ||
               $user->hasRole(['admin', 'supervisor']);
    }

    /**
     * Determine whether the user can create models.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        // Only authorized staff can create visit actor records
        return $user->hasPermission('create_visit_actors') ||
               $user->hasRole(['admin', 'medical_staff', 'nursing_staff']);
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param User $user
     * @param VisitActor $visitActor
     * @return bool
     */
    public function update(User $user, VisitActor $visitActor): bool
    {
        // Check if user can update based on status and permissions
        if ($visitActor->participation_ended_at && !$user->hasRole('admin')) {
            // Cannot update ended participations without admin rights
            return false;
        }
        
        return $user->hasPermission('update_visit_actors') ||
               ($user->id === $visitActor->staff_id && !$visitActor->is_billable_provider) ||
               $user->hasRole(['admin', 'supervisor']);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param User $user
     * @param VisitActor $visitActor
     * @return bool
     */
    public function delete(User $user, VisitActor $visitActor): bool
    {
        // Prevent deletion of billable records
        if ($visitActor->is_billable_provider && $visitActor->provider_charge_amount > 0) {
            return $user->hasRole('admin'); // Only admin can delete billable records
        }
        
        // Only allow deletion if participation hasn't ended or user is admin
        return $user->hasRole('admin') || 
               (!$visitActor->participation_ended_at && $user->hasPermission('delete_visit_actors'));
    }

    /**
     * Determine whether the user can end participation.
     *
     * @param User $user
     * @param VisitActor $visitActor
     * @return bool
     */
    public function endParticipation(User $user, VisitActor $visitActor): bool
    {
        // User can end participation if they are the staff member or supervisor
        return $user->id === $visitActor->staff_id ||
               $user->hasRole(['admin', 'supervisor']) ||
               $user->hasPermission('end_participation');
    }

    /**
     * Determine whether the user can view billing information.
     *
     * @param User $user
     * @param VisitActor $visitActor
     * @return bool
     */
    public function viewBilling(User $user, VisitActor $visitActor): bool
    {
        // Billing information is sensitive
        return $user->hasPermission('view_billing_info') ||
               $user->hasRole(['admin', 'billing_specialist', 'medical_director']);
    }

    /**
     * Determine whether the user can export visit actor data.
     *
     * @param User $user
     * @return bool
     */
    public function export(User $user): bool
    {
        // Export requires higher level permissions
        return $user->hasPermission('export_visit_actors') ||
               $user->hasRole(['admin', 'medical_director', 'compliance_officer']);
    }
}