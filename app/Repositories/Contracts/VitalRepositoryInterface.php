<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Vital;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface VitalRepositoryInterface
{
    /**
     * Find vital record by ID.
     *
     * @param int $id
     * @return Vital|null
     */
    public function findById(int $id): ?Vital;

    /**
     * Find vital record by ID with relationships.
     *
     * @param int $id
     * @param array $relations
     * @return Vital|null
     */
    public function findByIdWithRelations(int $id, array $relations = []): ?Vital;

    /**
     * Get all vital records with pagination.
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllPaginated(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Get vital records by patient.
     *
     * @param int $patientId
     * @param array $filters
     * @param int $limit
     * @return Collection
     */
    public function getByPatient(int $patientId, array $filters = [], int $limit = 50): Collection;

    /**
     * Get paginated vital records by patient.
     *
     * @param int $patientId
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPaginatedByPatient(int $patientId, array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Get vital records by visit.
     *
     * @param int $visitId
     * @return Collection
     */
    public function getByVisit(int $visitId): Collection;

    /**
     * Get latest vital record for a patient.
     *
     * @param int $patientId
     * @return Vital|null
     */
    public function getLatestByPatient(int $patientId): ?Vital;

    /**
     * Get vital records by date range for a patient.
     *
     * @param int $patientId
     * @param string $startDate
     * @param string $endDate
     * @return Collection
     */
    public function getByDateRange(int $patientId, string $startDate, string $endDate): Collection;

    /**
     * Get abnormal vital records.
     *
     * @param int|null $facilityId
     * @param int $limit
     * @return Collection
     */
    public function getAbnormalVitals(?int $facilityId = null, int $limit = 50): Collection;

    /**
     * Get critical vital records requiring attention.
     *
     * @param int|null $facilityId
     * @param int $limit
     * @return Collection
     */
    public function getCriticalVitals(?int $facilityId = null, int $limit = 50): Collection;

    /**
     * Create a new vital record.
     *
     * @param array $data
     * @return Vital
     */
    public function create(array $data): Vital;

    /**
     * Update an existing vital record.
     *
     * @param Vital $vital
     * @param array $data
     * @return bool
     */
    public function update(Vital $vital, array $data): bool;

    /**
     * Delete a vital record.
     *
     * @param Vital $vital
     * @return bool
     */
    public function delete(Vital $vital): bool;

    /**
     * Get vital signs trend for a patient.
     *
     * @param int $patientId
     * @param string $vitalType
     * @param int $limit
     * @return Collection
     */
    public function getVitalTrend(int $patientId, string $vitalType, int $limit = 10): Collection;

    /**
     * Get vital statistics for a facility.
     *
     * @param int $facilityId
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getVitalStatistics(int $facilityId, string $startDate, string $endDate): array;

    /**
     * Get average vitals for a patient over a period.
     *
     * @param int $patientId
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getAverageVitals(int $patientId, string $startDate, string $endDate): array;
}