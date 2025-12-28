<?php

namespace App\Services\InvoiceLineItem;

use App\Models\InvoiceLineItem;
use App\Repositories\Contracts\InvoiceLineItemRepositoryInterface;
use App\Services\Contracts\InvoiceLineItemServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class InvoiceLineItemService implements InvoiceLineItemServiceInterface
{
    /**
     * Repository instance
     */
    protected InvoiceLineItemRepositoryInterface $repository;

    /**
     * Constructor with dependency injection
     */
    public function __construct(InvoiceLineItemRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Get all invoice line items with pagination
     */
    public function getAllInvoiceLineItems(int $perPage = 15): array
    {
        try {
            $lineItems = $this->repository->paginate($perPage);
            
            return [
                'success' => true,
                'data' => [
                    'line_items' => $lineItems->items(),
                    'pagination' => [
                        'total' => $lineItems->total(),
                        'per_page' => $lineItems->perPage(),
                        'current_page' => $lineItems->currentPage(),
                        'last_page' => $lineItems->lastPage(),
                        'from' => $lineItems->firstItem(),
                        'to' => $lineItems->lastItem(),
                    ]
                ],
                'message' => 'Invoice line items retrieved successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Service error retrieving all invoice line items', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve invoice line items',
                'error' => 'An internal error occurred'
            ];
        }
    }

    /**
     * Get invoice line item by ID
     */
    public function getInvoiceLineItemById(int $id): array
    {
        try {
            $lineItem = $this->repository->findById($id);
            
            if (!$lineItem) {
                return [
                    'success' => false,
                    'message' => 'Invoice line item not found',
                    'error' => 'The requested invoice line item does not exist'
                ];
            }
            
            return [
                'success' => true,
                'data' => [
                    'line_item' => $lineItem
                ],
                'message' => 'Invoice line item retrieved successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Service error retrieving invoice line item by ID', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve invoice line item',
                'error' => 'An internal error occurred'
            ];
        }
    }

    /**
     * Get invoice line item by UUID
     */
    public function getInvoiceLineItemByUuid(string $uuid): array
    {
        try {
            $lineItem = $this->repository->findByUuid($uuid);
            
            if (!$lineItem) {
                return [
                    'success' => false,
                    'message' => 'Invoice line item not found',
                    'error' => 'The requested invoice line item does not exist'
                ];
            }
            
            return [
                'success' => true,
                'data' => [
                    'line_item' => $lineItem
                ],
                'message' => 'Invoice line item retrieved successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Service error retrieving invoice line item by UUID', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve invoice line item',
                'error' => 'An internal error occurred'
            ];
        }
    }

    /**
     * Create a new invoice line item
     */
    public function createInvoiceLineItem(array $data): array
    {
        try {
            // Validate business rules before creation
            $validationResult = $this->validateLineItemData($data, 'create');
            
            if (!$validationResult['success']) {
                return $validationResult;
            }
            
            DB::beginTransaction();
            
            // Calculate derived fields if not provided
            $data = $this->calculateDerivedFields($data);
            
            // Set default status if not provided
            if (!isset($data['line_item_status'])) {
                $data['line_item_status'] = InvoiceLineItem::DEFAULT_STATUS;
            }
            
            // Generate UUID if not provided
            if (!isset($data['line_item_uuid'])) {
                $data['line_item_uuid'] = (string) \Illuminate\Support\Str::uuid();
            }
            
            // Ensure net amount is calculated
            if (!isset($data['net_amount'])) {
                $data['net_amount'] = $this->calculateNetAmount($data);
            }
            
            // Create the line item
            $lineItem = $this->repository->create($data);
            
            DB::commit();
            
            return [
                'success' => true,
                'data' => [
                    'line_item' => $lineItem
                ],
                'message' => 'Invoice line item created successfully'
            ];
        } catch (\RuntimeException $e) {
            DB::rollBack();
            
            Log::error('Business logic error creating invoice line item', [
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to create invoice line item',
                'error' => $e->getMessage()
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Service error creating invoice line item', [
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to create invoice line item',
                'error' => 'An internal error occurred'
            ];
        }
    }

    /**
     * Update an existing invoice line item
     */
    public function updateInvoiceLineItem(int $id, array $data): array
    {
        try {
            // Check if line item exists
            $existingItem = $this->repository->findById($id);
            
            if (!$existingItem) {
                return [
                    'success' => false,
                    'message' => 'Invoice line item not found',
                    'error' => 'The invoice line item to update does not exist'
                ];
            }
            
            // Validate business rules for update
            $validationResult = $this->validateLineItemData($data, 'update', $existingItem);
            
            if (!$validationResult['success']) {
                return $validationResult;
            }
            
            DB::beginTransaction();
            
            // Recalculate derived fields if pricing data changed
            if (isset($data['quantity']) || isset($data['unit_price_at_time']) || 
                isset($data['discount_amount']) || isset($data['adjustment_amount'])) {
                $data = $this->calculateDerivedFields($data, $existingItem);
            }
            
            // Update the line item
            $updated = $this->repository->update($id, $data);
            
            if (!$updated) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Failed to update invoice line item',
                    'error' => 'Update operation failed'
                ];
            }
            
            // Get the updated item
            $lineItem = $this->repository->findById($id);
            
            DB::commit();
            
            return [
                'success' => true,
                'data' => [
                    'line_item' => $lineItem
                ],
                'message' => 'Invoice line item updated successfully'
            ];
        } catch (\RuntimeException $e) {
            DB::rollBack();
            
            Log::error('Business logic error updating invoice line item', [
                'id' => $id,
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to update invoice line item',
                'error' => $e->getMessage()
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Service error updating invoice line item', [
                'id' => $id,
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to update invoice line item',
                'error' => 'An internal error occurred'
            ];
        }
    }

    /**
     * Delete an invoice line item
     */
    public function deleteInvoiceLineItem(int $id): array
    {
        try {
            // Check if line item exists
            $existingItem = $this->repository->findById($id);
            
            if (!$existingItem) {
                return [
                    'success' => false,
                    'message' => 'Invoice line item not found',
                    'error' => 'The invoice line item to delete does not exist'
                ];
            }
            
            // Check if line item can be deleted (business rule)
            if ($existingItem->line_item_status === InvoiceLineItem::STATUS_BILLED || 
                $existingItem->line_item_status === InvoiceLineItem::STATUS_PAID) {
                return [
                    'success' => false,
                    'message' => 'Cannot delete invoice line item',
                    'error' => 'Line items that are already billed or paid cannot be deleted'
                ];
            }
            
            // Delete the line item
            $deleted = $this->repository->delete($id);
            
            if (!$deleted) {
                return [
                    'success' => false,
                    'message' => 'Failed to delete invoice line item',
                    'error' => 'Delete operation failed'
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Invoice line item deleted successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Service error deleting invoice line item', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to delete invoice line item',
                'error' => 'An internal error occurred'
            ];
        }
    }

    /**
     * Get line items by billing cycle
     */
    public function getLineItemsByBillingCycle(int $billingCycleId): array
    {
        try {
            $lineItems = $this->repository->findByBillingCycle($billingCycleId);
            
            return [
                'success' => true,
                'data' => [
                    'line_items' => $lineItems,
                    'count' => $lineItems->count()
                ],
                'message' => 'Line items retrieved successfully for billing cycle'
            ];
        } catch (\Exception $e) {
            Log::error('Service error retrieving line items by billing cycle', [
                'billingCycleId' => $billingCycleId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve line items for billing cycle',
                'error' => 'An internal error occurred'
            ];
        }
    }

    /**
     * Get line items by status
     */
    public function getLineItemsByStatus(string $status): array
    {
        try {
            // Validate status
            if (!in_array($status, [
                InvoiceLineItem::STATUS_PENDING,
                InvoiceLineItem::STATUS_APPROVED,
                InvoiceLineItem::STATUS_BILLED,
                InvoiceLineItem::STATUS_PAID,
                InvoiceLineItem::STATUS_DENIED,
                InvoiceLineItem::STATUS_ADJUSTED,
                InvoiceLineItem::STATUS_WRITTEN_OFF,
            ])) {
                return [
                    'success' => false,
                    'message' => 'Invalid status',
                    'error' => 'The provided status is not valid'
                ];
            }
            
            $lineItems = $this->repository->findByStatus($status);
            
            return [
                'success' => true,
                'data' => [
                    'line_items' => $lineItems,
                    'count' => $lineItems->count(),
                    'status' => $status
                ],
                'message' => 'Line items retrieved successfully for status'
            ];
        } catch (\Exception $e) {
            Log::error('Service error retrieving line items by status', [
                'status' => $status,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve line items by status',
                'error' => 'An internal error occurred'
            ];
        }
    }

    /**
     * Get line items requiring review
     */
    public function getLineItemsRequiringReview(): array
    {
        try {
            $lineItems = $this->repository->findRequiringReview();
            
            return [
                'success' => true,
                'data' => [
                    'line_items' => $lineItems,
                    'count' => $lineItems->count()
                ],
                'message' => 'Line items requiring review retrieved successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Service error retrieving line items requiring review', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve line items requiring review',
                'error' => 'An internal error occurred'
            ];
        }
    }

    /**
     * Get line items by date range
     */
    public function getLineItemsByDateRange(string $startDate, string $endDate): array
    {
        try {
            // Validate dates
            $start = \Carbon\Carbon::parse($startDate);
            $end = \Carbon\Carbon::parse($endDate);
            
            if ($start->gt($end)) {
                return [
                    'success' => false,
                    'message' => 'Invalid date range',
                    'error' => 'Start date must be before end date'
                ];
            }
            
            $lineItems = $this->repository->findByDateRange($start, $end);
            
            return [
                'success' => true,
                'data' => [
                    'line_items' => $lineItems,
                    'count' => $lineItems->count(),
                    'date_range' => [
                        'start_date' => $start->toDateString(),
                        'end_date' => $end->toDateString()
                    ]
                ],
                'message' => 'Line items retrieved successfully for date range'
            ];
        } catch (\Exception $e) {
            Log::error('Service error retrieving line items by date range', [
                'startDate' => $startDate,
                'endDate' => $endDate,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve line items by date range',
                'error' => 'An internal error occurred'
            ];
        }
    }

    /**
     * Update line item status
     */
    public function updateLineItemStatus(int $id, string $status, string $reason = null): array
    {
        try {
            // Validate status
            if (!in_array($status, [
                InvoiceLineItem::STATUS_PENDING,
                InvoiceLineItem::STATUS_APPROVED,
                InvoiceLineItem::STATUS_BILLED,
                InvoiceLineItem::STATUS_PAID,
                InvoiceLineItem::STATUS_DENIED,
                InvoiceLineItem::STATUS_ADJUSTED,
                InvoiceLineItem::STATUS_WRITTEN_OFF,
            ])) {
                return [
                    'success' => false,
                    'message' => 'Invalid status',
                    'error' => 'The provided status is not valid'
                ];
            }
            
            // Check if line item exists
            $existingItem = $this->repository->findById($id);
            
            if (!$existingItem) {
                return [
                    'success' => false,
                    'message' => 'Invoice line item not found',
                    'error' => 'The invoice line item does not exist'
                ];
            }
            
            // Apply business rules for status transitions
            if (!$this->isValidStatusTransition($existingItem->line_item_status, $status)) {
                return [
                    'success' => false,
                    'message' => 'Invalid status transition',
                    'error' => "Cannot transition from {$existingItem->line_item_status} to {$status}"
                ];
            }
            
            // Update status
            $updated = $this->repository->updateStatus($id, $status, $reason);
            
            if (!$updated) {
                return [
                    'success' => false,
                    'message' => 'Failed to update line item status',
                    'error' => 'Status update operation failed'
                ];
            }
            
            // Get updated item
            $lineItem = $this->repository->findById($id);
            
            return [
                'success' => true,
                'data' => [
                    'line_item' => $lineItem
                ],
                'message' => 'Line item status updated successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Service error updating line item status', [
                'id' => $id,
                'status' => $status,
                'reason' => $reason,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to update line item status',
                'error' => 'An internal error occurred'
            ];
        }
    }

    /**
     * Mark line item as reviewed
     */
    public function markLineItemAsReviewed(int $id, int $reviewerId): array
    {
        try {
            // Check if line item exists
            $existingItem = $this->repository->findById($id);
            
            if (!$existingItem) {
                return [
                    'success' => false,
                    'message' => 'Invoice line item not found',
                    'error' => 'The invoice line item does not exist'
                ];
            }
            
            // Check if review is needed
            if (!$existingItem->requires_review) {
                return [
                    'success' => false,
                    'message' => 'Review not required',
                    'error' => 'This line item does not require review'
                ];
            }
            
            // Check if already reviewed
            if ($existingItem->coding_reviewed) {
                return [
                    'success' => false,
                    'message' => 'Already reviewed',
                    'error' => 'This line item has already been reviewed'
                ];
            }
            
            // Mark as reviewed
            $updated = $this->repository->markAsReviewed($id, $reviewerId);
            
            if (!$updated) {
                return [
                    'success' => false,
                    'message' => 'Failed to mark line item as reviewed',
                    'error' => 'Review operation failed'
                ];
            }
            
            // Get updated item
            $lineItem = $this->repository->findById($id);
            
            return [
                'success' => true,
                'data' => [
                    'line_item' => $lineItem
                ],
                'message' => 'Line item marked as reviewed successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Service error marking line item as reviewed', [
                'id' => $id,
                'reviewerId' => $reviewerId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to mark line item as reviewed',
                'error' => 'An internal error occurred'
            ];
        }
    }

    /**
     * Calculate totals for billing cycle
     */
    public function calculateBillingCycleTotals(int $billingCycleId): array
    {
        try {
            $totals = $this->repository->calculateTotalsForBillingCycle($billingCycleId);
            
            return [
                'success' => true,
                'data' => [
                    'billing_cycle_id' => $billingCycleId,
                    'totals' => $totals
                ],
                'message' => 'Billing cycle totals calculated successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Service error calculating billing cycle totals', [
                'billingCycleId' => $billingCycleId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to calculate billing cycle totals',
                'error' => 'An internal error occurred'
            ];
        }
    }

    /**
     * Verify audit trail integrity
     */
    public function verifyAuditTrail(int $id): array
    {
        try {
            // Check if line item exists
            $existingItem = $this->repository->findById($id);
            
            if (!$existingItem) {
                return [
                    'success' => false,
                    'message' => 'Invoice line item not found',
                    'error' => 'The invoice line item does not exist'
                ];
            }
            
            $isValid = $this->repository->verifyAuditTrail($id);
            
            return [
                'success' => true,
                'data' => [
                    'audit_trail_valid' => $isValid,
                    'line_item_id' => $id,
                    'audit_trail_hash' => $existingItem->audit_trail_hash
                ],
                'message' => $isValid ? 'Audit trail is valid' : 'Audit trail validation failed'
            ];
        } catch (\Exception $e) {
            Log::error('Service error verifying audit trail', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to verify audit trail',
                'error' => 'An internal error occurred'
            ];
        }
    }

    /**
     * Validate line item for billing
     */
    public function validateLineItemForBilling(int $id): array
    {
        try {
            // Check if line item exists
            $lineItem = $this->repository->findById($id);
            
            if (!$lineItem) {
                return [
                    'success' => false,
                    'message' => 'Invoice line item not found',
                    'error' => 'The invoice line item does not exist'
                ];
            }
            
            $validationErrors = [];
            
            // Check status
            if ($lineItem->line_item_status !== InvoiceLineItem::STATUS_APPROVED) {
                $validationErrors[] = "Line item must be in 'approved' status, currently: {$lineItem->line_item_status}";
            }
            
            // Check if reviewed if required
            if ($lineItem->requires_review && !$lineItem->coding_reviewed) {
                $validationErrors[] = "Line item requires coding review but has not been reviewed";
            }
            
            // Check required fields
            if (empty($lineItem->procedure_code)) {
                $validationErrors[] = "Procedure code is required";
            }
            
            if (empty($lineItem->diagnosis_codes)) {
                $validationErrors[] = "Diagnosis codes are required";
            }
            
            // Check pricing
            if ($lineItem->net_amount <= 0) {
                $validationErrors[] = "Net amount must be greater than zero";
            }
            
            // Check service date
            if (empty($lineItem->service_performed_at)) {
                $validationErrors[] = "Service performed date is required";
            }
            
            $isValid = empty($validationErrors);
            
            return [
                'success' => true,
                'data' => [
                    'is_valid' => $isValid,
                    'validation_errors' => $validationErrors,
                    'line_item' => $lineItem
                ],
                'message' => $isValid ? 'Line item is valid for billing' : 'Line item validation failed'
            ];
        } catch (\Exception $e) {
            Log::error('Service error validating line item for billing', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to validate line item for billing',
                'error' => 'An internal error occurred'
            ];
        }
    }

    /**
     * Batch update line items status
     */
    public function batchUpdateStatus(array $ids, string $status, string $reason = null): array
    {
        try {
            // Validate status
            if (!in_array($status, [
                InvoiceLineItem::STATUS_PENDING,
                InvoiceLineItem::STATUS_APPROVED,
                InvoiceLineItem::STATUS_BILLED,
                InvoiceLineItem::STATUS_PAID,
                InvoiceLineItem::STATUS_DENIED,
                InvoiceLineItem::STATUS_ADJUSTED,
                InvoiceLineItem::STATUS_WRITTEN_OFF,
            ])) {
                return [
                    'success' => false,
                    'message' => 'Invalid status',
                    'error' => 'The provided status is not valid'
                ];
            }
            
            $results = [
                'successful' => [],
                'failed' => []
            ];
            
            DB::beginTransaction();
            
            foreach ($ids as $id) {
                try {
                    $result = $this->updateLineItemStatus($id, $status, $reason);
                    
                    if ($result['success']) {
                        $results['successful'][] = $id;
                    } else {
                        $results['failed'][] = [
                            'id' => $id,
                            'error' => $result['error'] ?? 'Unknown error'
                        ];
                    }
                } catch (\Exception $e) {
                    $results['failed'][] = [
                        'id' => $id,
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            DB::commit();
            
            return [
                'success' => true,
                'data' => $results,
                'message' => 'Batch status update completed',
                'summary' => [
                    'total' => count($ids),
                    'successful' => count($results['successful']),
                    'failed' => count($results['failed'])
                ]
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Service error in batch status update', [
                'ids' => $ids,
                'status' => $status,
                'reason' => $reason,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to batch update status',
                'error' => 'An internal error occurred'
            ];
        }
    }

    /**
     * Validate line item data for business rules
     */
    private function validateLineItemData(array $data, string $operation, ?InvoiceLineItem $existingItem = null): array
    {
        $errors = [];
        
        // Required fields for creation
        if ($operation === 'create') {
            $requiredFields = [
                'billing_cycle_id',
                'visit_id',
                'service_version_id',
                'service_code',
                'service_description',
                'unit_price_at_time',
                'service_performed_at'
            ];
            
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    $errors[] = "{$field} is required";
                }
            }
        }
        
        // Validate numeric fields
        $numericFields = [
            'quantity' => ['min' => 0.01, 'max' => 999999.99],
            'unit_price_at_time' => ['min' => 0, 'max' => 99999999.99],
            'applied_discount_percentage' => ['min' => 0, 'max' => 100],
            'discount_amount' => ['min' => 0, 'max' => 99999999.99],
            'adjustment_amount' => ['min' => -99999999.99, 'max' => 99999999.99],
        ];
        
        foreach ($numericFields as $field => $constraints) {
            if (isset($data[$field])) {
                $value = (float) $data[$field];
                
                if ($value < $constraints['min']) {
                    $errors[] = "{$field} must be at least {$constraints['min']}";
                }
                
                if ($value > $constraints['max']) {
                    $errors[] = "{$field} cannot exceed {$constraints['max']}";
                }
            }
        }
        
        // Validate status if provided
        if (isset($data['line_item_status'])) {
            $validStatuses = [
                InvoiceLineItem::STATUS_PENDING,
                InvoiceLineItem::STATUS_APPROVED,
                InvoiceLineItem::STATUS_BILLED,
                InvoiceLineItem::STATUS_PAID,
                InvoiceLineItem::STATUS_DENIED,
                InvoiceLineItem::STATUS_ADJUSTED,
                InvoiceLineItem::STATUS_WRITTEN_OFF,
            ];
            
            if (!in_array($data['line_item_status'], $validStatuses)) {
                $errors[] = "Invalid line item status";
            }
        }
        
        // Validate JSON fields
        $jsonFields = [
            'service_version_snapshot',
            'diagnosis_codes',
            'modifier_codes',
            'insurance_specific_codes',
            'metadata'
        ];
        
        foreach ($jsonFields as $field) {
            if (isset($data[$field]) && !$this->isValidJson($data[$field])) {
                $errors[] = "{$field} must be valid JSON";
            }
        }
        
        if (!empty($errors)) {
            return [
                'success' => false,
                'message' => 'Validation failed',
                'error' => 'One or more validation errors occurred',
                'validation_errors' => $errors
            ];
        }
        
        return ['success' => true];
    }

    /**
     * Calculate derived fields for line item
     */
    private function calculateDerivedFields(array $data, ?InvoiceLineItem $existingItem = null): array
    {
        // Calculate line total if quantity and unit price are provided
        if (isset($data['quantity']) && isset($data['unit_price_at_time'])) {
            $quantity = (float) $data['quantity'];
            $unitPrice = (float) $data['unit_price_at_time'];
            $data['line_total_amount'] = round($quantity * $unitPrice, 2);
        } elseif ($existingItem && (isset($data['quantity']) || isset($data['unit_price_at_time']))) {
            $quantity = isset($data['quantity']) ? (float) $data['quantity'] : $existingItem->quantity;
            $unitPrice = isset($data['unit_price_at_time']) ? (float) $data['unit_price_at_time'] : $existingItem->unit_price_at_time;
            $data['line_total_amount'] = round($quantity * $unitPrice, 2);
        }
        
        // Calculate discount amount if percentage is provided but amount is not
        if (isset($data['applied_discount_percentage']) && !isset($data['discount_amount']) && isset($data['line_total_amount'])) {
            $discountPercentage = (float) $data['applied_discount_percentage'];
            $lineTotal = (float) $data['line_total_amount'];
            $data['discount_amount'] = round($lineTotal * ($discountPercentage / 100), 2);
        }
        
        // Calculate net amount
        if (isset($data['line_total_amount']) || isset($data['discount_amount']) || isset($data['adjustment_amount'])) {
            $lineTotal = isset($data['line_total_amount']) ? (float) $data['line_total_amount'] : ($existingItem ? $existingItem->line_total_amount : 0);
            $discount = isset($data['discount_amount']) ? (float) $data['discount_amount'] : ($existingItem ? $existingItem->discount_amount : 0);
            $adjustment = isset($data['adjustment_amount']) ? (float) $data['adjustment_amount'] : ($existingItem ? $existingItem->adjustment_amount : 0);
            
            $data['net_amount'] = round(max(0, $lineTotal - $discount - $adjustment), 2);
        }
        
        return $data;
    }

    /**
     * Calculate net amount from data
     */
    private function calculateNetAmount(array $data): float
    {
        $lineTotal = $data['line_total_amount'] ?? 0;
        $discount = $data['discount_amount'] ?? 0;
        $adjustment = $data['adjustment_amount'] ?? 0;
        
        return round(max(0, $lineTotal - $discount - $adjustment), 2);
    }

    /**
     * Check if status transition is valid
     */
    private function isValidStatusTransition(string $fromStatus, string $toStatus): bool
    {
        $allowedTransitions = [
            InvoiceLineItem::STATUS_PENDING => [
                InvoiceLineItem::STATUS_APPROVED,
                InvoiceLineItem::STATUS_DENIED,
            ],
            InvoiceLineItem::STATUS_APPROVED => [
                InvoiceLineItem::STATUS_BILLED,
                InvoiceLineItem::STATUS_ADJUSTED,
                InvoiceLineItem::STATUS_WRITTEN_OFF,
            ],
            InvoiceLineItem::STATUS_BILLED => [
                InvoiceLineItem::STATUS_PAID,
                InvoiceLineItem::STATUS_DENIED,
                InvoiceLineItem::STATUS_ADJUSTED,
            ],
            InvoiceLineItem::STATUS_PAID => [
                InvoiceLineItem::STATUS_ADJUSTED,
            ],
            InvoiceLineItem::STATUS_DENIED => [
                InvoiceLineItem::STATUS_ADJUSTED,
            ],
            InvoiceLineItem::STATUS_ADJUSTED => [
                InvoiceLineItem::STATUS_BILLED,
                InvoiceLineItem::STATUS_PAID,
            ],
            InvoiceLineItem::STATUS_WRITTEN_OFF => [
                // No transitions from written off
            ],
        ];
        
        return in_array($toStatus, $allowedTransitions[$fromStatus] ?? []);
    }

    /**
     * Check if string is valid JSON
     */
    private function isValidJson($string): bool
    {
        if (is_array($string)) {
            return true;
        }
        
        if (!is_string($string)) {
            return false;
        }
        
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
}