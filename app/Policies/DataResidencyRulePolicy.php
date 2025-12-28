<?php

namespace App\Policies;

use App\Models\DataResidencyRule;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class DataResidencyRulePolicy
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
        return $user->hasPermission('view data residency rules')
            ? Response::allow()
            : Response::deny('You are not authorized to view data residency rules.');
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param User $user
     * @param DataResidencyRule $dataResidencyRule
     * @return Response|bool
     */
    public function view(User $user, DataResidencyRule $dataResidencyRule): Response|bool
    {
        return $user->hasPermission('view data residency rules')
            ? Response::allow()
            : Response::deny('You are not authorized to view this data residency rule.');
    }

    /**
     * Determine whether the user can create models.
     *
     * @param User $user
     * @return Response|bool
     */
    public function create(User $user): Response|bool
    {
        // Only compliance officers and administrators can create rules
        if (!$user->hasRole(['compliance_officer', 'administrator'])) {
            return Response::deny('Only compliance officers and administrators can create data residency rules.');
        }
        
        return $user->hasPermission('create data residency rules')
            ? Response::allow()
            : Response::deny('You are not authorized to create data residency rules.');
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param User $user
     * @param DataResidencyRule $dataResidencyRule
     * @return Response|bool
     */
    public function update(User $user, DataResidencyRule $dataResidencyRule): Response|bool
    {
        // Check if user has permission
        if (!$user->hasPermission('update data residency rules')) {
            return Response::deny('You are not authorized to update data residency rules.');
        }
        
        // Only compliance officers and administrators can update active rules
        if ($dataResidencyRule->status === 'active' && 
            !$user->hasRole(['compliance_officer', 'administrator'])) {
            return Response::deny('Only compliance officers and administrators can update active data residency rules.');
        }
        
        // Cannot update superseded rules
        if ($dataResidencyRule->status === 'superseded') {
            return Response::deny('Cannot update superseded data residency rules.');
        }
        
        return Response::allow();
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param User $user
     * @param DataResidencyRule $dataResidencyRule
     * @return Response|bool
     */
    public function delete(User $user, DataResidencyRule $dataResidencyRule): Response|bool
    {
        // Check if user has permission
        if (!$user->hasPermission('delete data residency rules')) {
            return Response::deny('You are not authorized to delete data residency rules.');
        }
        
        // Only administrators can delete rules
        if (!$user->hasRole('administrator')) {
            return Response::deny('Only administrators can delete data residency rules.');
        }
        
        // Cannot delete active and effective rules
        if ($dataResidencyRule->isEffective()) {
            return Response::deny('Cannot delete active and effective data residency rules.');
        }
        
        return Response::allow();
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param User $user
     * @param DataResidencyRule $dataResidencyRule
     * @return Response|bool
     */
    public function restore(User $user, DataResidencyRule $dataResidencyRule): Response|bool
    {
        return $user->hasRole('administrator') && $user->hasPermission('restore data residency rules')
            ? Response::allow()
            : Response::deny('You are not authorized to restore data residency rules.');
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param User $user
     * @param DataResidencyRule $dataResidencyRule
     * @return Response|bool
     */
    public function forceDelete(User $user, DataResidencyRule $dataResidencyRule): Response|bool
    {
        return $user->hasRole('administrator') && $user->hasPermission('force delete data residency rules')
            ? Response::allow()
            : Response::deny('You are not authorized to permanently delete data residency rules.');
    }

    /**
     * Determine whether the user can validate data processing.
     *
     * @param User $user
     * @return Response|bool
     */
    public function validateProcessing(User $user): Response|bool
    {
        return $user->hasPermission('validate data processing')
            ? Response::allow()
            : Response::deny('You are not authorized to validate data processing.');
    }

    /**
     * Determine whether the user can validate cross-border transfers.
     *
     * @param User $user
     * @return Response|bool
     */
    public function validateCrossBorderTransfer(User $user): Response|bool
    {
        return $user->hasPermission('validate cross border transfers')
            ? Response::allow()
            : Response::deny('You are not authorized to validate cross-border transfers.');
    }

    /**
     * Determine whether the user can view rules summary.
     *
     * @param User $user
     * @return Response|bool
     */
    public function viewSummary(User $user): Response|bool
    {
        return $user->hasPermission('view data residency rules')
            ? Response::allow()
            : Response::deny('You are not authorized to view rules summary.');
    }
}