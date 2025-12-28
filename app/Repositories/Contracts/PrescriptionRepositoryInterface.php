<?php

namespace App\Repositories\Contracts;

use App\Models\Prescription;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

interface PrescriptionRepositoryInterface
{
    /**
     * Find prescription by UUID
     *
     * @param string $uuid
     * @return Prescription|null
     */
    public function findByUuid(string $uuid): ?Prescription;

    /**
     * Find prescription by UUID or throw exception
     *
     * @param string $uuid
     * @return Prescription
     * @throws ModelNotFoundException
     */
    public function findByUuidOrFail(string $uuid): Prescription;

    /**
     * Get all prescriptions with optional filters
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAll(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Get prescriptions by patient ID
     *
     * @param int $patientId
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getByPatientId(int $patientId, array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Get prescriptions by provider ID
     *
     * @param int $providerId
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getByProviderId(int $providerId, array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Get prescriptions by facility ID
     *
     * @param int $facilityId
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getByFacilityId(int $facilityId, array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Get prescriptions needing transmission
     *
     * @param int $facilityId
     * @param int $limit
     * @return Collection
     */
    public function getPrescriptionsNeedingTransmission(int $facilityId, int $limit = 50): Collection;

    /**
     * Create new prescription
     *
     * @param array $data
     * @return Prescription
     */
    public function create(array $data): Prescription;

    /**
     * Update prescription
     *
     * @param Prescription $prescription
     * @param array $data
     * @return Prescription
     */
    public function update(Prescription $prescription, array $data): Prescription;

    /**
     * Delete prescription (soft delete)
     *
     * @param Prescription $prescription
     * @return bool
     */
    public function delete(Prescription $prescription): bool;

    /**
     * Restore soft-deleted prescription
     *
     * @param Prescription $prescription
     * @return bool
     */
    public function restore(Prescription $prescription): bool;

    /**
     * Process prescription refill
     *
     * @param Prescription $prescription
     * @param array $refillData
     * @return Prescription
     */
    public function processRefill(Prescription $prescription, array $refillData): Prescription;

    /**
     * Update dispense status
     *
     * @param Prescription $prescription
     * @param string $status
     * @param array $metadata
     * @return Prescription
     */
    public function updateDispenseStatus(Prescription $prescription, string $status, array $metadata = []): Prescription;

    /**
     * Discontinue prescription
     *
     * @param Prescription $prescription
     * @param string $reason
     * @param int|null $discontinuedById
     * @return Prescription
     */
    public function discontinue(Prescription $prescription, string $reason, ?int $discontinuedById = null): Prescription;

    /**
     * Get prescription statistics
     *
     * @param int $facilityId
     * @param array $dateRange
     * @return array
     */
    public function getStatistics(int $facilityId, array $dateRange = []): array;
}