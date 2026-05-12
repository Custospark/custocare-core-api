<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\FacilityStaffRole;
use App\Models\Patient;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

/**
 * Authorization uses concrete domain tables (staff, patients, facility_staff_roles),
 * not Spatie application roles.
 */
class AppointmentPolicy
{
    use HandlesAuthorization;

    /** Assignment rows considered usable for facility access checks. */
    private const ACTIVE_ASSIGNMENT_STATUSES = ['active', 'on_leave', 'suspended'];

    private function patientProfile(User $user): ?Patient
    {
        return $user->patientProfile;
    }

    private function staff(User $user): ?Staff
    {
        return $user->staff;
    }

    private function isSuperAdminStaff(User $user): bool
    {
        $s = $this->staff($user);

        return $s !== null && $s->global_role_level === 'super_admin';
    }

    private function staffHasActiveFacilityAssignment(User $user, int $facilityId): bool
    {
        $s = $this->staff($user);
        if ($s === null) {
            return false;
        }

        return FacilityStaffRole::query()
            ->where('staff_id', $s->id)
            ->where('facility_id', $facilityId)
            ->whereIn('assignment_status', self::ACTIVE_ASSIGNMENT_STATUSES)
            ->exists();
    }

    private function staffHasAnyActiveFacilityAssignment(User $user): bool
    {
        $s = $this->staff($user);
        if ($s === null) {
            return false;
        }

        return FacilityStaffRole::query()
            ->where('staff_id', $s->id)
            ->whereIn('assignment_status', self::ACTIVE_ASSIGNMENT_STATUSES)
            ->exists();
    }

    private function isAssignedProvider(User $user, Appointment $appointment): bool
    {
        $s = $this->staff($user);

        return $s !== null && (int) $s->id === (int) $appointment->provider_staff_id;
    }

    private function isPatientOwner(User $user, Appointment $appointment): bool
    {
        $p = $this->patientProfile($user);

        return $p !== null && (int) $p->id === (int) $appointment->patient_id;
    }

    /**
     * Facility-level delete (previously facility_manager + admin).
     */
    private function staffCanDeleteAtFacility(User $user, int $facilityId): bool
    {
        if ($this->isSuperAdminStaff($user)) {
            return true;
        }

        $s = $this->staff($user);
        if ($s === null) {
            return false;
        }

        if ($s->global_role_level === 'facility_admin' && $this->staffHasActiveFacilityAssignment($user, $facilityId)) {
            return true;
        }

        return FacilityStaffRole::query()
            ->where('staff_id', $s->id)
            ->where('facility_id', $facilityId)
            ->whereIn('assignment_status', self::ACTIVE_ASSIGNMENT_STATUSES)
            ->where('role_code', 'facility-administrator')
            ->exists();
    }

    public function viewAny(User $user): Response
    {
        if ($this->patientProfile($user) !== null) {
            return Response::allow();
        }

        if ($this->isSuperAdminStaff($user)) {
            return Response::allow();
        }

        if ($this->staffHasAnyActiveFacilityAssignment($user)) {
            return Response::allow();
        }

        return Response::deny('You are not authorized to view appointments.');
    }

    public function view(User $user, Appointment $appointment): Response
    {
        if ($this->isSuperAdminStaff($user)) {
            return Response::allow();
        }

        if ($this->isPatientOwner($user, $appointment)) {
            return Response::allow();
        }

        if ($this->staffHasActiveFacilityAssignment($user, (int) $appointment->facility_id)) {
            return Response::allow();
        }

        if ($this->isAssignedProvider($user, $appointment)) {
            return Response::allow();
        }

        return Response::deny('You are not authorized to view this appointment.');
    }

    public function create(User $user): Response
    {
        if ($this->patientProfile($user) !== null) {
            return Response::allow();
        }

        if ($this->isSuperAdminStaff($user)) {
            return Response::allow();
        }

        if ($this->staffHasAnyActiveFacilityAssignment($user)) {
            return Response::allow();
        }

        return Response::deny('You are not authorized to create appointments.');
    }

    public function update(User $user, Appointment $appointment): Response
    {
        if ($appointment->isCompleted() || $appointment->status === Appointment::STATUS_CANCELLED) {
            return Response::deny('Cannot update a completed or cancelled appointment.');
        }

        if ($this->isSuperAdminStaff($user)) {
            return Response::allow();
        }

        if ($this->staffHasActiveFacilityAssignment($user, (int) $appointment->facility_id)) {
            return Response::allow();
        }

        if ($this->isAssignedProvider($user, $appointment)) {
            return Response::allow();
        }

        return Response::deny('You are not authorized to update this appointment.');
    }

