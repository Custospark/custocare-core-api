<?php

namespace App\Repositories\Contracts;

use App\Models\VisitActor;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface VisitActorRepositoryInterface
{
    /**
     * Find a visit actor by ID.
     *
     * @param int $id
     * @return VisitActor|null
     */
    public function find(int $id): ?VisitActor;

    /**
     * Get all visit actors with pagination.
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator;

    /**
     * Get visit actors by visit ID.
     *
     * @param int $visitId
     * @return Collection
     */
    public function findByVisit(int $visitId): Collection;

    /**
     * Get visit actors by staff ID.
     *
     * @param int $staffId
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function findByStaff(int $staffId, array $filters = []): LengthAwarePaginator;

    /**
     * Create a new visit actor.
     *
     * @param array $data
     * @return VisitActor
     */
    public function create(array $data): VisitActor;

    /**
     * Update an existing visit actor.
     *
     * @param int $id
     * @param array $data
     * @return VisitActor
     */
    public function update(int $id, array $data): VisitActor;

    /**
     * Delete a visit actor.
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool;

    /**
     * End participation for a visit actor.
     *
     * @param int $id
     * @param array $data
     * @return VisitActor
     */
    public function endParticipation(int $id, array $data): VisitActor;

    /**
     * Get active participations for staff.
     *
     * @param int $staffId
     * @return Collection
     */
    public function getActiveParticipations(int $staffId): Collection;

    /**
     * Check for duplicate participation.
     *
     * @param int $visitId
     * @param int $staffId
     * @param string $participationType
     * @param string $startedAt
     * @return bool
     */
    public function isDuplicateParticipation(
        int $visitId,
        int $staffId,
        string $participationType,
        string $startedAt
    ): bool;
}