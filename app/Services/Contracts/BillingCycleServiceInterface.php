<?php

namespace App\Services\Contracts;



interface BillingCycleServiceInterface
{
    /**
     * Get all billing cycles with pagination
     *
     * @param array $filters
     * @return array
     */
    public function getAllBillingCycles(array $filters = []): array;

    /**
     * Get billing cycle by UUID
     *
     * @param string $uuid
     * @return array
     */
    public function getBillingCycleByUuid(string $uuid): array;

    /**
     * Create a new billing cycle
     *
     * @param array $data
     * @return array
     */
    public function createBillingCycle(array $data): array;

    /**
     * Update an existing billing cycle
     *
     * @param string $uuid
     * @param array $data
     * @return array
     */
    public function updateBillingCycle(string $uuid, array $data): array;

    /**
     * Delete a billing cycle
     *
     * @param string $uuid
     * @return array
     */
    public function deleteBillingCycle(string $uuid): array;

    /**
     * Update billing status
     *
     * @param string $uuid
     * @param string $status
     * @param array $additionalData
     * @return array
     */
    public function updateBillingStatus(string $uuid, string $status, array $additionalData = []): array;

    /**
     * Record payment
     *
     * @param string $uuid
     * @param array $paymentData
     * @return array
     */
    public function recordPayment(string $uuid, array $paymentData): array;

    /**
     * Get billing cycles by facility
     *
     * @param int $facilityId
     * @param array $filters
     * @return array
     */
    public function getBillingCyclesByFacility(int $facilityId, array $filters = []): array;

    /**
     * Get billing cycles by patient
     *
     * @param int $patientId
     * @param array $filters
     * @return array
     */
    public function getBillingCyclesByPatient(int $patientId, array $filters = []): array;

    /**
     * Get overdue billing cycles
     *
     * @param array $filters
     * @return array
     */
    public function getOverdueBillingCycles(array $filters = []): array;

    /**
     * Get disputed billing cycles
     *
     * @param array $filters
     * @return array
     */
    public function getDisputedBillingCycles(array $filters = []): array;

    /**
     * Get financial summary
     *
     * @param int $facilityId
     * @param array $dateRange
     * @return array
     */
    public function getFinancialSummary(int $facilityId, array $dateRange = []): array;
}