<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AuditLogPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any audit logs.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        // Only users with specific roles can view audit logs
        return $user->hasAnyRole(['auditor', 'compliance_officer', 'system_admin', 'super_admin']);
    }

    /**
     * Determine whether the user can view the audit log.
     *
     * @param User $user
     * @param AuditLog $auditLog
     * @return bool
     */
    public function view(User $user, AuditLog $auditLog): bool
    {
        // Check if user can view any audit logs
        if (!$this->viewAny($user)) {
            return false;
        }

        // Additional restrictions based on audit log content
        if ($auditLog->containsPhiAccess()) {
            // Only specific roles can view PHI access logs
            return $user->hasAnyRole(['compliance_officer', 'system_admin', 'super_admin']);
        }

        // Facility-based restrictions
        if ($auditLog->facility_id && $user->facility_id) {
            return $auditLog->facility_id === $user->facility_id || 
                   $user->hasRole('system_admin') || 
                   $user->hasRole('super_admin');
        }

        return true;
    }

    /**
     * Determine whether the user can create audit logs.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        // Audit logs are created by the system, not by users directly
        // This endpoint is for internal system use only
        return $user->hasAnyRole(['system_admin', 'super_admin']);
    }

    /**
     * Determine whether the user can update the audit log.
     *
     * @param User $user
     * @param AuditLog $auditLog
     * @return bool
     */
    public function update(User $user, AuditLog $auditLog): bool
    {
        // Audit logs are immutable by design
        // Only system administrators can modify legal hold status
        if ($user->hasAnyRole(['system_admin', 'super_admin'])) {
            // Can only modify legal hold flag, not other fields
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the audit log.
     *
     * @param User $user
     * @param AuditLog $auditLog
     * @return bool
     */
    public function delete(User $user, AuditLog $auditLog): bool
    {
        // Audit logs should generally not be deleted
        // Only system administrators can delete, and only if not under legal hold
        if ($user->hasAnyRole(['system_admin', 'super_admin'])) {
            return !$auditLog->isUnderLegalHold();
        }

        return false;
    }

    /**
     * Determine whether the user can view HIPAA accounting.
     *
     * @param User $user
     * @return bool
     */
    public function viewHippaAccounting(User $user): bool
    {
        // Only compliance officers and system admins can view HIPAA accounting
        return $user->hasAnyRole(['compliance_officer', 'system_admin', 'super_admin']);
    }

    /**
     * Determine whether the user can view PHI access logs.
     *
     * @param User $user
     * @return bool
     */
    public function viewPhiAccessLogs(User $user): bool
    {
        // Only specific roles can view PHI access logs
        return $user->hasAnyRole(['compliance_officer', 'system_admin', 'super_admin']);
    }

    /**
     * Determine whether the user can export audit logs.
     *
     * @param User $user
     * @return bool
     */
    public function export(User $user): bool
    {
        // Only authorized roles can export audit logs
        return $user->hasAnyRole(['auditor', 'compliance_officer', 'system_admin', 'super_admin']);
    }

    /**
     * Determine whether the user can view statistics.
     *
     * @param User $user
     * @return bool
     */
    public function viewStatistics(User $user): bool
    {
        // Most users can view statistics, but with data limitations
        return $user->hasAnyRole(['auditor', 'compliance_officer', 'system_admin', 'super_admin', 'facility_admin']);
    }

    /**
     * Determine whether the user can manage legal holds.
     *
     * @param User $user
     * @return bool
     */
    public function manageLegalHold(User $user): bool
    {
        // Only system administrators and legal/compliance officers can manage legal holds
        return $user->hasAnyRole(['compliance_officer', 'system_admin', 'super_admin', 'legal_officer']);
    }

    /**
     * Determine whether the user can view logs for a specific facility.
     *
     * @param User $user
     * @param int|null $facilityId
     * @return bool
     */
    public function viewFacilityLogs(User $user, ?int $facilityId = null): bool
    {
        // System admins can view all facility logs
        if ($user->hasAnyRole(['system_admin', 'super_admin'])) {
            return true;
        }

        // Facility admins can only view logs for their own facility
        if ($user->hasRole('facility_admin') && $user->facility_id) {
            return $facilityId === null || $facilityId === $user->facility_id;
        }

        // Regular users cannot view facility logs
        return false;
    }

    /**
     * Determine whether the user can view logs for a specific patient.
     *
     * @param User $user
     * @param int|null $patientId
     * @return bool
     */
    public function viewPatientLogs(User $user, ?int $patientId = null): bool
    {
        // System admins and compliance officers can view all patient logs
        if ($user->hasAnyRole(['system_admin', 'super_admin', 'compliance_officer'])) {
            return true;
        }

        // Healthcare providers can view logs for their own patients
        if ($user->hasRole('healthcare_provider') && $patientId) {
            // Check if patient is assigned to this provider
            // This would need actual business logic implementation
            return true; // Simplified for this example
        }

        return false;
    }

    /**
     * Determine whether the user can run archival or purging processes.
     *
     * @param User $user
     * @return bool
     */
    public function runMaintenance(User $user): bool
    {
        // Only system administrators can run maintenance processes
        return $user->hasAnyRole(['system_admin', 'super_admin']);
    }
}