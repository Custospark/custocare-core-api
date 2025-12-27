<?php

namespace App\Policies;

use App\Models\PatientConsent;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PatientConsentPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     *
     * @param  \App\Models\User  $user
     * @param  int|null  $patientId
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user, ?int $patientId = null)
    {
        // Allow if user has permission to view all consents
        if ($user->can('view_all_patient_consents')) {
            return true;
        }

        // Allow if user has permission to view patient consents and is viewing specific patient
        if ($patientId && $user->can('view_patient_consents')) {
            // Additional check: user can only view consents for patients they have access to
            return $user->patients->contains('id', $patientId) || 
                   $user->hasRole(['doctor', 'nurse', 'administrator']);
        }

        return false;
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\PatientConsent  $patientConsent
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, PatientConsent $patientConsent)
    {
        // Allow if user has permission to view all consents
        if ($user->can('view_all_patient_consents')) {
            return true;
        }

        // Allow if user can view patient consents and has access to this patient
        if ($user->can('view_patient_consents')) {
            // Check if user has access to this specific patient
            return $user->patients->contains('id', $patientConsent->patient_id) ||
                   $user->hasRole(['doctor', 'nurse']) && 
                   $this->isInScope($user, $patientConsent);
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user)
    {
        return $user->can('create_patient_consents') || 
               $user->hasRole(['doctor', 'nurse', 'administrator']);
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\PatientConsent  $patientConsent
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, PatientConsent $patientConsent)
    {
        // Cannot update revoked or superseded consents
        if ($patientConsent->isRevoked() || $patientConsent->status === 'superseded') {
            return false;
        }

        // Allow if user has permission to update all consents
        if ($user->can('update_all_patient_consents')) {
            return true;
        }

        // Allow if user can update patient consents and has access
        if ($user->can('update_patient_consents')) {
            // Check if user is authorized to update this specific consent
            return $user->hasRole(['doctor', 'nurse']) && 
                   $this->isInScope($user, $patientConsent);
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\PatientConsent  $patientConsent
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, PatientConsent $patientConsent)
    {
        // Consents should not be deleted, only revoked or superseded
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\PatientConsent  $patientConsent
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, PatientConsent $patientConsent)
    {
        // Only administrators can restore soft-deleted consents
        return $user->hasRole('administrator') && $user->can('restore_patient_consents');
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\PatientConsent  $patientConsent
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, PatientConsent $patientConsent)
    {
        // Permanent deletion should be restricted for compliance reasons
        return $user->hasRole('super_admin') && $user->can('force_delete_patient_consents');
    }

    /**
     * Determine whether the user can revoke the consent.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\PatientConsent  $patientConsent
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function revoke(User $user, PatientConsent $patientConsent)
    {
        // Cannot revoke already revoked consents
        if ($patientConsent->isRevoked()) {
            return false;
        }

        // Allow if user has permission to revoke all consents
        if ($user->can('revoke_all_patient_consents')) {
            return true;
        }

        // Allow if user can revoke patient consents and has access
        if ($user->can('revoke_patient_consents')) {
            return $user->hasRole(['doctor', 'nurse', 'administrator']) && 
                   $this->isInScope($user, $patientConsent);
        }

        return false;
    }

    /**
     * Determine whether the user can validate consents.
     *
     * @param  \App\Models\User  $user
     * @param  int  $patientId
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function validate(User $user, int $patientId)
    {
        // Allow if user has permission to validate consents
        return $user->can('validate_patient_consents') || 
               $user->hasRole(['doctor', 'nurse', 'receptionist']);
    }

    /**
     * Determine whether the user can view statistics.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewStatistics(User $user)
    {
        return $user->can('view_consent_statistics') || 
               $user->hasRole(['administrator', 'manager']);
    }

    /**
     * Determine whether the user can view expiring consents.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewExpiring(User $user)
    {
        return $user->can('view_expiring_consents') || 
               $user->hasRole(['nurse', 'administrator', 'compliance_officer']);
    }

    /**
     * Check if user is within the consent scope.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\PatientConsent  $patientConsent
     * @return bool
     */
    private function isInScope(User $user, PatientConsent $patientConsent): bool
    {
        // If consent has no specific staff scope, user is in scope
        if (!$patientConsent->scope_staff_ids || empty((array) $patientConsent->scope_staff_ids)) {
            return true;
        }

        // Check if user's staff ID is in the scope
        return in_array($user->staff_id ?? null, (array) $patientConsent->scope_staff_ids);
    }
}