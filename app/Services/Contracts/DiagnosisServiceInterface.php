<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Models\Diagnosis;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface DiagnosisServiceInterface
{
    /**
     * Get all diagnoses with pagination.
     *
     * @param array $filters
     * @param int $perPage
     * @return array{success: bool, data: LengthAwarePaginator|array, message: string, error?: string}
     */
    public function getAllDiagnoses(array $filters = [], int $perPage = 20): array;

    /**
     * Get diagnosis by ID.
     *
     * @param int $id
     * @return array{success: bool, data: Diagnosis|array|null, message: string, error?: string}
     */
    public function getDiagnosisById(int $id): array;

    /**
     * Get diagnoses for a patient.
     *
     * @param int $patientId
     * @param array $filters
     * @return array{success: bool, data: Collection|array, message: string, error?: string}
     */
    public function getPatientDiagnoses(int $patientId, array $filters = []): array;

    /**
     * Get active diagnoses for a patient.
     *
     * @param int $patientId
     * @return array{success: bool, data: Collection|array, message: string, error?: string}
     */
    public function getActivePatientDiagnoses(int $patientId): array;

    /**
     * Get primary diagnoses for a patient.
     *
     * @param int $patientId
     * @return array{success: bool, data: Collection|array, message: string, error?: string}
     */
    public function getPrimaryPatientDiagnoses(int $patientId): array;

    /**
     * Get diagnoses for a visit.
     *
     * @param int $visitId
     * @return array{success: bool, data: Collection|array, message: string, error?: string}
     */
    public function getVisitDiagnoses(int $visitId): array;

    /**
     * Create a new diagnosis.
     *
     * @param array $data
     * @param int $createdByStaffId
     * @return array{success: bool, data: Diagnosis|array|null, message: string, error?: string}
     */
    public function createDiagnosis(array $data, int $createdByStaffId): array;

    /**
     * Update an existing diagnosis.
     *
     * @param int $id
     * @param array $data
     * @param int $updatedByStaffId
     * @return array{success: bool, data: Diagnosis|array|null, message: string, error?: string}
     */
    public function updateDiagnosis(int $id, array $data, int $updatedByStaffId): array;

    /**
     * Delete a diagnosis (soft delete).
     *
     * @param int $id
     * @return array{success: bool, message: string, error?: string}
     */
    public function deleteDiagnosis(int $id): array;

    /**
     * Restore a soft-deleted diagnosis.
     *
     * @param int $id
     * @return array{success: bool, data: Diagnosis|array|null, message: string, error?: string}
     */
    public function restoreDiagnosis(int $id): array;

    /**
     * Force delete a diagnosis.
     *
     * @param int $id
     * @return array{success: bool, message: string, error?: string}
     */
    public function forceDeleteDiagnosis(int $id): array;

    /**
     * Verify a diagnosis.
     *
     * @param int $id
     * @param int $verifiedByStaffId
     * @return array{success: bool, data: Diagnosis|array|null, message: string, error?: string}
     */
    public function verifyDiagnosis(int $id, int $verifiedByStaffId): array;

    /**
     * Mark diagnosis as disputed.
     *
     * @param int $id
     * @param string|null $reason
     * @return array{success: bool, data: Diagnosis|array|null, message: string, error?: string}
     */
    public function disputeDiagnosis(int $id, ?string $reason = null): array;

    /**
     * Mark diagnosis as resolved.
     *
     * @param int $id
     * @param string|null $resolutionNotes
     * @return array{success: bool, data: Diagnosis|array|null, message: string, error?: string}
     */
    public function resolveDiagnosis(int $id, ?string $resolutionNotes = null): array;

    /**
     * Reactivate a resolved diagnosis.
     *
     * @param int $id
     * @return array{success: bool, data: Diagnosis|array|null, message: string, error?: string}
     */
    public function reactivateDiagnosis(int $id): array;

    /**
     * Get diagnosis statistics for a patient.
     *
     * @param int $patientId
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getPatientDiagnosisStatistics(int $patientId): array;

    /**
     * Get most common diagnoses in a facility.
     *
     * @param int $facilityId
     * @param int $limit
     * @return array{success: bool, data: Collection|array, message: string, error?: string}
     */
    public function getMostCommonDiagnoses(int $facilityId, int $limit = 10): array;

    /**
     * Search diagnoses by code or description.
     *
     * @param string $searchTerm
     * @param int|null $facilityId
     * @param int $limit
     * @return array{success: bool, data: Collection|array, message: string, error?: string}
     */
    public function searchDiagnoses(string $searchTerm, ?int $facilityId = null, int $limit = 20): array;

    /**
     * Get ICD code suggestions.
     *
     * @param string $searchTerm
     * @param int $limit
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function suggestIcdCodes(string $searchTerm, int $limit = 10): array;

    /**
     * Validate diagnosis uniqueness for a visit.
     *
     * @param int $visitId
     * @param string $diagnosisCode
     * @param string $diagnosisType
     * @param int|null $excludeId
     * @return bool
     */
    public function isDiagnosisUniqueForVisit(int $visitId, string $diagnosisCode, string $diagnosisType, ?int $excludeId = null): bool;
}