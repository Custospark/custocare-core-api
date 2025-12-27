<?php

namespace App\Repositories\Contracts;

use App\Models\VisitRoute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface VisitRouteRepositoryInterface
{
    /**
     * Get all visit routes with pagination.
     */
    public function all(array $filters = [], array $with = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Find a visit route by ID.
     */
    public function find(int $id, array $with = []): ?VisitRoute;

    /**
     * Find a visit route or fail.
     */
    public function findOrFail(int $id, array $with = []): VisitRoute;

    /**
     * Create a new visit route.
     */
    public function create(array $data): VisitRoute;

    /**
     * Update an existing visit route.
     */
    public function update(int $id, array $data): VisitRoute;

    /**
     * Delete a visit route.
     */
    public function delete(int $id): bool;

    /**
     * Get visit routes by visit ID.
     */
    public function findByVisit(int $visitId, array $filters = []): Collection;

    /**
     * Get visit routes by department ID.
     */
    public function findByDepartment(int $departmentId, array $filters = []): Collection;

    /**
     * Get active routes for a facility.
     */
    public function getActiveRoutes(int $facilityId): Collection;

    /**
     * Get pending handoff routes for a facility.
     */
    public function getPendingHandoffs(int $facilityId): Collection;

    /**
     * Get routes within a date range.
     */
    public function getRoutesBetweenDates(int $facilityId, string $startDate, string $endDate): Collection;

    /**
     * Get department throughput statistics.
     */
    public function getDepartmentThroughput(int $departmentId, string $startDate, string $endDate): array;

    /**
     * Get average wait times by routing reason.
     */
    public function getAverageWaitTimes(int $facilityId, string $startDate, string $endDate): array;

    /**
     * Bulk create visit routes.
     */
    public function bulkCreate(array $routes): Collection;
}