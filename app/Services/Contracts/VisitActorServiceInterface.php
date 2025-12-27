<?php

namespace App\Services\Contracts;

use App\Models\VisitActor;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface VisitActorServiceInterface
{
    /**
     * Get all visit actors with pagination.
     *
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getAllVisitActors(array $filters = []): LengthAwarePaginator;

    /**
     * Get visit actor by ID.
     *
     * @param int $id
     * @return VisitActor|null
     */
    public function getVisitActorById(int $id): ?VisitActor;

    /**
     * Create a new visit actor participation.
     *
     * @param array $data
     * @return array
     */
    public function createVisitActor(array $data): array;

    /**
     * Update an existing visit actor.
     *
     * @param int $id
     * @param array $data
     * @return array
     */
    public function updateVisitActor(int $id, array $data): array;

    /**
     * Delete a visit actor.
     *
     * @param int $id
     * @return array
     */
    public function deleteVisitActor(int $id): array;

    /**
     * End participation for a visit actor.
     *
     * @param int $id
     * @param array $data
     * @return array
     */
    public function endParticipation(int $id, array $data): array;

    /**
     * Get visit actors by visit ID.
     *
     * @param int $visitId
     * @return Collection
     */
    public function getVisitActorsByVisit(int $visitId): Collection;

    /**
     * Get active participations for staff.
     *
     * @param int $staffId
     * @return Collection
     */
    public function getActiveStaffParticipations(int $staffId): Collection;

    /**
     * Validate participation data.
     *
     * @param array $data
     * @return array
     */
    public function validateParticipationData(array $data): array;
}