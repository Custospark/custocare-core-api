<?php

namespace App\Repositories\Contracts;

use App\Models\Visit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Interface for Visit Repository
 *
 * Defines the contract for Visit data persistence operations
 */
interface VisitRepositoryInterface
{
    /**
     * Find a visit by its UUID
     *
     * @param string $uuid
     * @return Visit|null
     */
    public function findByUuid(string $uuid): ?Visit;

    /**
     * Find a visit by its ID
     *
     * @param int $id
     * @return Visit|null
     */
    public function findById(int $id): ?Visit;

    /**
     * Get all visits with pagination
     *
     * @param int $perPage
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getAllPaginated(int $perPage = 15, array $filters = []): LengthAwarePaginator;

    /**
     * Get visits by facility ID
     *
     * @param int $facilityId
     * @param int $perPage
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getByFacility(int $facilityId, int $perPage = 15, array $filters = []): LengthAwarePaginator;

    /**
     * Get visits by patient ID
     *
     * @param int $patientId
     * @param int $perPage
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getByPatient(int $patientId, int $perPage = 15, array $filters = []): LengthAwarePaginator;

    /**
     * Create a new visit
     *
     * @param array $data
     * @return Visit
     */
    public function create(array $data): Visit;

    /**
     * Update an existing visit
     *
     * @param Visit $visit
     * @param array $data
     * @return Visit
     */
    public function update(Visit $visit, array $data): Visit;

    /**
     * Delete a visit (soft delete)
     *
     * @param Visit $visit
     * @return bool
     */
    public function delete(Visit $visit): bool;

    /**
     * Restore a soft-deleted visit
     *
     * @param Visit $visit
     * @return bool
     */
    public function restore(Visit $visit): bool;

    /**
     * Permanently delete a visit
     *
     * @param Visit $visit
     * @return bool
     */
    public function forceDelete(Visit $visit): bool;

    /**
     * Get visits with long waiting times
     *
     * @param int $minutesThreshold
     * @param int|null $facilityId
     * @return Collection
     */
    public function getLongWaitingVisits(int $minutesThreshold, ?int $facilityId = null): Collection;

    /**
     * Get active visits by department
     *
     * @param int $departmentId
     * @return Collection
     */
    public function getActiveVisitsByDepartment(int $departmentId): Collection;

    /**
     * Get visits by status
     *
     * @param string $status
     * @param int $perPage
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getByStatus(string $status, int $perPage = 15, array $filters = []): LengthAwarePaginator;

    /**
     * Get visits by date range
     *
     * @param string $startDate
     * @param string $endDate
     * @param int $perPage
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getByDateRange(string $startDate, string $endDate, int $perPage = 15, array $filters = []): LengthAwarePaginator;

    /**
     * Update visit phase
     *
     * @param Visit $visit
     * @param string $phase
     * @param array $additionalData
     * @return Visit
     */
    public function updatePhase(Visit $visit, string $phase, array $additionalData = []): Visit;

    /**
     * Update visit status
     *
     * @param Visit $visit
     * @param string $status
     * @param array $additionalData
     * @return Visit
     */
    public function updateStatus(Visit $visit, string $status, array $additionalData = []): Visit;

    /**
     * Discharge a visit
     *
     * @param Visit $visit
     * @param array $dischargeData
     * @return Visit
     */
    public function discharge(Visit $visit, array $dischargeData): Visit;
}