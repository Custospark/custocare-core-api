<?php

namespace App\Repositories\Contracts;

use App\Models\MedicationDispense;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface MedicationDispenseRepositoryInterface
{
    /**
     * Find medication dispense by UUID.
     *
     * @param string $uuid
     * @return MedicationDispense|null
     */
    public function findByUuid(string $uuid): ?MedicationDispense;

    /**
     * Find medication dispense by ID.
     *
     * @param int $id
     * @return MedicationDispense|null
     */
    public function findById(int $id): ?MedicationDispense;

    /**
     * Get all medication dispenses with pagination.
     *
     * @param array $filters
     * @param int $perPage
     * @param array $relations
     * @return LengthAwarePaginator
     */
    public function getAllPaginated(array $filters = [], int $perPage = 20, array $relations = []): LengthAwarePaginator;

    /**
     * Get dispenses by prescription ID.
     *
     * @param int $prescriptionId
     * @param array $relations
     * @return Collection
     */
    public function getByPrescriptionId(int $prescriptionId, array $relations = []): Collection;

    /**
     * Get dispenses by patient ID.
     *
     * @param int $patientId
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getByPatientId(int $patientId, array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Get dispenses by facility ID.
     *
     * @param int $facilityId
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getByFacilityId(int $facilityId, array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Create a new medication dispense.
     *
     * @param array $data
     * @return MedicationDispense
     */
    public function create(array $data): MedicationDispense;

    /**
     * Update an existing medication dispense.
     *
     * @param int $id
     * @param array $data
     * @return MedicationDispense
     */
    public function update(int $id, array $data): MedicationDispense;

    /**
     * Update by UUID.
     *
     * @param string $uuid
     * @param array $data
     * @return MedicationDispense
     */
    public function updateByUuid(string $uuid, array $data): MedicationDispense;

    /**
     * Delete a medication dispense.
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool;

    /**
     * Delete by UUID.
     *
     * @param string $uuid
     * @return bool
     */
    public function deleteByUuid(string $uuid): bool;

    /**
     * Verify a dispense (4-eyes principle).
     *
     * @param int $id
     * @param int $pharmacistId
     * @param string $notes
     * @return MedicationDispense
     */
    public function verifyDispense(int $id, int $pharmacistId, string $notes): MedicationDispense;

    /**
     * Mark dispense as picked up.
     *
     * @param int $id
     * @param array $pickupData
     * @return MedicationDispense
     */
    public function markAsPickedUp(int $id, array $pickupData): MedicationDispense;

    /**
     * Update dispense status.
     *
     * @param int $id
     * @param string $status
     * @param string|null $reason
     * @return MedicationDispense
     */
    public function updateStatus(int $id, string $status, ?string $reason = null): MedicationDispense;

    /**
     * Get dispense statistics for a facility.
     *
     * @param int $facilityId
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getFacilityStatistics(int $facilityId, string $startDate, string $endDate): array;
}