<?php

namespace App\Repositories\Contracts;

use App\Models\BillingCycle;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface BillingCycleRepositoryInterface
{
    /**
     * Find billing cycle by UUID
     *
     * @param string $uuid
     * @return BillingCycle|null
     */
    public function findByUuid(string $uuid): ?BillingCycle;

    /**
     * Find billing cycle by ID
     *
     * @param int $id
     * @return BillingCycle|null
     */
    public function findById(int $id): ?BillingCycle;

    /**
     * Get all billing cycles with pagination
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllPaginated(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Get billing cycles by facility
     *
     * @param int $facilityId
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getByFacility(int $facilityId, array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Get billing cycles by patient
     *
     * @param int $patientId
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getByPatient(int $patientId, array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Get billing cycles by visit
     *
     * @param int $visitId
     * @param array $filters
     * @return Collection
     */
    public function getByVisit(int $visitId, array $filters = []): Collection;

    /**
     * Get overdue billing cycles
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getOverdue(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Get disputed billing cycles
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getDisputed(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Create a new billing cycle
     *
     * @param array $data
     * @return BillingCycle
     */
    public function create(array $data): BillingCycle;

    /**
     * Update an existing billing cycle
     *
     * @param BillingCycle $billingCycle
     * @param array $data
     * @return BillingCycle
     */
    public function update(BillingCycle $billingCycle, array $data): BillingCycle;

    /**
     * Delete a billing cycle
     *
     * @param BillingCycle $billingCycle
     * @return bool
     */
    public function delete(BillingCycle $billingCycle): bool;

    /**
     * Restore a soft deleted billing cycle
     *
     * @param BillingCycle $billingCycle
     * @return bool
     */
    public function restore(BillingCycle $billingCycle): bool;

    /**
     * Force delete a billing cycle
     *
     * @param BillingCycle $billingCycle
     * @return bool
     */
    public function forceDelete(BillingCycle $billingCycle): bool;

    /**
     * Update billing status
     *
     * @param BillingCycle $billingCycle
     * @param string $status
     * @param array $additionalData
     * @return BillingCycle
     */
    public function updateStatus(BillingCycle $billingCycle, string $status, array $additionalData = []): BillingCycle;

    /**
     * Record payment received
     *
     * @param BillingCycle $billingCycle
     * @param array $paymentData
     * @return BillingCycle
     */
    public function recordPayment(BillingCycle $billingCycle, array $paymentData): BillingCycle;

    /**
     * Get financial summary by facility
     *
     * @param int $facilityId
     * @param array $dateRange
     * @return array
     */
    public function getFinancialSummary(int $facilityId, array $dateRange = []): array;
}