<?php

namespace App\Services\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface VisitCurrentStateServiceInterface
{
    /**
     * Get a visit current state by ID.
     *
     * @param int $id
     * @return array
     */
    public function getVisitCurrentState(int $id): array;

    /**
     * Get a visit current state by visit ID.
     *
     * @param int $visitId
     * @return array
     */
    public function getVisitCurrentStateByVisitId(int $visitId): array;

    /**
     * Get all visit current states.
     *
     * @param array $filters
     * @return array
     */
    public function getAllVisitCurrentStates(array $filters = []): array;

    /**
     * Create a new visit current state.
     *
     * @param array $data
     * @return array
     */
    public function createVisitCurrentState(array $data): array;

    /**
     * Update an existing visit current state.
     *
     * @param int $id
     * @param array $data
     * @return array
     */
    public function updateVisitCurrentState(int $id, array $data): array;

    /**
     * Delete a visit current state.
     *
     * @param int $id
     * @return array
     */
    public function deleteVisitCurrentState(int $id): array;

    /**
     * Get visit current states by facility.
     *
     * @param int $facilityId
     * @param array $filters
     * @return array
     */
    public function getVisitCurrentStatesByFacility(int $facilityId, array $filters = []): array;

    /**
     * Get visit current states by department.
     *
     * @param int $departmentId
     * @return array
     */
    public function getVisitCurrentStatesByDepartment(int $departmentId): array;

    /**
     * Get visits with critical alerts for a facility.
     *
     * @param int $facilityId
     * @return array
     */
    public function getVisitsWithCriticalAlerts(int $facilityId): array;

    /**
     * Get long waiting visits.
     *
     * @param int $thresholdMinutes
     * @param int|null $facilityId
     * @return array
     */
    public function getLongWaitingVisits(int $thresholdMinutes, ?int $facilityId = null): array;

    /**
     * Process CDC event for visit update.
     *
     * @param int $visitId
     * @param array $eventData
     * @return array
     */
    public function processVisitEvent(int $visitId, array $eventData): array;

    /**
     * Get dashboard statistics for a facility.
     *
     * @param int $facilityId
     * @return array
     */
    public function getDashboardStats(int $facilityId): array;

    /**
     * Update wait times for all active visits.
     *
     * @return array
     */
    public function updateWaitTimes(): array;
}