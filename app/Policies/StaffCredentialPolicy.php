<?php

namespace App\Policies;

use App\Models\Staff;
use App\Models\StaffCredential;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class StaffCredentialPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(Staff $staff): Response
    {
        // Only HR, Compliance, and Managers can view all credentials
        return in_array($staff->role, ['hr', 'compliance', 'manager', 'admin'])
            ? Response::allow()
            : Response::deny('You do not have permission to view all credentials.');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(Staff $staff, StaffCredential $credential): Response
    {
        // Staff can view their own credentials
        if ($staff->id === $credential->staff_id) {
            return Response::allow();
        }

        // HR, Compliance, and Managers can view any credentials
        if (in_array($staff->role, ['hr', 'compliance', 'manager', 'admin'])) {
            return Response::allow();
        }

        // Supervisors can view credentials of their team members
        if ($staff->role === 'supervisor') {
            // This assumes a relationship between supervisor and staff
            // You'll need to implement your own logic here
            $isTeamMember = false; // Implement your team check logic
            return $isTeamMember
                ? Response::allow()
                : Response::deny('You can only view credentials of your team members.');
        }

        return Response::deny('You do not have permission to view this credential.');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(Staff $staff): Response
    {
        // HR, Compliance, and Managers can create credentials
        return in_array($staff->role, ['hr', 'compliance', 'manager', 'admin'])
            ? Response::allow()
            : Response::deny('You do not have permission to create credentials.');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(Staff $staff, StaffCredential $credential): Response
    {
        // Only HR and Compliance can update credentials
        if (!in_array($staff->role, ['hr', 'compliance', 'admin'])) {
            return Response::deny('You do not have permission to update credentials.');
        }

        // Cannot update verified current credentials (must supersede)
        if ($credential->is_current && $credential->verification_status === 'verified') {
            return Response::deny('Cannot update current verified credential. Please supersede it instead.');
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(Staff $staff, StaffCredential $credential): Response
    {
        // Only HR and Compliance can delete credentials
        if (!in_array($staff->role, ['hr', 'compliance', 'admin'])) {
            return Response::deny('You do not have permission to delete credentials.');
        }

        // Cannot delete current verified credentials
        if ($credential->is_current && $credential->verification_status === 'verified') {
            return Response::deny('Cannot delete current verified credential. Please supersede it first.');
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(Staff $staff, StaffCredential $credential): Response
    {
        // Only HR and Compliance can restore credentials
        return in_array($staff->role, ['hr', 'compliance', 'admin'])
            ? Response::allow()
            : Response::deny('You do not have permission to restore credentials.');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(Staff $staff, StaffCredential $credential): Response
    {
        // Only Admins can permanently delete credentials
        return $staff->role === 'admin'
            ? Response::allow()
            : Response::deny('You do not have permission to permanently delete credentials.');
    }

    /**
     * Determine whether the user can verify credentials.
     */
    public function verify(Staff $staff, StaffCredential $credential): Response
    {
        // Only Compliance officers and Admins can verify credentials
        if (!in_array($staff->role, ['compliance', 'admin'])) {
            return Response::deny('You do not have permission to verify credentials.');
        }

        // Cannot verify already verified credentials
        if ($credential->verification_status === 'verified') {
            return Response::deny('This credential is already verified.');
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can supersede credentials.
     */
    public function supersede(Staff $staff, StaffCredential $credential): Response
    {
        // Only HR and Compliance can supersede credentials
        if (!in_array($staff->role, ['hr', 'compliance', 'admin'])) {
            return Response::deny('You do not have permission to supersede credentials.');
        }

        // Only current credentials can be superseded
        if (!$credential->is_current) {
            return Response::deny('Only current credentials can be superseded.');
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can view credential statistics.
     */
    public function viewStatistics(Staff $staff): Response
    {
        // Only HR, Compliance, and Managers can view statistics
        return in_array($staff->role, ['hr', 'compliance', 'manager', 'admin'])
            ? Response::allow()
            : Response::deny('You do not have permission to view credential statistics.');
    }

    /**
     * Determine whether the user can view expiring credentials.
     */
    public function viewExpiring(Staff $staff): Response
    {
        // Only HR, Compliance, and Managers can view expiring credentials
        return in_array($staff->role, ['hr', 'compliance', 'manager', 'admin'])
            ? Response::allow()
            : Response::deny('You do not have permission to view expiring credentials.');
    }
}