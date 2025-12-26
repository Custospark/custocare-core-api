<?php

namespace App\Repositories\Contracts;

use App\Models\Patient;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface PatientRepositoryInterface
{
    /**
     * Find a patient by UUID.
     */
    public function findByUuid(string $uuid): ?Patient;

    /**
     * Find a patient by user ID.
     */
    public function findByUserId(int $userId): ?Patient;

    /**
     * Find a patient by medical record number hash.
     */
    public function findByMrnHash(string $mrnHash): ?Patient;

    /**
     * Get all patients with pagination.
     */
    public function getAllPaginated(int $perPage = 15): LengthAwarePaginator;

    /**
     * Get active patients.
     */
    public function getActivePatients(int $perPage = 15): LengthAwarePaginator;

    /**
     * Search patients by criteria.
     */
    public function search(array $criteria): Collection;

    /**
     * Create a new patient.
     */
    public function create(array $data): Patient;

    /**
     * Update an existing patient.
     */
    public function update(Patient $patient, array $data): bool;

    /**
     * Soft delete a patient.
     */
    public function delete(Patient $patient): bool;

    /**
     * Restore a soft-deleted patient.
     */
    public function restore(Patient $patient): bool;

    /**
     * Permanently delete a patient.
     */
    public function forceDelete(Patient $patient): bool;

    /**
     * Get patients by blood type.
     */
    public function getByBloodType(string $bloodType): Collection;

    /**
     * Get patients requiring isolation.
     */
    public function getPatientsRequiringIsolation(): Collection;

    /**
     * Get patients with specific consent level.
     */
    public function getByConsentLevel(string $consentLevel): Collection;

    /**
     * Update patient status.
     */
    public function updateStatus(Patient $patient, string $status): bool;

    /**
     * Get patients by primary care provider.
     */
    public function getByPrimaryCareProvider(int $staffId): Collection;

    /**
     * Get patients by facility.
     */
    public function getByFacility(int $facilityId): Collection;

    /**
     * Merge patient records.
     */
    public function merge(Patient $sourcePatient, Patient $targetPatient): bool;

    /**
     * Get deceased patients.
     */
    public function getDeceasedPatients(): Collection;
}