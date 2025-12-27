<?php

namespace App\Policies;

use App\Models\ClinicalEncounter;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class ClinicalEncounterPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): Response
    {
        return $user->hasPermission('view clinical encounters')
            ? Response::allow()
            : Response::deny('You do not have permission to view clinical encounters.');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ClinicalEncounter $clinicalEncounter): Response
    {
        // Allow if user has general view permission
        if (!$user->hasPermission('view clinical encounters')) {
            return Response::deny('You do not have permission to view clinical encounters.');
        }
        
        // Facility-based access control
        if ($user->facility_id && $user->facility_id !== $clinicalEncounter->facility_id) {
            return Response::deny('You can only view clinical encounters in your facility.');
        }
        
        // Provider-based access control
        if ($user->staff_id && 
            $user->staff_id !== $clinicalEncounter->primary_provider_staff_id &&
            $user->staff_id !== $clinicalEncounter->supervising_provider_staff_id) {
            // Check if user is in the same department
            if ($user->department_id !== $clinicalEncounter->department_id) {
                return Response::deny('You can only view clinical encounters you are associated with.');
            }
        }
        
        return Response::allow();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): Response
    {
        // Only providers and above can create encounters
        if (!$user->hasRole(['provider', 'supervisor', 'admin'])) {
            return Response::deny('Only providers can create clinical encounters.');
        }
        
        return $user->hasPermission('create clinical encounters')
            ? Response::allow()
            : Response::deny('You do not have permission to create clinical encounters.');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ClinicalEncounter $clinicalEncounter): Response
    {
        // Check general permission
        if (!$user->hasPermission('edit clinical encounters')) {
            return Response::deny('You do not have permission to edit clinical encounters.');
        }
        
        // Cannot update signed encounters without amendment permission
        if ($clinicalEncounter->signed_at && !$user->hasPermission('amend clinical encounters')) {
            return Response::deny('Signed encounters can only be amended by authorized personnel.');
        }
        
        // Provider can only update their own encounters
        if ($user->hasRole('provider')) {
            if ($user->staff_id !== $clinicalEncounter->primary_provider_staff_id) {
                return Response::deny('You can only update your own clinical encounters.');
            }
        }
        
        // Supervisor can update encounters in their department
        if ($user->hasRole('supervisor')) {
            if ($user->department_id !== $clinicalEncounter->department_id) {
                return Response::deny('You can only update clinical encounters in your department.');
            }
        }
        
        return Response::allow();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ClinicalEncounter $clinicalEncounter): Response
    {
        // Check general permission
        if (!$user->hasPermission('delete clinical encounters')) {
            return Response::deny('You do not have permission to delete clinical encounters.');
        }
        
        // Cannot delete signed encounters
        if ($clinicalEncounter->signed_at) {
            return Response::deny('Signed encounters cannot be deleted.');
        }
        
        // Provider can only delete their own unsigned encounters
        if ($user->hasRole('provider')) {
            if ($user->staff_id !== $clinicalEncounter->primary_provider_staff_id) {
                return Response::deny('You can only delete your own clinical encounters.');
            }
        }
        
        // Supervisor can delete encounters in their department
        if ($user->hasRole('supervisor')) {
            if ($user->department_id !== $clinicalEncounter->department_id) {
                return Response::deny('You can only delete clinical encounters in your department.');
            }
        }
        
        return Response::allow();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ClinicalEncounter $clinicalEncounter): Response
    {
        return $user->hasPermission('restore clinical encounters')
            ? Response::allow()
            : Response::deny('You do not have permission to restore clinical encounters.');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ClinicalEncounter $clinicalEncounter): Response
    {
        // Only admins can permanently delete
        if (!$user->hasRole('admin')) {
            return Response::deny('Only administrators can permanently delete clinical encounters.');
        }
        
        // Cannot permanently delete signed encounters
        if ($clinicalEncounter->signed_at) {
            return Response::deny('Signed encounters cannot be permanently deleted.');
        }
        
        return $user->hasPermission('force delete clinical encounters')
            ? Response::allow()
            : Response::deny('You do not have permission to permanently delete clinical encounters.');
    }

    /**
     * Determine whether the user can sign the model.
     */
    public function sign(User $user, ClinicalEncounter $clinicalEncounter): Response
    {
        // Check general permission
        if (!$user->hasPermission('sign clinical encounters')) {
            return Response::deny('You do not have permission to sign clinical encounters.');
        }
        
        // Only primary or supervising provider can sign
        if ($user->staff_id !== $clinicalEncounter->primary_provider_staff_id &&
            $user->staff_id !== $clinicalEncounter->supervising_provider_staff_id) {
            return Response::deny('Only the primary or supervising provider can sign this encounter.');
        }
        
        // Must be completed before signing
        if ($clinicalEncounter->documentation_status !== 'completed') {
            return Response::deny('Encounter must be completed before signing.');
        }
        
        // Cannot sign if already signed
        if ($clinicalEncounter->signed_at) {
            return Response::deny('Encounter is already signed.');
        }
        
        return Response::allow();
    }

    /**
     * Determine whether the user can amend the model.
     */
    public function amend(User $user, ClinicalEncounter $clinicalEncounter): Response
    {
        // Check general permission
        if (!$user->hasPermission('amend clinical encounters')) {
            return Response::deny('You do not have permission to amend clinical encounters.');
        }
        
        // Can only amend signed encounters
        if (!$clinicalEncounter->signed_at) {
            return Response::deny('Only signed encounters can be amended.');
        }
        
        // Must be a provider or supervisor
        if (!$user->hasRole(['provider', 'supervisor', 'admin'])) {
            return Response::deny('Only providers and supervisors can amend encounters.');
        }
        
        return Response::allow();
    }

    /**
     * Determine whether the user can view billing information.
     */
    public function viewBilling(User $user, ClinicalEncounter $clinicalEncounter): Response
    {
        // Check general permission
        if (!$user->hasPermission('view billing information')) {
            return Response::deny('You do not have permission to view billing information.');
        }
        
        // Billing department staff can view all
        if ($user->hasRole('billing')) {
            return Response::allow();
        }
        
        // Provider can view their own
        if ($user->staff_id === $clinicalEncounter->primary_provider_staff_id ||
            $user->staff_id === $clinicalEncounter->supervising_provider_staff_id) {
            return Response::allow();
        }
        
        // Admin and supervisors can view all
        if ($user->hasRole(['admin', 'supervisor'])) {
            return Response::allow();
        }
        
        return Response::deny('You do not have permission to view billing information for this encounter.');
    }
}