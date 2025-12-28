<?php

namespace App\Services\Contracts;

use App\Models\Prescription;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface PrescriptionServiceInterface
{
    /**
     * Get all prescriptions with filters
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllPrescriptions(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Get prescription by UUID
     *
     * @param string $uuid
     * @return Prescription
     */
    public function getPrescriptionByUuid(string $uuid): Prescription;

    /**
     * Get patient prescriptions
     *
     * @param int $patientId
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPatientPrescriptions(int $patientId, array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Create new prescription with validation
     *
     * @param array $data
     * @return Prescription
     */
    public function createPrescription(array $data): Prescription;

    /**
     * Update prescription
     *
     * @param string $uuid
     * @param array $data
     * @return Prescription
     */
    public function updatePrescription(string $uuid, array $data): Prescription;

    /**
     * Delete prescription (soft delete)
     *
     * @param string $uuid
     * @return bool
     */
    public function deletePrescription(string $uuid): bool;

    /**
     * Process prescription refill
     *
     * @param string $uuid
     * @param array $refillData
     * @return Prescription
     */
    public function processRefill(string $uuid, array $refillData): Prescription;

    /**
     * Update dispense status
     *
     * @param string $uuid
     * @param string $status
     * @param array $metadata
     * @return Prescription
     */
    public function updateDispenseStatus(string $uuid, string $status, array $metadata = []): Prescription;

    /**
     * Discontinue prescription
     *
     * @param string $uuid
     * @param string $reason
     * @param int|null $discontinuedById
     * @return Prescription
     */
    public function discontinuePrescription(string $uuid, string $reason, ?int $discontinuedById = null): Prescription;

    /**
     * Validate drug interactions and allergies
     *
     * @param int $patientId
     * @param string $medicationName
     * @param array $existingConditions
     * @return array
     */
    public function validateDrugSafety(int $patientId, string $medicationName, array $existingConditions = []): array;

    /**
     * Check prescription refill eligibility
     *
     * @param string $uuid
     * @return array
     */
    public function checkRefillEligibility(string $uuid): array;

    /**
     * Get prescriptions needing transmission
     *
     * @param int $facilityId
     * @param int $limit
     * @return Collection
     */
    public function getPrescriptionsNeedingTransmission(int $facilityId, int $limit = 50): Collection;

    /**
     * Transmit prescription to pharmacy
     *
     * @param string $uuid
     * @param array $transmissionData
     * @return Prescription
     */
    public function transmitPrescription(string $uuid, array $transmissionData = []): Prescription;

    /**
     * Get prescription statistics
     *
     * @param int $facilityId
     * @param array $dateRange
     * @return array
     */
    public function getPrescriptionStatistics(int $facilityId, array $dateRange = []): array;
}