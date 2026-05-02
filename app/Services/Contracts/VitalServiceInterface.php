<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Models\Vital;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface VitalServiceInterface
{
    /**
     * Get all vital records with pagination.
     *
     * @param array $filters
     * @param int $perPage
     * @return array{success: bool, data: LengthAwarePaginator|array, message: string, error?: string}
     */
    public function getAllVitals(array $filters = [], int $perPage = 20): array;

    /**
     * Get vital record by ID.
     *
     * @param int $id
     * @return array{success: bool, data: Vital|array|null, message: string, error?: string}
     */
    public function getVitalById(int $id): array;

    /**
     * Get vital records for a patient.
     *
     * @param int $patientId
     * @param array $filters
     * @param int $perPage
     * @return array{success: bool, data: LengthAwarePaginator|array, message: string, error?: string}
     */
    public function getPatientVitals(int $patientId, array $filters = [], int $perPage = 20): array;

    /**
     * Get latest vital record for a patient.
     *
     * @param int $patientId
     * @return array{success: bool, data: Vital|array|null, message: string, error?: string}
     */
    public function getLatestPatientVitals(int $patientId): array;

    /**
     * Get vital records for a visit.
     *
     * @param int $visitId
     * @return array{success: bool, data: Collection|array, message: string, error?: string}
     */
    public function getVisitVitals(int $visitId): array;

    /**
     * Create a new vital record.
     *
     * @param array $data
     * @param int $recordedByStaffId
     * @return array{success: bool, data: Vital|array|null, message: string, error?: string}
     */
    public function createVital(array $data, int $recordedByStaffId): array;

    /**
     * Update an existing vital record.
     *
     * @param int $id
     * @param array $data
     * @param int $updatedByStaffId
     * @return array{success: bool, data: Vital|array|null, message: string, error?: string}
     */
    public function updateVital(int $id, array $data, int $updatedByStaffId): array;

    /**
     * Delete a vital record.
     *
     * @param int $id
     * @return array{success: bool, message: string, error?: string}
     */
    public function deleteVital(int $id): array;

    /**
     * Get abnormal vital records.
     *
     * @param int|null $facilityId
     * @param int $limit
     * @return array{success: bool, data: Collection|array, message: string, error?: string}
     */
    public function getAbnormalVitals(?int $facilityId = null, int $limit = 50): array;

    /**
     * Get critical vital records requiring attention.
     *
     * @param int|null $facilityId
     * @param int $limit
     * @return array{success: bool, data: Collection|array, message: string, error?: string}
     */
    public function getCriticalVitals(?int $facilityId = null, int $limit = 50): array;

    /**
     * Get vital signs trend for a patient.
     *
     * @param int $patientId
     * @param string $vitalType
     * @param int $limit
     * @return array{success: bool, data: Collection|array, message: string, error?: string}
     */
    public function getVitalTrend(int $patientId, string $vitalType, int $limit = 10): array;

    /**
     * Get vital statistics for a facility.
     *
     * @param int $facilityId
     * @param string $startDate
     * @param string $endDate
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getVitalStatistics(int $facilityId, string $startDate, string $endDate): array;

    /**
     * Auto-calculate BMI based on height and weight.
     *
     * @param array $data
     * @return array
     */
    public function calculateAndSetBmi(array $data): array;

    /**
     * Generate clinical alerts based on vital signs.
     *
     * @param array $data
     * @return string|null
     */
    public function generateClinicalAlertFromData(array $data): ?string;
}