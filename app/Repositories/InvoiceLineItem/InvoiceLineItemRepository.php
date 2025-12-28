<?php

namespace App\Repositories\InvoiceLineItem;

use App\Models\InvoiceLineItem;
use App\Repositories\Contracts\InvoiceLineItemRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InvoiceLineItemRepository implements InvoiceLineItemRepositoryInterface
{
    /**
     * Model instance
     */
    protected InvoiceLineItem $model;

    /**
     * Constructor with dependency injection
     */
    public function __construct(InvoiceLineItem $model)
    {
        $this->model = $model;
    }

    /**
     * Find invoice line item by ID
     */
    public function findById(int $id): ?InvoiceLineItem
    {
        try {
            return $this->model->with([
                'billingCycle',
                'serviceVersion',
                'staffPerformed',
                'reviewedBy',
                'createdBy'
            ])->find($id);
        } catch (\Exception $e) {
            Log::error('Error finding invoice line item by ID', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Find invoice line item by UUID
     */
    public function findByUuid(string $uuid): ?InvoiceLineItem
    {
        try {
            return $this->model->with([
                'billingCycle',
                'serviceVersion',
                'staffPerformed',
                'reviewedBy',
                'createdBy'
            ])->where('line_item_uuid', $uuid)->first();
        } catch (\Exception $e) {
            Log::error('Error finding invoice line item by UUID', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Get all invoice line items
     */
    public function all(): Collection
    {
        try {
            return $this->model->with([
                'billingCycle',
                'serviceVersion',
                'staffPerformed'
            ])->latest()->get();
        } catch (\Exception $e) {
            Log::error('Error retrieving all invoice line items', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return new Collection();
        }
    }

    /**
     * Get paginated invoice line items
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        try {
            return $this->model->with([
                'billingCycle',
                'serviceVersion',
                'staffPerformed'
            ])->latest()->paginate($perPage);
        } catch (\Exception $e) {
            Log::error('Error paginating invoice line items', [
                'perPage' => $perPage,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }
    }

    /**
     * Create a new invoice line item
     */
    public function create(array $data): InvoiceLineItem
    {
        try {
            DB::beginTransaction();
            
            $lineItem = $this->model->create($data);
            
            // Generate audit trail hash after creation
            $lineItem->audit_trail_hash = $lineItem->generateAuditTrailHash();
            $lineItem->save();
            
            DB::commit();
            
            // Reload with relationships
            return $lineItem->load([
                'billingCycle',
                'serviceVersion',
                'staffPerformed'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error creating invoice line item', [
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw new \RuntimeException('Failed to create invoice line item: ' . $e->getMessage());
        }
    }

    /**
     * Update an existing invoice line item
     */
    public function update(int $id, array $data): bool
    {
        try {
            DB::beginTransaction();
            
            $lineItem = $this->findById($id);
            
            if (!$lineItem) {
                DB::rollBack();
                return false;
            }
            
            $result = $lineItem->update($data);
            
            if ($result) {
                // Regenerate audit trail hash after update
                $lineItem->audit_trail_hash = $lineItem->generateAuditTrailHash();
                $lineItem->save();
            }
            
            DB::commit();
            return $result;
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error updating invoice line item', [
                'id' => $id,
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw new \RuntimeException('Failed to update invoice line item: ' . $e->getMessage());
        }
    }

    /**
     * Delete an invoice line item
     */
    public function delete(int $id): bool
    {
        try {
            $lineItem = $this->findById($id);
            
            if (!$lineItem) {
                return false;
            }
            
            return $lineItem->delete();
        } catch (\Exception $e) {
            Log::error('Error deleting invoice line item', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return false;
        }
    }

    /**
     * Find line items by billing cycle
     */
    public function findByBillingCycle(int $billingCycleId): Collection
    {
        try {
            return $this->model->with([
                'serviceVersion',
                'staffPerformed'
            ])->where('billing_cycle_id', $billingCycleId)->get();
        } catch (\Exception $e) {
            Log::error('Error finding line items by billing cycle', [
                'billingCycleId' => $billingCycleId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return new Collection();
        }
    }

    /**
     * Find line items by status
     */
    public function findByStatus(string $status): Collection
    {
        try {
            return $this->model->with([
                'billingCycle',
                'serviceVersion'
            ])->where('line_item_status', $status)->get();
        } catch (\Exception $e) {
            Log::error('Error finding line items by status', [
                'status' => $status,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return new Collection();
        }
    }

    /**
     * Find line items requiring review
     */
    public function findRequiringReview(): Collection
    {
        try {
            return $this->model->with([
                'billingCycle',
                'serviceVersion',
                'staffPerformed'
            ])->where('requires_review', true)
              ->where('coding_reviewed', false)
              ->get();
        } catch (\Exception $e) {
            Log::error('Error finding line items requiring review', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return new Collection();
        }
    }

    /**
     * Find line items by date range
     */
    public function findByDateRange(\Carbon\Carbon $startDate, \Carbon\Carbon $endDate): Collection
    {
        try {
            return $this->model->with([
                'billingCycle',
                'serviceVersion',
                'staffPerformed'
            ])->whereBetween('service_performed_at', [$startDate, $endDate])->get();
        } catch (\Exception $e) {
            Log::error('Error finding line items by date range', [
                'startDate' => $startDate,
                'endDate' => $endDate,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return new Collection();
        }
    }

    /**
     * Find line items by staff who performed service
     */
    public function findByStaffPerformed(int $staffId): Collection
    {
        try {
            return $this->model->with([
                'billingCycle',
                'serviceVersion'
            ])->where('staff_performed_id', $staffId)->get();
        } catch (\Exception $e) {
            Log::error('Error finding line items by staff performed', [
                'staffId' => $staffId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return new Collection();
        }
    }

    /**
     * Update status of a line item
     */
    public function updateStatus(int $id, string $status, ?string $reason = null): bool
    {
        try {
            DB::beginTransaction();
            
            $lineItem = $this->findById($id);
            
            if (!$lineItem) {
                DB::rollBack();
                return false;
            }
            
            $result = $lineItem->updateStatus($status, $reason);
            
            DB::commit();
            return $result;
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error updating line item status', [
                'id' => $id,
                'status' => $status,
                'reason' => $reason,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return false;
        }
    }

    /**
     * Mark line item as reviewed
     */
    public function markAsReviewed(int $id, int $reviewerId): bool
    {
        try {
            $lineItem = $this->findById($id);
            
            if (!$lineItem) {
                return false;
            }
            
            return $lineItem->markAsReviewed($reviewerId);
        } catch (\Exception $e) {
            Log::error('Error marking line item as reviewed', [
                'id' => $id,
                'reviewerId' => $reviewerId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return false;
        }
    }

    /**
     * Calculate totals for billing cycle
     */
    public function calculateTotalsForBillingCycle(int $billingCycleId): array
    {
        try {
            $totals = $this->model->where('billing_cycle_id', $billingCycleId)
                ->select([
                    DB::raw('COUNT(*) as total_items'),
                    DB::raw('SUM(quantity) as total_quantity'),
                    DB::raw('SUM(line_total_amount) as total_line_amount'),
                    DB::raw('SUM(discount_amount) as total_discount'),
                    DB::raw('SUM(adjustment_amount) as total_adjustment'),
                    DB::raw('SUM(net_amount) as total_net_amount'),
                    DB::raw('AVG(applied_discount_percentage) as avg_discount_percentage')
                ])
                ->first();
            
            return [
                'total_items' => (int) ($totals->total_items ?? 0),
                'total_quantity' => (float) ($totals->total_quantity ?? 0),
                'total_line_amount' => (float) ($totals->total_line_amount ?? 0),
                'total_discount' => (float) ($totals->total_discount ?? 0),
                'total_adjustment' => (float) ($totals->total_adjustment ?? 0),
                'total_net_amount' => (float) ($totals->total_net_amount ?? 0),
                'avg_discount_percentage' => (float) ($totals->avg_discount_percentage ?? 0),
            ];
        } catch (\Exception $e) {
            Log::error('Error calculating totals for billing cycle', [
                'billingCycleId' => $billingCycleId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'total_items' => 0,
                'total_quantity' => 0,
                'total_line_amount' => 0,
                'total_discount' => 0,
                'total_adjustment' => 0,
                'total_net_amount' => 0,
                'avg_discount_percentage' => 0,
            ];
        }
    }

    /**
     * Verify audit trail integrity
     */
    public function verifyAuditTrail(int $id): bool
    {
        try {
            $lineItem = $this->findById($id);
            
            if (!$lineItem) {
                return false;
            }
            
            return $lineItem->isAuditTrailValid();
        } catch (\Exception $e) {
            Log::error('Error verifying audit trail', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return false;
        }
    }
}