<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Patient;
use Illuminate\Auth\Access\Response;

class PatientPolicy
{
    /**
     * Determine whether the user can view any patients.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view_patients') || 
               $user->hasRole(['doctor', 'nurse', 'admin']);
    }

    /**
     * Determine whether the user can view the patient.
     */
    public function view(User $user, Patient $patient): bool
    {
        // Users can view their own patient record
        if ($user->id === $patient->user_id) {
            return true;
        }

        // Staff with appropriate permissions can view
        if ($user->isStaff()) {
            return $user->hasPermission('view_patient_records') ||
                   $user->hasRole(['doctor', 'nurse']);
        }

        return false;
    }

    /**
     * Determine whether the user can create patients.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('create_patients') ||
               $user->hasRole(['admin', 'registrar']);
    }

    /**
     * Determine whether the user can update the patient.
     */
    public function update(User $user, Patient $patient): bool
    {
        // Cannot update deceased or merged patients
        if ($patient->isDeceased() || $patient->status === 'merged') {
            return false;
        }

        // Users can update their own basic info with restrictions
        if ($user->id === $patient->user_id) {
            return $user->hasPermission('update_own_patient_record');
        }

        // Staff with appropriate permissions can update
        if ($user->isStaff()) {
            return $user->hasPermission('update_patient_records') ||
                   $user->hasRole(['doctor', 'nurse', 'admin']);
        }

        return false;
    }

    /**
     * Determine whether the user can delete the patient.
     */
    public function delete(User $user, Patient $patient): bool
    {
        // Cannot delete deceased patients
        if ($patient->isDeceased()) {
            return false;
        }

        // Only admins can delete patient records
        return $user->hasPermission('delete_patients') &&
               $user->hasRole(['admin']);
    }

    /**
     * Determine whether the user can restore the patient.
     */
    public function restore(User $user, Patient $patient): bool
    {
        return $user->hasPermission('restore_patients') &&
               $user->hasRole(['admin']);
    }

    /**
     * Determine whether the user can permanently delete the patient.
     */
    public function forceDelete(User $user, Patient $patient): bool
    {
        // Only allow force delete for test patients
        if ($patient->status !== 'test_patient') {
            return false;
        }

        return $user->hasPermission('force_delete_patients') &&
               $user->hasRole(['admin']);
    }

    /**
     * Determine whether the user can view sensitive patient data.
     */
    public function viewSensitiveData(User $user, Patient $patient): bool
    {
        // Users cannot view their own sensitive data without proper consent
        if ($user->id === $patient->user_id) {
            return $patient->default_consent_level === 'full';
        }

        // Only medical staff with proper clearance can view sensitive data
        return $user->hasPermission('view_sensitive_patient_data') &&
               $user->hasRole(['doctor', 'nurse']) &&
               $patient->default_consent_level !== 'none';
    }

    /**
     * Determine whether the user can update medical information.
     */
    public function updateMedicalInfo(User $user, Patient $patient): bool
    {
        // Only medical staff can update medical information
        return $user->hasPermission('update_medical_info') &&
               $user->hasRole(['doctor', 'nurse']);
    }

    /**
     * Determine whether the user can update consent level.
     */
    public function updateConsent(User $user, Patient $patient): bool
    {
        // Users can update their own consent level
        if ($user->id === $patient->user_id) {
            return true;
        }

        // Admins and compliance officers can update consent
        return $user->hasPermission('update_consent_levels') &&
               $user->hasRole(['admin', 'compliance_officer']);
    }

    /**
     * Determine whether the user can merge patient records.
     */
    public function merge(User $user): bool
    {
        return $user->hasPermission('merge_patient_records') &&
               $user->hasRole(['admin', 'data_manager']);
    }

    /**
     * Determine whether the user can export patient data.
     */
    public function export(User $user, Patient $patient): bool
    {
        // Users can export their own data if allowed
        if ($user->id === $patient->user_id && $patient->data_sharing_allowed) {
            return true;
        }

        // Staff can export with proper permissions and patient consent
        return $user->hasPermission('export_patient_data') &&
               $user->hasRole(['admin', 'researcher']) &&
               $patient->data_sharing_allowed;
    }

    /**
     * Determine whether the user can mark patient as deceased.
     */
    public function markDeceased(User $user, Patient $patient): bool
    {
        // Cannot mark already deceased patients
        if ($patient->isDeceased()) {
            return false;
        }

        // Only doctors and admins can mark patients as deceased
        return $user->hasPermission('mark_patient_deceased') &&
               $user->hasRole(['doctor', 'admin']);
    }
}