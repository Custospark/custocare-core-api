<?php

declare(strict_types=1);

namespace App\Repositories\Lab\Contracts;

use App\Models\LabRequest;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface LabRequestRepositoryInterface
{
    /**
     * Find request by ID.
     *
     * @param int $id
     * @return LabRequest|null
     */
    public function findById(int $id): ?LabRequest;

    /**
     * Find request by UUID.
     *
     * @param string $uuid
     * @return LabRequest|null
     */
    public function findByUuid(string $uuid): ?LabRequest;

    /**
     * Get all requests with pagination.
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllPaginated(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Get requests by facility.
     *
     * @param int $facilityId
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getByFacility(int $facilityId, array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Get requests by patient.
     *
     * @param int $patientId
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getByPatient(int $patientId, array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Get requests by visit.
     *
     * @param int $visitId
     * @return Collection
     */
    public function getByVisit(int $visitId): Collection;

    /**
     * Get requests by status.
     *
     * @param string $status
     * @param int|null $facilityId
     * @return Collection
     */
    public function getByStatus(string $status, ?int $facilityId = null): Collection;

    /**
     * Get requests by priority.
     *
     * @param string $priority
     * @param int|null $facilityId
     * @return Collection
     */
    public function getByPriority(string $priority, ?int $facilityId = null): Collection;

    /**
     * Get pending requests.
     *
     * @param int|null $facilityId
     * @return Collection
     */
    public function getPendingRequests(?int $facilityId = null): Collection;

    /**
     * Get in-progress requests.
     *
     * @param int|null $facilityId
     * @return Collection
     */
    public function getInProgressRequests(?int $facilityId = null): Collection;

    /**
     * Get requests by date range.
     *
     * @param string $startDate
     * @param string $endDate
     * @param int|null $facilityId
     * @return Collection
     */
    public function getByDateRange(string $startDate, string $endDate, ?int $facilityId = null): Collection;

    /**
     * Create a new request.
     *
     * @param array $data
     * @return LabRequest
     */
    public function create(array $data): LabRequest;

    /**
     * Update an existing request.
     *
     * @param LabRequest $request
     * @param array $data
     * @return bool
     */
    public function update(LabRequest $request, array $data): bool;

    /**
     * Delete a request (soft delete).
     *
     * @param LabRequest $request
     * @return bool
     */
    public function delete(LabRequest $request): bool;

    /**
     * Restore a soft-deleted request.
     *
     * @param int $id
     * @return bool
     */
    public function restore(int $id): bool;

    /**
     * Update request status.
     *
     * @param LabRequest $request
     * @param string $status
     * @return bool
     */
    public function updateStatus(LabRequest $request, string $status): bool;

    /**
     * Cancel request.
     *
     * @param LabRequest $request
     * @param string $reason
     * @param int|null $cancelledByStaffId
     * @return bool
     */
    public function cancel(LabRequest $request, string $reason, ?int $cancelledByStaffId = null): bool;

    /**
     * Get request with its items.
     *
     * @param int $id
     * @return LabRequest|null
     */
    public function getWithItems(int $id): ?LabRequest;

    /**
     * Get request with full details (items and results).
     *
     * @param int $id
     * @return LabRequest|null
     */
    public function getWithFullDetails(int $id): ?LabRequest;

    /**
     * Get request statistics.
     *
     * @param int $facilityId
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getRequestStatistics(int $facilityId, string $startDate, string $endDate): array;

    /**
     * Get requests requiring attention.
     *
     * @param int $facilityId
     * @return Collection
     */
    public function getRequestsRequiringAttention(int $facilityId): Collection;
}