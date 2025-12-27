<?php

namespace App\Repositories\Contracts;

use App\Models\ClinicalEncounter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

interface ClinicalEncounterRepositoryInterface
{
    /**
     * Find clinical encounter by ID
     *
     * @param int $id
     * @return ClinicalEncounter
     * @throws ModelNotFoundException
     */
    public function findById(int $id): ClinicalEncounter;

    /**
     * Find clinical encounter by UUID
     *
     * @param string $uuid
     * @return ClinicalEncounter
     * @throws ModelNotFoundException
     */
    public function findByUuid(string $uuid): ClinicalEncounter;

    /**
     * Get all clinical encounters with pagination
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Get clinical encounters by visit ID
     *
     * @param int $visitId
     * @param array $filters
     * @return Collection
     */
    public function getByVisitId(int $visitId, array $filters = []): Collection;

    /**
     * Get clinical encounters by patient ID
     *
     * @param int $patientId
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getByPatientId(int $patientId, array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Get clinical encounters by provider ID
     *
     * @param int $providerId
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getByProviderId(int $providerId, array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Create a new clinical encounter
     *
     * @param array $data
     * @return ClinicalEncounter
     */
    public function create(array $data): ClinicalEncounter;

    /**
     * Update an existing clinical encounter
     *
     * @param ClinicalEncounter $encounter
     * @param array $data
     * @return ClinicalEncounter
     */
    public function update(ClinicalEncounter $encounter, array $data): ClinicalEncounter;

    /**
     * Delete a clinical encounter (soft delete)
     *
     * @param ClinicalEncounter $encounter
     * @return bool
     */
    public function delete(ClinicalEncounter $encounter): bool;

    /**
     * Restore a soft-deleted clinical encounter
     *
     * @param ClinicalEncounter $encounter
     * @return bool
     */
    public function restore(ClinicalEncounter $encounter): bool;

    /**
     * Permanently delete a clinical encounter
     *
     * @param ClinicalEncounter $encounter
     * @return bool
     */
    public function forceDelete(ClinicalEncounter $encounter): bool;

    /**
     * Get encounters requiring immediate attention
     *
     * @param int $facilityId
     * @return Collection
     */
    public function getRequiringAttention(int $facilityId): Collection;

    /**
     * Get encounters by documentation status
     *
     * @param string $status
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getByDocumentationStatus(string $status, array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Get encounters with incomplete documentation
     *
     * @param int $facilityId
     * @param int $daysThreshold
     * @return Collection
     */
    public function getIncompleteDocumentation(int $facilityId, int $daysThreshold = 3): Collection;

    /**
     * Search encounters by various criteria
     *
     * @param array $criteria
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function search(array $criteria, int $perPage = 15): LengthAwarePaginator;
}