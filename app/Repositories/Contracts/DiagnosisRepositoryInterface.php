<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Diagnosis;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface DiagnosisRepositoryInterface
{
    /**
     * Find diagnosis by ID.
     *
     * @param int $id
     * @return Diagnosis|null
     */
    public function findById(int $id): ?Diagnosis;

    /**
     * Find diagnosis by ID with relationships.
     *
     * @param int $id
     * @param array $relations
     * @return Diagnosis|null
     */
    public function findByIdWithRelations(int $id, array $relations = []): ?Diagnosis;

    /**
     * Get all diagnoses with pagination.
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllPaginated(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Get diagnoses by patient.
     *
     * @param int $patientId
     * @param array $filters
     * @return Collection
     */
    public function getByPatient(int $patientId, array $filters = []): Collection;

    /**
     * Get active diagnoses by patient.
     *
     * @param int $patientId
     * @return Collection
     */
    public function getActiveByPatient(int $patientId): Collection;

    /**
     * Get primary diagnoses by patient.
     *
     * @param int $patientId
     * @return Collection
     */
    public function getPrimaryByPatient(int $patientId): Collection;

    /**
     * Get diagnoses by visit.
     *
     * @param int $visitId
     * @return Collection
     */
    public function getByVisit(int $visitId): Collection;

    /**
     * Get diagnoses by facility.
     *
     * @param int $facilityId
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getByFacility(int $facilityId, array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Get diagnoses by ICD code.
     *
     * @param string $code
     * @param int|null $facilityId
     * @return Collection
     */
    public function getByCode(string $code, ?int $facilityId = null): Collection;

    /**
     * Get verified diagnoses.
     *
     * @param int|null $facilityId
     * @param int $limit
     * @return Collection
     */
    public function getVerifiedDiagnoses(?int $facilityId = null, int $limit = 50): Collection;

    /**
     * Create a new diagnosis.
     *
     * @param array $data
     * @return Diagnosis
     */
    public function create(array $data): Diagnosis;

    /**
     * Update an existing diagnosis.
     *
     * @param Diagnosis $diagnosis
     * @param array $data
     * @return bool
     */
    public function update(Diagnosis $diagnosis, array $data): bool;

    /**
     * Delete a diagnosis (soft delete).
     *
     * @param Diagnosis $diagnosis
     * @return bool
     */
    public function delete(Diagnosis $diagnosis): bool;

    /**
     * Restore a soft-deleted diagnosis.
     *
     * @param int $id
     * @return bool
     */
    public function restore(int $id): bool;

    /**
     * Force delete a diagnosis.
     *
     * @param int $id
     * @return bool
     */
    public function forceDelete(int $id): bool;

    /**
     * Update verification status.
     *
     * @param Diagnosis $diagnosis
     * @param string $status
     * @param int|null $verifiedByStaffId
     * @return bool
     */
    public function updateVerificationStatus(Diagnosis $diagnosis, string $status, ?int $verifiedByStaffId = null): bool;

    /**
     * Get diagnosis count by type for a patient.
     *
     * @param int $patientId
     * @return array
     */
    public function getCountByType(int $patientId): array;

    /**
     * Get most common diagnoses in a facility.
     *
     * @param int $facilityId
     * @param int $limit
     * @return Collection
     */
    public function getMostCommonDiagnoses(int $facilityId, int $limit = 10): Collection;

    /**
     * Search diagnoses by code or description.
     *
     * @param string $searchTerm
     * @param int|null $facilityId
     * @param int $limit
     * @return Collection
     */
    public function searchDiagnoses(string $searchTerm, ?int $facilityId = null, int $limit = 20): Collection;

    /**
     * Get diagnoses by date range.
     *
     * @param int $patientId
     * @param string $startDate
     * @param string $endDate
     * @return Collection
     */
    public function getByDateRange(int $patientId, string $startDate, string $endDate): Collection;
}