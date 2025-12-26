<?php

namespace App\Services\Contracts;

use App\Models\Patient;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface PatientServiceInterface
{
    /**
     * Get patient by UUID with authorization check.
     */
    public function getPatientByUuid(string $uuid): ?Patient;

    /**
     * Get patient by user ID.
     */
    public function getPatientByUserId(int $userId): ?Patient;

    /**
     * Get all patients with pagination.
     */
    public function getAllPatients(int $perPage = 15): LengthAwarePaginator;

    /**
     * Create a new patient record.
     */
    public function createPatient(array $data): Patient;

    /**
     * Update an existing patient record.
     */
    public function updatePatient(Patient $patient, array $data): bool;

    /**
     * Delete a patient record (soft delete).
     */
    public function deletePatient(Patient $patient): bool;

    /**
     * Restore a soft-deleted patient.
     */
    public function restorePatient(Patient $patient): bool;

    /**
     * Permanently delete a patient.
     */
    public function forceDeletePatient(Patient $patient): bool;

    /**
     * Search patients by criteria.
     */
    public function searchPatients(array $criteria): Collection;

    /**
     * Update patient status with validation.
     */
    public function updatePatientStatus(Patient $patient, string $status): bool;

    /**
     * Mark patient as deceased.
     */
    public function markAsDeceased(Patient $patient, \DateTimeInterface $deceasedAt): bool;

    /**
     * Merge patient records.
     */
    public function mergePatients(Patient $sourcePatient, Patient $targetPatient): bool;

    /**
     * Get patients by blood type for emergency matching.
     */
    public function getPatientsByBloodType(string $bloodType): Collection;

    /**
     * Get patients requiring isolation.
     */
    public function getPatientsRequiringIsolation(): Collection;

    /**
     * Update patient consent level.
     */
    public function updateConsentLevel(Patient $patient, string $consentLevel): bool;

    /**
     * Validate patient data before creation/update.
     */
    public function validatePatientData(array $data): array;

    /**
     * Check if patient can be updated based on status.
     */
    public function canUpdatePatient(Patient $patient): bool;

    /**
     * Get patient statistics.
     */
    public function getPatientStatistics(): array;

    /**
     * Export patient data with consent verification.
     */
    public function exportPatientData(Patient $patient): array;
}