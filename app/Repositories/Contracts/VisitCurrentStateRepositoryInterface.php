<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface VisitCurrentStateRepositoryInterface
{
    /**
     * Find a visit current state by ID.
     *
     * @param int $id
     * @return \App\Models\VisitCurrentState|null
     */
    public function findById(int $id): ?\App\Models\VisitCurrentState;

    /**
     * Find a visit current state by visit ID.
     *
     * @param int $visitId
     * @return \App\Models\VisitCurrentState|null
     */
    public function findByVisitId(int $visitId): ?\App\Models\VisitCurrentState;

    /**
     * Get all visit current states.
     *
     * @return Collection
     */
    public function all(): Collection;

    /**
     * Paginate visit current states.
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator;

    /**
     * Create a new visit current state.
     *
     * @param array $data
     * @return \App\Models\VisitCurrentState
     */
    public function create(array $data): \App\Models\VisitCurrentState;

    /**
     * Update an existing visit current state.
     *
     * @param int $id
     * @param array $data
     * @return \App\Models\VisitCurrentState
     */
    public function update(int $id, array $data): \App\Models\VisitCurrentState;

    /**
     * Delete a visit current state.
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool;

    /**
     * Get visit current states by facility.
     *
     * @param int $facilityId
     * @param array $filters
     * @return Collection
     */
    public function getByFacility(int $facilityId, array $filters = []): Collection;

    /**
     * Get visit current states by department.
     *
     * @param int $departmentId
     * @return Collection
     */
    public function getByDepartment(int $departmentId): Collection;

    /**
     * Get visit current states by phase.
     *
     * @param string $phase
     * @return Collection
     */
    public function getByPhase(string $phase): Collection;

    /**
     * Get visits with critical alerts.
     *
     * @param int $facilityId
     * @return Collection
     */
    public function getWithCriticalAlerts(int $facilityId): Collection;

    /**
     * Get visits waiting beyond threshold.
     *
     * @param int $thresholdMinutes
     * @param int|null $facilityId
     * @return Collection
     */
    public function getLongWaitingVisits(int $thresholdMinutes, ?int $facilityId = null): Collection;

    /**
     * Update from CDC event.
     *
     * @param int $visitId
     * @param array $eventData
     * @return \App\Models\VisitCurrentState
     */
    public function updateFromEvent(int $visitId, array $eventData): \App\Models\VisitCurrentState;

    /**
     * Get dashboard statistics.
     *
     * @param int $facilityId
     * @return array
     */
    public function getDashboardStats(int $facilityId): array;
}