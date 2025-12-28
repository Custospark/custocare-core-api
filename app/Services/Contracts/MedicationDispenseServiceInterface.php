<?php

namespace App\Services\Contracts;

use App\Models\MedicationDispense;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface MedicationDispenseServiceInterface
{
    /**
     * Get all medication dispenses with pagination.
     *
     * @param array $filters
     * @param int $perPage
     * @return array
     */
    public function getAllDispenses(array $filters = [], int $perPage = 20): array;

    /**
     * Get medication dispense by UUID.
     *
     * @param string $uuid
     * @return array
     */
    public function getDispenseByUuid(string $uuid): array;

    /**
     * Create a new medication dispense.
     *
     * @param array $data
     * @return array
     */
    public function createDispense(array $data): array;

    /**
     * Update an existing medication dispense.
     *
     * @param string $uuid
     * @param array $data
     * @return array
     */
    public function updateDispense(string $uuid, array $data): array;

    /**
     * Delete a medication dispense.
     *
     * @param string $uuid
     * @return array
     */
    public function deleteDispense(string $uuid): array;

    /**
     * Verify a dispense (pharmacist check).
     *
     * @param string $uuid
     * @param int $pharmacistId
     * @param array $data
     * @return array
     */
    public function verifyDispense(string $uuid, int $pharmacistId, array $data): array;

    /**
     * Mark dispense as picked up.
     *
     * @param string $uuid
     * @param array $pickupData
     * @return array
     */
    public function markAsPickedUp(string $uuid, array $pickupData): array;

    /**
     * Update dispense status.
     *
     * @param string $uuid
     * @param string $status
     * @param string|null $reason
     * @return array
     */
    public function updateDispenseStatus(string $uuid, string $status, ?string $reason = null): array;

    /**
     * Get dispenses by prescription.
     *
     * @param int $prescriptionId
     * @return array
     */
    public function getDispensesByPrescription(int $prescriptionId): array;

    /**
     * Get dispenses by patient.
     *
     * @param int $patientId
     * @param array $filters
     * @param int $perPage
     * @return array
     */
    public function getDispensesByPatient(int $patientId, array $filters = [], int $perPage = 20): array;

    /**
     * Get facility statistics.
     *
     * @param int $facilityId
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getFacilityStatistics(int $facilityId, string $startDate, string $endDate): array;

    /**
     * Perform safety checks for dispense.
     *
     * @param array $prescriptionData
     * @param int $patientId
     * @param int $facilityId
     * @return array
     */
    public function performSafetyChecks(array $prescriptionData, int $patientId, int $facilityId): array;

    /**
     * Validate dispense data before creation.
     *
     * @param array $data
     * @return array
     */
    public function validateDispenseData(array $data): array;
}