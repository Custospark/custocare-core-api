<?php

namespace App\Services\Contracts;

use App\Models\Visit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Interface for Visit Service
 *
 * Defines the contract for Visit business logic operations
 */
interface VisitServiceInterface
{
    /**
     * Get all visits with pagination
     *
     * @param array $filters
     * @param int $perPage
     * @return array
     */
    public function getAllVisits(array $filters = [], int $perPage = 15): array;

    /**
     * Get visit by UUID
     *
     * @param string $uuid
     * @return array
     */
    public function getVisitByUuid(string $uuid): array;

    /**
     * Get visits by facility ID
     *
     * @param int $facilityId
     * @param array $filters
     * @param int $perPage
     * @return array
     */
    public function getVisitsByFacility(int $facilityId, array $filters = [], int $perPage = 15): array;

    /**
     * Get visits by patient ID
     *
     * @param int $patientId
     * @param array $filters
     * @param int $perPage
     * @return array
     */
    public function getVisitsByPatient(int $patientId, array $filters = [], int $perPage = 15): array;

    /**
     * Create a new visit
     *
     * @param array $data
     * @param int $userId
     * @return array
     */
    public function createVisit(array $data, int $userId): array;

    /**
     * Update an existing visit
     *
     * @param string $uuid
     * @param array $data
     * @param int $userId
     * @return array
     */
    public function updateVisit(string $uuid, array $data, int $userId): array;

    /**
     * Delete a visit (soft delete)
     *
     * @param string $uuid
     * @param int $userId
     * @return array
     */
    public function deleteVisit(string $uuid, int $userId): array;

    /**
     * Restore a soft-deleted visit
     *
     * @param string $uuid
     * @param int $userId
     * @return array
     */
    public function restoreVisit(string $uuid, int $userId): array;

    /**
     * Get active visits by department
     *
     * @param int $departmentId
     * @return array
     */
    public function getActiveVisitsByDepartment(int $departmentId): array;

    /**
     * Update visit phase
     *
     * @param string $uuid
     * @param string $phase
     * @param array $additionalData
     * @param int $userId
     * @return array
     */
    public function updateVisitPhase(string $uuid, string $phase, array $additionalData = [], ?int $userId = null): array;

    /**
     * Update visit status
     *
     * @param string $uuid
     * @param string $status
     * @param array $additionalData
     * @param int|null $staffId Authenticated staff id for audit (updated_by_staff_id)
     * @return array
     */
    public function updateVisitStatus(string $uuid, string $status, array $additionalData = [], ?int $staffId = null): array;

    /**
     * Discharge a visit
     *
     * @param string $uuid
     * @param array $dischargeData
     * @param int $userId
     * @return array
     */
    public function dischargeVisit(string $uuid, array $dischargeData, int $userId): array;

    /**
     * Get long waiting visits
     *
     * @param int $minutesThreshold
     * @param int|null $facilityId
     * @return array
     */
    public function getLongWaitingVisits(int $minutesThreshold, ?int $facilityId = null): array;

    /**
     * Get visit statistics
     *
     * @param int|null $facilityId
     * @param string|null $dateRange
     * @return array
     */
    public function getVisitStatistics(?int $facilityId = null, ?string $dateRange = null): array;

    /**
     * Start clinical care for a visit
     *
     * @param string $uuid
     * @param int $userId
     * @return array
     */
    public function startClinicalCare(string $uuid, int $userId): array;

    /**
     * End clinical care for a visit
     *
     * @param string $uuid
     * @param int $userId
     * @return array
     */
    public function endClinicalCare(string $uuid, int $userId): array;

    /**
     * Cancel a visit
     *
     * @param string $uuid
     * @param string $reason
     * @param int $userId
     * @return array
     */
    public function cancelVisit(string $uuid, string $reason, int $userId): array;

    /**
     * Register a patient for a visit
     *
     * @param string $uuid
     * @param array $registrationData
     * @param int $userId
     * @return array
     */
    public function registerVisit(string $uuid, array $registrationData, int $userId): array;

    /**
     * Bulk reassign all active/in-progress visits from one staff to another within a facility.
     *
     * @param int $fromStaffId
     * @param int $toStaffId
     * @param int $facilityId
     * @return array{success: bool, reassigned_count: int, message: string}
     */
    public function bulkReassignStaff(int $fromStaffId, int $toStaffId, int $facilityId): array;
}