    public function delete(User $user, Appointment $appointment): Response
    {
        if ($appointment->isInProgress() || $appointment->isCompleted()) {
            return Response::deny('Cannot delete an appointment that is in progress or completed.');
        }

        if ($this->staffCanDeleteAtFacility($user, (int) $appointment->facility_id)) {
            return Response::allow();
        }

        return Response::deny('You are not authorized to delete appointments.');
    }

    public function cancel(User $user, Appointment $appointment): Response
    {
        if (!$appointment->isCancellable()) {
            return Response::deny('This appointment cannot be cancelled at this time.');
        }

        if ($this->isSuperAdminStaff($user)) {
            return Response::allow();
        }

        if ($this->staffHasActiveFacilityAssignment($user, (int) $appointment->facility_id)) {
            return Response::allow();
        }

        if ($this->isAssignedProvider($user, $appointment)) {
            return Response::allow();
        }

        if ($this->isPatientOwner($user, $appointment)) {
            return Response::allow();
        }

        return Response::deny('You are not authorized to cancel this appointment.');
    }

    public function confirm(User $user, Appointment $appointment): Response
    {
        if ($appointment->status !== Appointment::STATUS_SCHEDULED) {
            return Response::deny('Only scheduled appointments can be confirmed.');
        }

        if ($this->isSuperAdminStaff($user)) {
            return Response::allow();
        }

        if ($this->staffHasActiveFacilityAssignment($user, (int) $appointment->facility_id)) {
            return Response::allow();
        }

        return Response::deny('You are not authorized to confirm this appointment.');
    }

    public function checkIn(User $user, Appointment $appointment): Response
    {
        if ($appointment->status !== Appointment::STATUS_CONFIRMED) {
            return Response::deny('Only confirmed appointments can be checked in.');
        }

        if ($this->isPatientOwner($user, $appointment)) {
            return Response::allow();
        }

        if ($this->staffHasActiveFacilityAssignment($user, (int) $appointment->facility_id)) {
            return Response::allow();
        }

        return Response::deny('You are not authorized to check in for this appointment.');
    }

    public function complete(User $user, Appointment $appointment): Response
    {
        if (!in_array($appointment->status, [
            Appointment::STATUS_CHECKED_IN,
            Appointment::STATUS_IN_PROGRESS,
        ], true)) {
            return Response::deny('Only checked-in or in-progress appointments can be completed.');
        }

        if ($this->isSuperAdminStaff($user)) {
            return Response::allow();
        }

        if ($this->isAssignedProvider($user, $appointment)) {
            return Response::allow();
        }

        return Response::deny('You are not authorized to complete this appointment.');
    }

    public function reschedule(User $user, Appointment $appointment): Response
    {
        if ($appointment->isCompleted() || $appointment->status === Appointment::STATUS_CANCELLED) {
            return Response::deny('Cannot reschedule a completed or cancelled appointment.');
        }

        if ($this->isSuperAdminStaff($user)) {
            return Response::allow();
        }

        if ($this->staffHasActiveFacilityAssignment($user, (int) $appointment->facility_id)) {
            return Response::allow();
        }

        if ($this->isAssignedProvider($user, $appointment)) {
            return Response::allow();
        }

        if ($this->isPatientOwner($user, $appointment)) {
            return Response::allow();
        }

        return Response::deny('You are not authorized to reschedule this appointment.');
    }

    public function sendReminder(User $user, Appointment $appointment): Response
    {
        if (!$appointment->isUpcoming()) {
            return Response::deny('Reminders can only be sent for upcoming appointments.');
        }

        if ($this->isSuperAdminStaff($user)) {
            return Response::allow();
        }

        if ($this->staffHasActiveFacilityAssignment($user, (int) $appointment->facility_id)) {
            return Response::allow();
        }

        return Response::deny('You are not authorized to send reminders for this appointment.');
    }

    public function restore(User $user, Appointment $appointment): Response
    {
        return $this->isSuperAdminStaff($user)
            ? Response::allow()
            : Response::deny('You are not authorized to restore appointments.');
    }

    public function forceDelete(User $user, Appointment $appointment): Response
    {
        return $this->isSuperAdminStaff($user)
            ? Response::allow()
            : Response::deny('You are not authorized to permanently delete appointments.');
    }
}
