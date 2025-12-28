<?php

namespace App\Policies;

use App\Models\MedicationDispense;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class MedicationDispensePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     *
     * @param  User  $user
     * @return Response|bool
     */
    public function viewAny(User $user): Response|bool
    {
        // Only pharmacy staff, pharmacists, and administrators can view dispenses
        return $user->hasRole(['pharmacist', 'pharmacy_technician', 'administrator', 'nurse_practitioner']);
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param  User  $user
     * @param  MedicationDispense  $medicationDispense
     * @return Response|bool
     */
    public function view(User $user, MedicationDispense $medicationDispense): Response|bool
    {
        // User can view if they have appropriate role and facility access
        $hasRole = $user->hasRole(['pharmacist', 'pharmacy_technician', 'administrator', 'nurse_practitioner']);
        $hasFacilityAccess = $user->facility_id === $medicationDispense->facility_id || 
                           $user->hasRole('administrator');

        if (!$hasRole || !$hasFacilityAccess) {
            return Response::deny('You do not have permission to view this dispense.');
        }

        // Pharmacists can view all dispenses in their facility
        // Pharmacy technicians can only view dispenses they created or are assigned to verify
        if ($user->hasRole('pharmacy_technician')) {
            $isCreator = $medicationDispense->dispensed_by_staff_id === $user->id;
            $isAssignedToVerify = $medicationDispense->checked_by_staff_id === $user->id;
            
            if (!$isCreator && !$isAssignedToVerify) {
                return Response::deny('You can only view dispenses you created or are assigned to verify.');
            }
        }

        return true;
    }

    /**
     * Determine whether the user can create models.
     *
     * @param  User  $user
     * @return Response|bool
     */
    public function create(User $user): Response|bool
    {
        // Only pharmacy technicians and pharmacists can create dispenses
        if (!$user->hasRole(['pharmacy_technician', 'pharmacist'])) {
            return Response::deny('You do not have permission to create medication dispenses.');
        }

        // Check if user has active pharmacy license/certification
        if ($user->hasRole('pharmacist') && !$user->hasValidPharmacyLicense()) {
            return Response::deny('Your pharmacy license is not valid or has expired.');
        }

        return true;
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  User  $user
     * @param  MedicationDispense  $medicationDispense
     * @return Response|bool
     */
    public function update(User $user, MedicationDispense $medicationDispense): Response|bool
    {
        // Cannot update verified dispenses without special permission
        if ($medicationDispense->isVerified() && !$user->hasRole('administrator')) {
            return Response::deny('Cannot update verified dispenses.');
        }

        // Cannot update picked up dispenses
        if ($medicationDispense->isPickedUp()) {
            return Response::deny('Cannot update dispenses that have been picked up.');
        }

        // Only pharmacy staff from the same facility can update
        if ($user->facility_id !== $medicationDispense->facility_id && !$user->hasRole('administrator')) {
            return Response::deny('You can only update dispenses in your facility.');
        }

        // Pharmacy technicians can only update their own unverified dispenses
        if ($user->hasRole('pharmacy_technician') && 
            $medicationDispense->dispensed_by_staff_id !== $user->id) {
            return Response::deny('You can only update dispenses you created.');
        }

        return $user->hasRole(['pharmacy_technician', 'pharmacist', 'administrator']);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  User  $user
     * @param  MedicationDispense  $medicationDispense
     * @return Response|bool
     */
    public function delete(User $user, MedicationDispense $medicationDispense): Response|bool
    {
        // Cannot delete verified or picked up dispenses
        if ($medicationDispense->isVerified()) {
            return Response::deny('Cannot delete verified dispenses.');
        }

        if ($medicationDispense->isPickedUp()) {
            return Response::deny('Cannot delete dispenses that have been picked up.');
        }

        // Only administrators or the creating pharmacy technician can delete
        if ($user->hasRole('administrator')) {
            return true;
        }

        if ($user->hasRole('pharmacy_technician') && 
            $medicationDispense->dispensed_by_staff_id === $user->id) {
            return true;
        }

        return Response::deny('You do not have permission to delete this dispense.');
    }

    /**
     * Determine whether the user can verify dispenses.
     *
     * @param  User  $user
     * @param  MedicationDispense  $medicationDispense
     * @return Response|bool
     */
    public function verify(User $user, MedicationDispense $medicationDispense): Response|bool
    {
        // Only pharmacists can verify dispenses
        if (!$user->hasRole('pharmacist')) {
            return Response::deny('Only pharmacists can verify dispenses.');
        }

        // Cannot verify own dispenses (4-eyes principle)
        if ($medicationDispense->dispensed_by_staff_id === $user->id) {
            return Response::deny('You cannot verify your own dispense.');
        }

        // Cannot verify already verified dispenses
        if ($medicationDispense->isVerified()) {
            return Response::deny('This dispense is already verified.');
        }

        // Must be from the same facility
        if ($user->facility_id !== $medicationDispense->facility_id && !$user->hasRole('administrator')) {
            return Response::deny('You can only verify dispenses in your facility.');
        }

        // Check if pharmacist has valid license
        if (!$user->hasValidPharmacyLicense()) {
            return Response::deny('Your pharmacy license is not valid or has expired.');
        }

        return true;
    }

    /**
     * Determine whether the user can mark dispenses as picked up.
     *
     * @param  User  $user
     * @param  MedicationDispense  $medicationDispense
     * @return Response|bool
     */
    public function markAsPickedUp(User $user, MedicationDispense $medicationDispense): Response|bool
    {
        // Only pharmacy staff and receptionists can mark as picked up
        if (!$user->hasRole(['pharmacy_technician', 'pharmacist', 'receptionist'])) {
            return Response::deny('You do not have permission to update pickup status.');
        }

        // Cannot mark already picked up dispenses
        if ($medicationDispense->isPickedUp()) {
            return Response::deny('This dispense is already marked as picked up.');
        }

        // Must be verified before pickup (except for emergency situations)
        if (!$medicationDispense->isVerified() && !$user->hasRole('administrator')) {
            return Response::deny('Dispense must be verified before marking as picked up.');
        }

        // Must be from the same facility
        if ($user->facility_id !== $medicationDispense->facility_id && !$user->hasRole('administrator')) {
            return Response::deny('You can only update pickup status for dispenses in your facility.');
        }

        return true;
    }

    /**
     * Determine whether the user can update dispense status.
     *
     * @param  User  $user
     * @param  MedicationDispense  $medicationDispense
     * @return Response|bool
     */
    public function updateStatus(User $user, MedicationDispense $medicationDispense): Response|bool
    {
        // Only pharmacists and administrators can update status
        if (!$user->hasRole(['pharmacist', 'administrator'])) {
            return Response::deny('Only pharmacists can update dispense status.');
        }

        // Must be from the same facility
        if ($user->facility_id !== $medicationDispense->facility_id && !$user->hasRole('administrator')) {
            return Response::deny('You can only update status for dispenses in your facility.');
        }

        // Special rules for specific status changes
        if (in_array($medicationDispense->status, ['returned', 'destroyed'])) {
            // Only senior pharmacists or administrators can mark as returned/destroyed
            if (!$user->hasRole(['senior_pharmacist', 'administrator'])) {
                return Response::deny('Only senior pharmacists can mark dispenses as returned or destroyed.');
            }
        }

        return true;
    }

    /**
     * Determine whether the user can view dispenses by patient.
     *
     * @param  User  $user
     * @return Response|bool
     */
    public function viewPatientDispenses(User $user): Response|bool
    {
        // Healthcare providers, pharmacy staff, and administrators can view patient dispenses
        return $user->hasRole([
            'pharmacist', 
            'pharmacy_technician', 
            'physician', 
            'nurse_practitioner', 
            'nurse',
            'administrator'
        ]);
    }

    /**
     * Determine whether the user can view facility statistics.
     *
     * @param  User  $user
     * @return Response|bool
     */
    public function viewStatistics(User $user): Response|bool
    {
        // Only pharmacy managers and administrators can view statistics
        return $user->hasRole(['pharmacy_manager', 'administrator']);
    }

    /**
     * Determine whether the user can override safety checks.
     *
     * @param  User  $user
     * @param  MedicationDispense  $medicationDispense
     * @return Response|bool
     */
    public function overrideSafetyChecks(User $user, MedicationDispense $medicationDispense): Response|bool
    {
        // Only senior pharmacists and administrators can override safety checks
        if (!$user->hasRole(['senior_pharmacist', 'administrator'])) {
            return Response::deny('You do not have permission to override safety checks.');
        }

        // Must provide justification
        if (empty(request()->input('override_justification'))) {
            return Response::deny('Override justification is required.');
        }

        // Must be from the same facility
        if ($user->facility_id !== $medicationDispense->facility_id && !$user->hasRole('administrator')) {
            return Response::deny('You can only override safety checks for dispenses in your facility.');
        }

        return true;
    }
}