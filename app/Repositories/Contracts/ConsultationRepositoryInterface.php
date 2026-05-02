<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Consultation;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ConsultationRepositoryInterface
{
    /**
     * Find consultation by ID.
     *
     * @param int $id
     * @return Consultation|null
     */
    public function findById(int $id): ?Consultation;

    /**
     * Find consultation by ID with relationships.
     *
     * @param int $id
     * @param array $relations
     * @return Consultation|null
     */
    public function findByIdWithRelations(int $id, array $relations = []): ?Consultation;

    /**
     * Get all consultations with pagination.
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllPaginated(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Get consultations by patient.
     *
     * @param int $patientId
     * @param array $filters
     * @param int $limit
     * @return Collection
     */
    public function getByPatient(int $patientId, array $filters = [], int $limit = 50): Collection;

    /**
     * Get paginated consultations by patient.
     *
     * @param int $patientId
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPaginatedByPatient(int $patientId, array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Get consultations by visit.
     *
     * @param int $visitId
     * @return Collection
     */
    public function getByVisit(int $visitId): Collection;

    /**
     * Get consultations by facility.
     *
     * @param int $facilityId
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getByFacility(int $facilityId, array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Get consultations by consultant.
     *
     * @param int $consultantStaffId
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getByConsultant(int $consultantStaffId, array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Get pending consultations.
     *
     * @param int|null $facilityId
     * @param int $limit
     * @return Collection
     */
    public function getPendingConsultations(?int $facilityId = null, int $limit = 50): Collection;

    /**
     * Get urgent consultations.
     *
     * @param int|null $facilityId
     * @param int $limit
     * @return Collection
     */
    public function getUrgentConsultations(?int $facilityId = null, int $limit = 50): Collection;

    /**
     * Get overdue consultations.
     *
     * @param int|null $facilityId
     * @param int $limit
     * @return Collection
     */
    public function getOverdueConsultations(?int $facilityId = null, int $limit = 50): Collection;

    /**
     * Create a new consultation.
     *
     * @param array $data
     * @return Consultation
     */
    public function create(array $data): Consultation;

    /**
     * Update an existing consultation.
     *
     * @param Consultation $consultation
     * @param array $data
     * @return bool
     */
    public function update(Consultation $consultation, array $data): bool;

    /**
     * Delete a consultation (soft delete).
     *
     * @param Consultation $consultation
     * @return bool
     */
    public function delete(Consultation $consultation): bool;

    /**
     * Restore a soft-deleted consultation.
     *
     * @param int $id
     * @return bool
     */
    public function restore(int $id): bool;

    /**
     * Force delete a consultation.
     *
     * @param int $id
     * @return bool
     */
    public function forceDelete(int $id): bool;

    /**
     * Update consultation status.
     *
     * @param Consultation $consultation
     * @param string $status
     * @return bool
     */
    public function updateStatus(Consultation $consultation, string $status): bool;

    /**
     * Get consultation count by status for a facility.
     *
     * @param int $facilityId
     * @return array
     */
    public function getCountByStatus(int $facilityId): array;

    /**
     * Get consultations by specialty.
     *
     * @param string $specialty
     * @param int|null $facilityId
     * @param int $limit
     * @return Collection
     */
    public function getBySpecialty(string $specialty, ?int $facilityId = null, int $limit = 50): Collection;

    /**
     * Get consultation statistics for a date range.
     *
     * @param int $facilityId
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getConsultationStatistics(int $facilityId, string $startDate, string $endDate): array;
}