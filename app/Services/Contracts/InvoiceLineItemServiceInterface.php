<?php

namespace App\Services\Contracts;

use App\Models\InvoiceLineItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface InvoiceLineItemServiceInterface
{
    /**
     * Get all invoice line items with pagination
     */
    public function getAllInvoiceLineItems(int $perPage = 15): array;

    /**
     * Get invoice line item by ID
     */
    public function getInvoiceLineItemById(int $id): array;

    /**
     * Get invoice line item by UUID
     */
    public function getInvoiceLineItemByUuid(string $uuid): array;

    /**
     * Create a new invoice line item
     */
    public function createInvoiceLineItem(array $data): array;

    /**
     * Update an existing invoice line item
     */
    public function updateInvoiceLineItem(int $id, array $data): array;

    /**
     * Delete an invoice line item
     */
    public function deleteInvoiceLineItem(int $id): array;

    /**
     * Get line items by billing cycle
     */
    public function getLineItemsByBillingCycle(int $billingCycleId): array;

    /**
     * Get line items by status
     */
    public function getLineItemsByStatus(string $status): array;

    /**
     * Get line items requiring review
     */
    public function getLineItemsRequiringReview(): array;

    /**
     * Get line items by date range
     */
    public function getLineItemsByDateRange(string $startDate, string $endDate): array;

    /**
     * Update line item status
     */
    public function updateLineItemStatus(int $id, string $status, ?string $reason = null): array;

    /**
     * Mark line item as reviewed
     */
    public function markLineItemAsReviewed(int $id, int $reviewerId): array;

    /**
     * Calculate totals for billing cycle
     */
    public function calculateBillingCycleTotals(int $billingCycleId): array;

    /**
     * Verify audit trail integrity
     */
    public function verifyAuditTrail(int $id): array;

    /**
     * Validate line item for billing
     */
    public function validateLineItemForBilling(int $id): array;

    /**
     * Batch update line items status
     */
    public function batchUpdateStatus(array $ids, string $status, ?string $reason = null): array;
}