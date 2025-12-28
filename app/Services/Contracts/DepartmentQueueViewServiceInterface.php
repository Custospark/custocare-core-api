<?php

namespace App\Services\Contracts;

use App\Models\DepartmentQueueView;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

interface DepartmentQueueViewServiceInterface
{
    /**
     * Get department queue view by ID
     */
    public function getQueueViewById(int $id): ?DepartmentQueueView;

    /**
     * Get queue view by department and type
     */
    public function getQueueViewByDepartmentAndType(int $departmentId, string $queueType): ?DepartmentQueueView;

    /**
     * Get all queue views for a facility
     */
    public function getFacilityQueueViews(int $facilityId, array $filters = []): Collection;

    /**
     * Get critical queues for alerting
     */
    public function getCriticalQueues(int $facilityId): Collection;

    /**
     * Get current dashboard statistics
     */
    public function getDashboardStatistics(int $facilityId): array;

    /**
     * Create a new queue view snapshot
     */
    public function createQueueView(array $data): array;

    /**
     * Update queue view metrics
     */
    public function updateQueueView(int $id, array $data): array;

    /**
     * Batch update queue views (for 30-second refresh)
     */
    public function batchUpdateQueueViews(array $queueData): array;

    /**
     * Delete a queue view
     */
    public function deleteQueueView(int $id): array;

    /**
     * Get wait time analysis for a department
     */
    public function analyzeWaitTimes(int $departmentId, string $queueType): array;

    /**
     * Get capacity analysis across facility
     */
    public function analyzeCapacity(int $facilityId): array;

    /**
     * Generate queue predictions for next hour
     */
    public function generatePredictions(int $departmentId, string $queueType): array;

    /**
     * Validate queue view data
     */
    public function validateQueueData(array $data): array;
}