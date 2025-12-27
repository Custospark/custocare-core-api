<?php

namespace App\Services\Contracts;

use App\Models\VisitRoute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface VisitRouteServiceInterface
{
    /**
     * Get all visit routes with pagination.
     */
    public function getAllRoutes(array $filters = [], array $with = [], int $perPage = 15): array;

    /**
     * Get a specific visit route by ID.
     */
    public function getRouteById(int $id, array $with = []): array;

    /**
     * Create a new visit route.
     */
    public function createRoute(array $data): array;

    /**
     * Update an existing visit route.
     */
    public function updateRoute(int $id, array $data): array;

    /**
     * Delete a visit route.
     */
    public function deleteRoute(int $id): array;

    /**
     * Get routes for a specific visit.
     */
    public function getRoutesForVisit(int $visitId, array $filters = []): array;

    /**
     * Get active routes for a facility.
     */
    public function getActiveRoutesForFacility(int $facilityId): array;

    /**
     * Get pending handoffs for a facility.
     */
    public function getPendingHandoffsForFacility(int $facilityId): array;

    /**
     * Acknowledge a handoff.
     */
    public function acknowledgeHandoff(int $routeId, int $staffId): array;

    /**
     * Mark a route as arrived.
     */
    public function markRouteAsArrived(int $routeId): array;

    /**
     * Mark a route as departed.
     */
    public function markRouteAsDeparted(int $routeId): array;

    /**
     * Get department throughput statistics.
     */
    public function getDepartmentThroughput(int $departmentId, string $startDate, string $endDate): array;

    /**
     * Get wait time analytics.
     */
    public function getWaitTimeAnalytics(int $facilityId, string $startDate, string $endDate): array;

    /**
     * Create multiple routes in bulk.
     */
    public function bulkCreateRoutes(array $routesData): array;

    /**
     * Validate routing logic before creation.
     */
    public function validateRouteCreation(array $data): array;
}