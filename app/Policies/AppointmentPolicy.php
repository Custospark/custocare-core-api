<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class AppointmentPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): Response
    {
        // Admins, facility managers, providers, and receptionists can view appointments
        if ($user->hasAnyRole(['admin', 'facility_manager', 'healthcare_provider', 'receptionist'])) {
            return Response::allow();
        }

        // Patients can only view their own appointments
        if ($user->hasRole('patient')) {
            return Response::allow();
        }

        return Response::deny('You are not authorized to view appointments.');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Appointment $appointment): Response
    {
        // Admins can view any appointment
        if ($user->hasRole('admin')) {
            return Response::allow();
        }

        // Facility managers can view appointments in their facility
        if ($user->hasRole('facility_manager') && 
            $user->facility_id === $appointment->facility_id) {
            return Response::allow();
        }

        // Providers can view appointments they're assigned to
        if ($user->hasRole('healthcare_provider') && 
            $user->staffProfile && 
            $user->staffProfile->id === $appointment->provider_staff_id) {
            return Response::allow();
        }

        // Receptionists can view appointments in their facility
        if ($user->hasRole('receptionist') && 
            $user->facility_id === $appointment->facility_id) {
            return Response::allow();
        }

        // Patients can view their own appointments
        if ($user->hasRole('patient') && 
            $user->patientProfile && 
            $user->patientProfile->id === $appointment->patient_id) {
            return Response::allow();
        }

        return Response::deny('You are not authorized to view this appointment.');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): Response
    {
        // Admins, facility managers, providers, and receptionists can create appointments
        if ($user->hasAnyRole(['admin', 'facility_manager', 'healthcare_provider', 'receptionist'])) {
            return Response::allow();
        }

        return Response::deny('You are not authorized to create appointments.');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Appointment $appointment): Response
    {
        // Don't allow updates to completed or cancelled appointments
        if ($appointment->isCompleted() || $appointment->status === Appointment::STATUS_CANCELLED) {
            return Response::deny('Cannot update a completed or cancelled appointment.');
        }

        // Admins can update any appointment
        if ($user->hasRole('admin')) {
            return Response::allow();
        }

        // Facility managers can update appointments in their facility
        if ($user->hasRole('facility_manager') && 
            $user->facility_id === $appointment->facility_id) {
            return Response::allow();
        }

        // Providers can update appointments they're assigned to
        if ($user->hasRole('healthcare_provider') && 
            $user->staffProfile && 
            $user->staffProfile->id === $appointment->provider_staff_id) {
            return Response::allow();
        }

        // Receptionists can update appointments in their facility
        if ($user->hasRole('receptionist') && 
            $user->facility_id === $appointment->facility_id) {
            return Response::allow();
        }

        return Response::deny('You are not authorized to update this appointment.');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Appointment $appointment): Response
    {
        // Don't allow deletion of in-progress or completed appointments
        if ($appointment->isInProgress() || $appointment->isCompleted()) {
            return Response::deny('Cannot delete an appointment that is in progress or completed.');
        }

        // Admins can delete any appointment
        if ($user->hasRole('admin')) {
            return Response::allow();
        }

        // Facility managers can delete appointments in their facility
        if ($user->hasRole('facility_manager') && 
            $user->facility_id === $appointment->facility_id) {
            return Response::allow();
        }

        // Don't allow providers or receptionists to delete appointments
        return Response::deny('You are not authorized to delete appointments.');
    }

    /**
     * Determine whether the user can cancel the model.
     */
    public function cancel(User $user, Appointment $appointment): Response
    {
        // Check if appointment can be cancelled
        if (!$appointment->isCancellable()) {
            return Response::deny('This appointment cannot be cancelled at this time.');
        }

        // Admins can cancel any appointment
        if ($user->hasRole('admin')) {
            return Response::allow();
        }

        // Facility managers can cancel appointments in their facility
        if ($user->hasRole('facility_manager') && 
            $user->facility_id === $appointment->facility_id) {
            return Response::allow();
        }

        // Providers can cancel appointments they're assigned to
        if ($user->hasRole('healthcare_provider') && 
            $user->staffProfile && 
            $user->staffProfile->id === $appointment->provider_staff_id) {
            return Response::allow();
        }

        // Receptionists can cancel appointments in their facility
        if ($user->hasRole('receptionist') && 
            $user->facility_id === $appointment->facility_id) {
            return Response::allow();
        }

        // Patients can cancel their own appointments
        if ($user->hasRole('patient') && 
            $user->patientProfile && 
            $user->patientProfile->id === $appointment->patient_id) {
            return Response::allow();
        }

        return Response::deny('You are not authorized to cancel this appointment.');
    }

    /**
     * Determine whether the user can confirm the model.
     */
    public function confirm(User $user, Appointment $appointment): Response
    {
        // Only scheduled appointments can be confirmed
        if ($appointment->status !== Appointment::STATUS_SCHEDULED) {
            return Response::deny('Only scheduled appointments can be confirmed.');
        }

        // Admins can confirm any appointment
        if ($user->hasRole('admin')) {
            return Response::allow();
        }

        // Facility managers can confirm appointments in their facility
        if ($user->hasRole('facility_manager') && 
            $user->facility_id === $appointment->facility_id) {
            return Response::allow();
        }

        // Receptionists can confirm appointments in their facility
        if ($user->hasRole('receptionist') && 
            $user->facility_id === $appointment->facility_id) {
            return Response::allow();
        }

        return Response::deny('You are not authorized to confirm this appointment.');
    }

    /**
     * Determine whether the user can check in to the model.
     */
    public function checkIn(User $user, Appointment $appointment): Response
    {
        // Only confirmed appointments can be checked in
        if ($appointment->status !== Appointment::STATUS_CONFIRMED) {
            return Response::deny('Only confirmed appointments can be checked in.');
        }

        // Patients can check in to their own appointments
        if ($user->hasRole('patient') && 
            $user->patientProfile && 
            $user->patientProfile->id === $appointment->patient_id) {
            return Response::allow();
        }

        // Receptionists can check in patients in their facility
        if ($user->hasRole('receptionist') && 
            $user->facility_id === $appointment->facility_id) {
            return Response::allow();
        }

        return Response::deny('You are not authorized to check in for this appointment.');
    }

    /**
     * Determine whether the user can mark the model as completed.
     */
    public function complete(User $user, Appointment $appointment): Response
    {
        // Only checked-in or in-progress appointments can be completed
        if (!in_array($appointment->status, [
            Appointment::STATUS_CHECKED_IN,
            Appointment::STATUS_IN_PROGRESS
        ])) {
            return Response::deny('Only checked-in or in-progress appointments can be completed.');
        }

        // Providers can complete appointments they're assigned to
        if ($user->hasRole('healthcare_provider') && 
            $user->staffProfile && 
            $user->staffProfile->id === $appointment->provider_staff_id) {
            return Response::allow();
        }

        return Response::deny('You are not authorized to complete this appointment.');
    }

    /**
     * Determine whether the user can reschedule the model.
     */
    public function reschedule(User $user, Appointment $appointment): Response
    {
        // Don't allow rescheduling of completed or cancelled appointments
        if ($appointment->isCompleted() || $appointment->status === Appointment::STATUS_CANCELLED) {
            return Response::deny('Cannot reschedule a completed or cancelled appointment.');
        }

        // Admins can reschedule any appointment
        if ($user->hasRole('admin')) {
            return Response::allow();
        }

        // Facility managers can reschedule appointments in their facility
        if ($user->hasRole('facility_manager') && 
            $user->facility_id === $appointment->facility_id) {
            return Response::allow();
        }

        // Providers can reschedule appointments they're assigned to
        if ($user->hasRole('healthcare_provider') && 
            $user->staffProfile && 
            $user->staffProfile->id === $appointment->provider_staff_id) {
            return Response::allow();
        }

        // Receptionists can reschedule appointments in their facility
        if ($user->hasRole('receptionist') && 
            $user->facility_id === $appointment->facility_id) {
            return Response::allow();
        }

        // Patients can reschedule their own appointments
        if ($user->hasRole('patient') && 
            $user->patientProfile && 
            $user->patientProfile->id === $appointment->patient_id) {
            return Response::allow();
        }

        return Response::deny('You are not authorized to reschedule this appointment.');
    }

    /**
     * Determine whether the user can send reminders for the model.
     */
    public function sendReminder(User $user, Appointment $appointment): Response
    {
        // Only upcoming appointments can have reminders sent
        if (!$appointment->isUpcoming()) {
            return Response::deny('Reminders can only be sent for upcoming appointments.');
        }

        // Admins can send reminders for any appointment
        if ($user->hasRole('admin')) {
            return Response::allow();
        }

        // Facility managers can send reminders for appointments in their facility
        if ($user->hasRole('facility_manager') && 
            $user->facility_id === $appointment->facility_id) {
            return Response::allow();
        }

        // Receptionists can send reminders for appointments in their facility
        if ($user->hasRole('receptionist') && 
            $user->facility_id === $appointment->facility_id) {
            return Response::allow();
        }

        return Response::deny('You are not authorized to send reminders for this appointment.');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Appointment $appointment): Response
    {
        return $user->hasRole('admin')
            ? Response::allow()
            : Response::deny('You are not authorized to restore appointments.');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Appointment $appointment): Response
    {
        return $user->hasRole('admin')
            ? Response::allow()
            : Response::deny('You are not authorized to permanently delete appointments.');
    }
}