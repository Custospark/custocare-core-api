<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Prescription;
use Illuminate\Auth\Access\HandlesAuthorization;

class PrescriptionPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any prescriptions.
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('view prescriptions') || 
               $user->hasRole(['admin', 'provider', 'pharmacist', 'nurse']);
    }

    /**
     * Determine whether the user can view the prescription.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Prescription  $prescription
     * @return bool
     */
    public function view(User $user, Prescription $prescription): bool
    {
        // Admin can view all
        if ($user->hasRole('admin')) {
            return true;
        }
        
        // Provider can view their own prescriptions
        if ($user->hasRole('provider') && $prescription->prescribing_provider_staff_id === $user->id) {
            return true;
        }
        
        // Staff can view prescriptions from their facility
        if ($prescription->facility_id === $user->facility_id) {
            return $user->hasPermission('view prescriptions');
        }
        
        return false;
    }

    /**
     * Determine whether the user can create prescriptions.
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('create prescriptions') && 
               ($user->hasRole(['admin', 'provider']) || $user->hasRole('pharmacist') && $user->can('prescribe'));
    }

    /**
     * Determine whether the user can update the prescription.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Prescription  $prescription
     * @return bool
     */
    public function update(User $user, Prescription $prescription): bool
    {
        // Cannot update if transmitted or dispensed
        if (in_array($prescription->dispense_status, ['transmitted', 'dispensed', 'received_by_pharmacy'])) {
            return false;
        }
        
        // Cannot update if discontinued or cancelled
        if (in_array($prescription->status, ['discontinued', 'cancelled', 'expired'])) {
            return false;
        }
        
        // Admin can update any
        if ($user->hasRole('admin')) {
            return $user->hasPermission('edit prescriptions');
        }
        
        // Provider can update their own prescriptions
        if ($user->hasRole('provider') && $prescription->prescribing_provider_staff_id === $user->id) {
            return $user->hasPermission('edit prescriptions');
        }
        
        // Facility staff can update prescriptions in their facility
        if ($prescription->facility_id === $user->facility_id) {
            return $user->hasPermission('edit prescriptions') && 
                   $user->hasRole(['pharmacist', 'nurse']);
        }
        
        return false;
    }

    /**
     * Determine whether the user can delete the prescription.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Prescription  $prescription
     * @return bool
     */
    public function delete(User $user, Prescription $prescription): bool
    {
        // Cannot delete if transmitted or dispensed
        if (in_array($prescription->dispense_status, ['transmitted', 'dispensed', 'received_by_pharmacy'])) {
            return false;
        }
        
        // Cannot delete if not active
        if (!$prescription->isActive()) {
            return false;
        }
        
        // Admin can delete any
        if ($user->hasRole('admin')) {
            return $user->hasPermission('delete prescriptions');
        }
        
        // Provider can delete their own prescriptions
        if ($user->hasRole('provider') && $prescription->prescribing_provider_staff_id === $user->id) {
            return $user->hasPermission('delete prescriptions');
        }
        
        // Facility staff can delete prescriptions in their facility
        if ($prescription->facility_id === $user->facility_id) {
            return $user->hasPermission('delete prescriptions') && 
                   $user->hasRole(['pharmacist', 'nurse']);
        }
        
        return false;
    }

    /**
     * Determine whether the user can restore the prescription.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Prescription  $prescription
     * @return bool
     */
    public function restore(User $user, Prescription $prescription): bool
    {
        return $user->hasRole('admin') && $user->hasPermission('restore prescriptions');
    }

    /**
     * Determine whether the user can permanently delete the prescription.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Prescription  $prescription
     * @return bool
     */
    public function forceDelete(User $user, Prescription $prescription): bool
    {
        return $user->hasRole('admin') && $user->hasPermission('force delete prescriptions');
    }

    /**
     * Determine whether the user can refill the prescription.
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    public function refill(User $user): bool
    {
        return $user->hasPermission('refill prescriptions') && 
               $user->hasRole(['admin', 'pharmacist']);
    }

    /**
     * Determine whether the user can discontinue the prescription.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Prescription  $prescription
     * @return bool
     */
    public function discontinue(User $user, Prescription $prescription): bool
    {
        // Only active prescriptions can be discontinued
        if (!$prescription->isActive()) {
            return false;
        }
        
        // Admin can discontinue any
        if ($user->hasRole('admin')) {
            return true;
        }
        
        // Provider can discontinue their own prescriptions
        if ($user->hasRole('provider') && $prescription->prescribing_provider_staff_id === $user->id) {
            return true;
        }
        
        // Pharmacist can discontinue prescriptions in their facility
        if ($prescription->facility_id === $user->facility_id && $user->hasRole('pharmacist')) {
            return true;
        }
        
        return false;
    }

    /**
     * Determine whether the user can transmit the prescription.
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    public function transmit(User $user): bool
    {
        return $user->hasPermission('transmit prescriptions') && 
               $user->hasRole(['admin', 'pharmacist']);
    }

    /**
     * Determine whether the user can update dispense status.
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    public function updateDispenseStatus(User $user): bool
    {
        return $user->hasPermission('update dispense status') && 
               $user->hasRole(['admin', 'pharmacist']);
    }

    /**
     * Determine whether the user can view prescription statistics.
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    public function viewStatistics(User $user): bool
    {
        return $user->hasRole(['admin', 'provider', 'pharmacist', 'nurse']) && 
               $user->hasPermission('view prescription statistics');
    }

    /**
     * Determine whether the user can view transmissions.
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    public function viewTransmissions(User $user): bool
    {
        return $user->hasRole(['admin', 'pharmacist']) && 
               $user->hasPermission('view e-prescription transmissions');
    }

    /**
     * Determine whether the user can view DEA numbers.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Prescription  $prescription
     * @return bool
     */
    public function viewDeaNumber(User $user, Prescription $prescription): bool
    {
        // Only admin, prescribing provider, and authorized pharmacists can view DEA numbers
        if ($user->hasRole('admin')) {
            return true;
        }
        
        if ($user->hasRole('provider') && $prescription->prescribing_provider_staff_id === $user->id) {
            return true;
        }
        
        if ($user->hasRole('pharmacist') && $prescription->facility_id === $user->facility_id) {
            return $user->hasPermission('view dea numbers');
        }
        
        return false;
    }

    /**
     * Determine whether the user can view sensitive clinical information.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Prescription  $prescription
     * @return bool
     */
    public function viewSensitiveInfo(User $user, Prescription $prescription): bool
    {
        // Admin, providers, and pharmacists can view sensitive info
        return $user->hasRole(['admin', 'provider', 'pharmacist']) || 
               ($user->hasRole('nurse') && $prescription->facility_id === $user->facility_id);
    }

    /**
     * Determine whether the user can manage controlled substances.
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    public function manageControlledSubstances(User $user): bool
    {
        return $user->hasPermission('manage controlled substances') && 
               $user->hasRole(['admin', 'provider', 'pharmacist']);
    }
}