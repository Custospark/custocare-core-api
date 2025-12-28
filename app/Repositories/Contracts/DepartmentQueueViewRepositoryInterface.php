<?php

namespace App\Repositories\Contracts;

use App\Models\DepartmentQueueView;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface DepartmentQueueViewRepositoryInterface
{
    /**
     * Find department queue view by ID
     */
    public function findById(int $id): ?DepartmentQueueView;

    /**
     * Find by department and queue type
     */
    public function findByDepartmentAndType(int $departmentId, string $queueType): ?DepartmentQueueView;

    /**
     * Get all queue views for a facility
     */
    public function getByFacilityId(int $facilityId, array $filters = []): Collection;

    /**
     * Get paginated queue views for a department
     */
    public function getPaginatedByDepartment(int $departmentId, int $perPage = 20): LengthAwarePaginator;

    /**
     * Get critical queue views across facility
     */
    public function getCriticalQueues(int $facilityId): Collection;

    /**
     * Get current snapshots (last 30 seconds)
     */
    public function getCurrentSnapshots(array $departmentIds = []): Collection;

    /**
     * Create a new department queue view
     */
    public function create(array $data): DepartmentQueueView;

    /**
     * Update department queue view
     */
    public function update(DepartmentQueueView $queueView, array $data): bool;

    /**
     * Delete department queue view
     */
    public function delete(DepartmentQueueView $queueView): bool;

    /**
     * Batch update multiple queue views
     */
    public function batchUpdate(array $updates): bool;

    /**
     * Get queue statistics for dashboard
     */
    public function getDashboardStatistics(int $facilityId): array;

    /**
     * Get wait time trends for a department
     */
    public function getWaitTimeTrends(int $departmentId, string $queueType, int $hours = 24): Collection;
}