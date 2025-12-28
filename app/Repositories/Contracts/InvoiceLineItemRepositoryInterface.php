<?php

namespace App\Repositories\Contracts;

use App\Models\InvoiceLineItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface InvoiceLineItemRepositoryInterface
{
    /**
     * Find invoice line item by ID
     */
    public function findById(int $id): ?InvoiceLineItem;

    /**
     * Find invoice line item by UUID
     */
    public function findByUuid(string $uuid): ?InvoiceLineItem;

    /**
     * Get all invoice line items
     */
    public function all(): Collection;

    /**
     * Get paginated invoice line items
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator;

    /**
     * Create a new invoice line item
     */
    public function create(array $data): InvoiceLineItem;

    /**
     * Update an existing invoice line item
     */
    public function update(int $id, array $data): bool;

    /**
     * Delete an invoice line item
     */
    public function delete(int $id): bool;

    /**
     * Find line items by billing cycle
     */
    public function findByBillingCycle(int $billingCycleId): Collection;

    /**
     * Find line items by status
     */
    public function findByStatus(string $status): Collection;

    /**
     * Find line items requiring review
     */
    public function findRequiringReview(): Collection;

    /**
     * Find line items by date range
     */
    public function findByDateRange(\Carbon\Carbon $startDate, \Carbon\Carbon $endDate): Collection;

    /**
     * Find line items by staff who performed service
     */
    public function findByStaffPerformed(int $staffId): Collection;

    /**
     * Update status of a line item
     */
    public function updateStatus(int $id, string $status, ?string $reason = null): bool;

    /**
     * Mark line item as reviewed
     */
    public function markAsReviewed(int $id, int $reviewerId): bool;

    /**
     * Calculate totals for billing cycle
     */
    public function calculateTotalsForBillingCycle(int $billingCycleId): array;

    /**
     * Verify audit trail integrity
     */
    public function verifyAuditTrail(int $id): bool;
}