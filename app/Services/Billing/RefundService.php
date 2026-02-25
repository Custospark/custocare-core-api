<?php

namespace App\Services\Billing;

use App\Models\BillingCycle;
use App\Models\FinancialAdjustment;
use App\Models\InvoiceLineItem;
use App\Models\InventoryItem;
use App\Models\ServiceCatalog;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * RefundService
 *
 * PUBLIC API — exactly two entry points consumed by the controller:
 *
 *   refundTransaction()  – Processes a refund (full OR partial).
 *                          Determines the type automatically from the payload:
 *                            • line_items present → partial refund
 *                            • no line_items      → full refund
 *
 *   voidTransaction()    – Voids (cancels) a billing cycle without a monetary
 *                          refund. Soft-deletes the cycle and its line items,
 *                          and always restores inventory.
 *
 * PRIVATE MODULE METHODS (supporting helpers):
 *
 *   determineRefundType()        – Inspects payload to resolve full vs partial.
 *   processFullRefund()          – Full-refund pipeline.
 *   processPartialRefund()       – Partial-refund pipeline.
 *   validateRefundEligibility()  – Guards for refundable state.
 *   validateVoidEligibility()    – Guards for voidable state.
 *   createFinancialAdjustment()  – Persists the FinancialAdjustment record.
 *   createBillingSnapshot()      – Captures exact billing_cycles row for audit.
 *   restoreInventory()           – Returns stock quantities on refund/void.
 *   updateVisitAfterRefund()     – Recalculates visit payment state post-refund.
 *   updateVisitAfterVoid()       – Resets visit payment state post-void.
 */
class RefundService
{
    // =========================================================================
    // PUBLIC ENTRY POINTS
    // =========================================================================

    /**
     * Refund Entry Point — auto-detects full vs. partial refund.
     *
     * The caller does NOT need to know the refund type; it is resolved here
     * by inspecting whether `line_items` is present in $refundData.
     *
     * @param int   $billingCycleId Target billing cycle.
     * @param array $refundData     Validated payload from the controller.
     *                              Keys: reason, reason_notes?, line_items?,
     *                              refund_methods, restore_inventory?
     * @param int   $facilityId     Scoping facility.
     * @param int   $staffId        Performing staff member.
     * @return array{success: bool, message: string, data?: array, errors?: array}
     */
    public function refundTransaction(
        int $billingCycleId,
        array $refundData,
        int $facilityId,
        int $staffId
    ): array {
        // Resolve the refund type from the payload
        $refundType = $this->determineRefundType($refundData);

        Log::info('Refund type determined', [
            'billing_cycle_id' => $billingCycleId,
            'refund_type'      => $refundType,
            'has_line_items'   => !empty($refundData['line_items']),
        ]);

        // Route to the appropriate private pipeline
        return $refundType === 'partial_refund'
            ? $this->processPartialRefund($billingCycleId, $refundData, $facilityId, $staffId)
            : $this->processFullRefund($billingCycleId, $refundData, $facilityId, $staffId);
    }

    /**
     * Void Entry Point — cancels a billing cycle without issuing a refund.
     *
     * Eligibility rules (enforced inside):
     *   • Cycle must not already be soft-deleted (voided).
     *   • Cycle must be in 'draft' status OR created within the last 24 hours.
     *   • Insurance claim must not yet have been submitted.
     *
     * @param int   $billingCycleId Target billing cycle.
     * @param array $voidData       Validated payload: reason, reason_notes?,
     *                              restore_inventory?
     * @param int   $facilityId     Scoping facility.
     * @param int   $staffId        Performing staff member.
     * @return array{success: bool, message: string, data?: array, errors?: array}
     */
    public function voidTransaction(
        int $billingCycleId,
        array $voidData,
        int $facilityId,
        int $staffId
    ): array {
        try {
            return DB::transaction(function () use ($billingCycleId, $voidData, $facilityId, $staffId) {


                // 1. Fetch and lock the billing cycle scoped to the facility
                $billingCycle = BillingCycle::where('id', $billingCycleId)
                    ->where('facility_id', $facilityId)
                    ->with(['lineItems', 'visit'])
                    ->lockForUpdate()
                    ->first();

                if (!$billingCycle) {
                    return $this->notFound('Billing cycle not found.', 'billing_cycle');
                }

                // 2. Validate void eligibility
                $eligibility = $this->validateVoidEligibility($billingCycle);
                if (!$eligibility['success']) {
                    return $eligibility;
                }

                // 3. Build the full billing snapshot before any mutations
                $snapshot = $this->createBillingSnapshot($billingCycle);

                // 4. Persist the FinancialAdjustment (void carries zero refund amounts)
                $adjustment = $this->createFinancialAdjustment([
                    'facility_id'               => $facilityId,
                    'billing_cycle_id'          => $billingCycle->id,
                    'visit_id'                  => $billingCycle->visit_id,
                    'patient_id'                => $billingCycle->patient_id,
                    'adjustment_type'           => 'void_transaction',
                    'adjustment_reason'         => $voidData['reason'],
                    'reason_notes'              => $voidData['reason_notes'] ?? null,
                    'original_amount'           => $billingCycle->net_amount,
                    'adjustment_amount'         => 0,   // Void = cancellation, not a cash movement
                    'remaining_amount'          => 0,
                    'patient_refund_amount'     => 0,
                    'insurance_refund_amount'   => 0,
                    // Default restore_inventory to TRUE for voids
                    'restore_inventory'         => $voidData['restore_inventory'] ?? true,
                    'requested_by_staff_id'     => $staffId,
                    'original_billing_snapshot' => $snapshot,
                ]);

                // 5. Stamp void metadata onto the billing cycle before soft-deleting
                $billingCycle->billing_status      = 'written_off';
                $billingCycle->updated_by_staff_id = $staffId;
                $billingCycle->metadata            = $this->mergeMetadata(
                    $billingCycle->metadata,
                    [
                        'voided' => [
                            'voided_at'          => now()->toIso8601String(),
                            'voided_by_staff_id' => $staffId,
                            'adjustment_id'      => $adjustment->id,
                            'reference_number'   => $adjustment->reference_number,
                            'reason'             => $voidData['reason'],
                        ],
                    ]
                );
                $billingCycle->save();
                $billingCycle->delete(); // Soft-delete — row still exists for audit

                // 6. Soft-delete and mark all child line items as written_off
                InvoiceLineItem::where('billing_cycle_id', $billingCycle->id)
                    ->update([
                        'line_item_status' => 'written_off',
                        'deleted_at'       => now(),
                    ]);

                // 7. Restore inventory (always restores on void)
                $restoredInventory = $this->restoreInventory(
                    $billingCycle->lineItems,
                    $staffId,
                    $adjustment->reference_number
                );
                $adjustment->inventory_restored = $restoredInventory;
                $adjustment->save();

                // 8. Reset visit billing state
                $this->updateVisitAfterVoid($billingCycle->visit, $billingCycle, $adjustment, $staffId);

                // 9. Mark the adjustment as completed
                $this->completeAdjustment($adjustment, $staffId);

                Log::info('Transaction voided successfully', [
                    'billing_cycle_id' => $billingCycle->id,
                    'adjustment_id'    => $adjustment->id,
                    'reference_number' => $adjustment->reference_number,
                    'staff_id'         => $staffId,
                ]);

                return [
                    'success' => true,
                    'message' => 'Transaction voided successfully.',
                    'data'    => [
                        'adjustment_id'     => $adjustment->id,
                        'reference_number'  => $adjustment->reference_number,
                        'voided_amount'     => $billingCycle->net_amount,
                        'inventory_restored'=> !empty($restoredInventory),
                        'completed_at'      => now(),
                    ],
                ];
            });

        } catch (Throwable $e) {
            Log::error('Transaction void failed — all changes rolled back', [
                'billing_cycle_id' => $billingCycleId,
                'error'            => $e->getMessage(),
                'trace'            => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to void transaction. All changes have been rolled back.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    // =========================================================================
    // PRIVATE PIPELINE METHODS
    // =========================================================================

    /**
     * Determine whether the payload describes a full or partial refund.
     *
     * Rule:
     *   line_items present and non-empty → 'partial_refund'
     *   otherwise                        → 'full_refund'
     *
     * @param array $refundData
     * @return string 'full_refund' | 'partial_refund'
     */
    private function determineRefundType(array $refundData): string
    {
        $refundType= isset($refundData['line_items']) && !empty($refundData['line_items'])
            ? 'partial_refund'
            : 'full_refund';
        Log::info($refundType);
        Log::info($refundData);
        return$refundType;
    }

    // -------------------------------------------------------------------------
    // Full-refund pipeline
    // -------------------------------------------------------------------------

    /**
     * Issue a full refund for an entire billing cycle.
     *
     * Reverses the total amount of patient + insurance payments received,
     * marks all line items as written_off, and optionally restores inventory.
     *
     * @param int   $billingCycleId
     * @param array $refundData     reason, reason_notes?, refund_methods, restore_inventory?
     * @param int   $facilityId
     * @param int   $staffId
     * @return array
     */
    private function processFullRefund(
        int $billingCycleId,
        array $refundData,
        int $facilityId,
        int $staffId
    ): array {
        try {
            return DB::transaction(function () use ($billingCycleId, $refundData, $facilityId, $staffId) {

                // 1. Fetch and lock
                $billingCycle = BillingCycle::where('id', $billingCycleId)
                    ->where('facility_id', $facilityId)
                    ->with(['lineItems', 'visit'])
                    ->lockForUpdate()
                    ->first();

                if (!$billingCycle) {
                    return $this->notFound('Billing cycle not found.', 'billing_cycle');
                }
                Log::alert($billingCycle);
                // 2. Eligibility check
                $eligibility = $this->validateRefundEligibility($billingCycle, 'full_refund');
                if (!$eligibility['success']) {
                    return $eligibility;
                }

                // 3. Derive the total previously paid (patient + insurance combined)
                $totalPaid = (float) $billingCycle->patient_payment_received
                           + (float) $billingCycle->insurance_payment_received;

                // 4. Persist the adjustment record
                $adjustment = $this->createFinancialAdjustment([
                    'facility_id'               => $facilityId,
                    'billing_cycle_id'          => $billingCycle->id,
                    'visit_id'                  => $billingCycle->visit_id,
                    'patient_id'                => $billingCycle->patient_id,
                    'adjustment_type'           => 'full_refund',
                    'adjustment_reason'         => $refundData['reason'],
                    'reason_notes'              => $refundData['reason_notes'] ?? null,
                    'original_amount'           => $billingCycle->net_amount,
                    'adjustment_amount'         => $totalPaid,
                    'remaining_amount'          => 0,
                    'patient_refund_amount'     => $billingCycle->patient_payment_received,
                    'insurance_refund_amount'   => $billingCycle->insurance_payment_received,
                    'refund_methods'            => $refundData['refund_methods'] ?? [],
                    'restore_inventory'         => $refundData['restore_inventory'] ?? false,
                    'requested_by_staff_id'     => $staffId,
                    'original_billing_snapshot' => $this->createBillingSnapshot($billingCycle),
                ]);

                // 5. Mutate the billing cycle
                $billingCycle->billing_status      = 'fully_refunded';
                $billingCycle->bad_debt_adjustment = $totalPaid;
                $billingCycle->updated_by_staff_id = $staffId;
                $billingCycle->metadata            = $this->mergeMetadata(
                    $billingCycle->metadata,
                    [
                        'refunded' => [
                            'refunded_at'          => now()->toIso8601String(),
                            'refunded_by_staff_id' => $staffId,
                            'adjustment_id'        => $adjustment->id,
                            'reference_number'     => $adjustment->reference_number,
                            'refund_amount'        => $totalPaid,
                            'refund_type'          => 'full_refund',
                        ],
                    ]
                );
                $billingCycle->save();

                // 6. Mark all line items as written_off
                InvoiceLineItem::where('billing_cycle_id', $billingCycle->id)
                    ->update(['line_item_status' => 'written_off', 'updated_at' => now()]);

                // 7. Conditionally restore inventory
                if ($refundData['restore_inventory'] ?? true) { //Make inventory restoration true by default for now.
                    $restored = $this->restoreInventory(
                        $billingCycle->lineItems,
                        $staffId,
                        $adjustment->reference_number
                    );
                    $adjustment->inventory_restored = $restored;
                    $adjustment->save();
                }

                // 8. Recalculate visit payment state
                $this->updateVisitAfterRefund($billingCycle->visit, $billingCycle, $adjustment, $staffId);

                // 9. Seal the adjustment
                $this->completeAdjustment($adjustment, $staffId);

                Log::info('Full refund processed successfully', [
                    'billing_cycle_id' => $billingCycle->id,
                    'adjustment_id'    => $adjustment->id,
                    'refund_amount'    => $totalPaid,
                    'staff_id'         => $staffId,
                ]);

                return [
                    'success' => true,
                    'message' => 'Full refund processed successfully.',
                    'data'    => [
                        'refund_type'        => 'full_refund',
                        'adjustment_id'      => $adjustment->id,
                        'reference_number'   => $adjustment->reference_number,
                        'refund_amount'      => $totalPaid,
                        'patient_refund'     => $adjustment->patient_refund_amount,
                        'insurance_refund'   => $adjustment->insurance_refund_amount,
                        'inventory_restored' => $adjustment->restore_inventory,
                        'completed_at'       => now()->toIso8601String(),
                    ],
                ];
            });

        } catch (Throwable $e) {
            Log::error('Full refund processing failed — all changes rolled back', [
                'billing_cycle_id' => $billingCycleId,
                'error'            => $e->getMessage(),
                'trace'            => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to process full refund. All changes have been rolled back.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    // -------------------------------------------------------------------------
    // Partial-refund pipeline
    // -------------------------------------------------------------------------

    /**
     * Issue a partial refund against specific line items.
     *
     * Each line item may carry an explicit refund_amount; if omitted the full
     * net_amount of that line item is used.  The total refund is split between
     * patient and insurance proportionally to the original payment split.
     *
     * @param int   $billingCycleId
     * @param array $refundData     reason, reason_notes?, line_items[], refund_methods, restore_inventory?
     * @param int   $facilityId
     * @param int   $staffId
     * @return array
     */private function processPartialRefund(
    int $billingCycleId,
    array $refundData,
    int $facilityId,
    int $staffId
): array {
    try {
        return DB::transaction(function () use ($billingCycleId, $refundData, $facilityId, $staffId) {

            // 1. Fetch and lock billing cycle
            $billingCycle = BillingCycle::where('id', $billingCycleId)
                ->where('facility_id', $facilityId)
                ->with(['visit'])
                ->lockForUpdate()
                ->first();

            if (!$billingCycle) {
                return $this->notFound('Billing cycle not found.', 'billing_cycle');
            }

            // 2. Validate refund eligibility
            $eligibility = $this->validateRefundEligibility($billingCycle, 'partial_refund');
            if (!$eligibility['success']) {
                return $eligibility;
            }

            // 3. Extract requested reference IDs (these are either service_catalog_id OR inventory_item_id)
            if (!isset($refundData['line_items']) || !is_array($refundData['line_items'])) {
                return [
                    'success' => false,
                    'message' => 'Line items are required for partial refund.',
                    'errors' => ['line_items' => ['Line items array is required']],
                ];
            }

            $requestedRefunds = $refundData['line_items'];
            $requestedRefIds = array_column($requestedRefunds, 'line_item_id'); // These are service_catalog_id or inventory_item_id
            
            Log::info('Processing partial refund', [
                'billing_cycle_id' => $billingCycleId,
                'requested_reference_ids' => $requestedRefIds,
                'requested_refunds' => $requestedRefunds,
            ]);

            // 4. Fetch line items that match either service_catalog_id OR inventory_item_id
            $lineItems = InvoiceLineItem::where('billing_cycle_id', $billingCycle->id)
                ->where(function($query) use ($requestedRefIds) {
                    $query->whereIn('service_catalog_id', $requestedRefIds)
                          ->orWhereIn('inventory_item_id', $requestedRefIds);
                })
                ->lockForUpdate()
                ->get();

            // 5. Verify all requested reference IDs were found
            $foundServiceIds = $lineItems->pluck('service_catalog_id')->filter()->toArray();
            $foundInventoryIds = $lineItems->pluck('inventory_item_id')->filter()->toArray();
            $foundRefIds = array_merge($foundServiceIds, $foundInventoryIds);
            
            $missingRefIds = array_diff($requestedRefIds, $foundRefIds);
            
            if (!empty($missingRefIds)) {
                return [
                    'success' => false,
                    'message' => 'Some service/inventory items do not belong to this billing cycle or do not exist.',
                    'errors' => [
                        'line_items' => ['Missing or invalid reference IDs: ' . implode(', ', $missingRefIds)]
                    ],
                ];
            }

            // 6. Validate that referenced inventory and service catalog items still exist in their source tables
            $inventoryIds = $lineItems->pluck('inventory_item_id')->filter()->unique()->toArray();
            $serviceCatalogIds = $lineItems->pluck('service_catalog_id')->filter()->unique()->toArray();

            // Check inventory items exist
            if (!empty($inventoryIds)) {
                $validInventoryIds = InventoryItem::whereIn('id', $inventoryIds)->pluck('id')->toArray();
                $invalidInventoryIds = array_diff($inventoryIds, $validInventoryIds);
                
                if (!empty($invalidInventoryIds)) {
                    return [
                        'success' => false,
                        'message' => 'Referenced inventory items no longer exist.',
                        'errors' => [
                            'inventory_item_id' => ['Invalid inventory IDs: ' . implode(', ', $invalidInventoryIds)]
                        ],
                    ];
                }
            }

            // Check service catalog items exist
            if (!empty($serviceCatalogIds)) {
                $validServiceIds = ServiceCatalog::whereIn('id', $serviceCatalogIds)->pluck('id')->toArray();
                $invalidServiceIds = array_diff($serviceCatalogIds, $validServiceIds);
                
                if (!empty($invalidServiceIds)) {
                    return [
                        'success' => false,
                        'message' => 'Referenced service catalog items no longer exist.',
                        'errors' => [
                            'service_catalog_id' => ['Invalid service catalog IDs: ' . implode(', ', $invalidServiceIds)]
                        ],
                    ];
                }
            }

            // 7. Parse tax details from billing cycle
            $taxDetails = json_decode($billingCycle->tax_details, true);
            $totalTaxAmount = isset($taxDetails[28]) ? (float) $taxDetails[28] : 0;
            $billingSubtotal = (float) $billingCycle->subtotal;

            // 8. Calculate per-line refund amounts with tax
            $totalRefundSubtotal = 0;
            $totalRefundTax = 0;
            $totalRefundAmount = 0;
            $affectedLineItems = [];

            foreach ($requestedRefunds as $requestedItem) {
                // Find the line item by matching either service_catalog_id or inventory_item_id
                $lineItem = $lineItems->first(function($item) use ($requestedItem) {
                    return ($item->service_catalog_id == $requestedItem['line_item_id'] || 
                            $item->inventory_item_id == $requestedItem['line_item_id']);
                });

                if (!$lineItem) {
                    return [
                        'success' => false,
                        'message' => "Line item with reference ID {$requestedItem['line_item_id']} not found.",
                        'errors' => ['line_items' => ["Invalid reference ID: {$requestedItem['line_item_id']}"]],
                    ];
                }

                // Calculate tax attributable to this line item
                $lineItemProportion = $billingSubtotal > 0 
                    ? (float) $lineItem->line_total_amount / $billingSubtotal 
                    : 0;
                $lineItemTaxAmount = round($totalTaxAmount * $lineItemProportion, 2);
                $lineItemTotalWithTax = (float) $lineItem->line_total_amount + $lineItemTaxAmount;

                // Determine refund amount
                $refundSubtotal = isset($requestedItem['refund_amount'])
                    ? (float) $requestedItem['refund_amount']
                    : (float) $lineItem->line_total_amount;

                // Calculate proportional tax refund
                $lineItemSubtotal = (float) $lineItem->line_total_amount;
                $refundTax = $lineItemSubtotal > 0 
                    ? round($refundSubtotal * ($lineItemTaxAmount / $lineItemSubtotal), 2) 
                    : 0;
                $refundTotal = $refundSubtotal + $refundTax;

                // Validate refund subtotal
                if ($refundSubtotal <= 0) {
                    return [
                        'success' => false,
                        'message' => "Refund amount must be greater than zero.",
                        'errors' => ['line_items' => ["Invalid refund amount: {$refundSubtotal}"]],
                    ];
                }

                if ($lineItem->total_amount_charged > (float) $lineItem->line_total_amount) {
                    return [
                        'success' => false,
                        'message' => "Refund amount exceeds original amount.",
                        'errors' => [
                            'line_items' => [
                                "Requested {$refundSubtotal}, max allowed {$lineItem->line_total_amount}"
                            ],
                        ],
                    ];
                }

                // Validate quantity
                $refundQuantity = $requestedItem['quantity'] ?? (float) $lineItem->quantity;
                if ($refundQuantity <= 0) {
                    return [
                        'success' => false,
                        'message' => "Refund quantity must be greater than zero.",
                        'errors' => ['line_items' => ["Invalid refund quantity: {$refundQuantity}"]],
                    ];
                }

                if ($refundQuantity > (float) $lineItem->quantity) {
                    return [
                        'success' => false,
                        'message' => "Refund quantity exceeds original quantity.",
                        'errors' => [
                            'line_items' => [
                                "Requested quantity {$refundQuantity}, max allowed {$lineItem->quantity}"
                            ],
                        ],
                    ];
                }

                $totalRefundSubtotal += $refundSubtotal;
                $totalRefundTax += $refundTax;
                $totalRefundAmount += $refundTotal;
                
                $affectedLineItems[] = [
                    'line_item_id' => $lineItem->id,
                    'reference_id' => $requestedItem['line_item_id'], // The service_catalog_id or inventory_item_id
                    'reference_type' => $lineItem->service_catalog_id ? 'service' : 'inventory',
                    'line_item_uuid' => $lineItem->uuid ?? $lineItem->line_item_uuid,
                    'service_code' => $lineItem->service_code,
                    'service_name' => $lineItem->description ?? $lineItem->service_description,
                    'original_subtotal' => (float) $lineItem->line_total_amount,
                    'original_tax' => $lineItemTaxAmount,
                    'original_total' => $lineItemTotalWithTax,
                    'refund_subtotal' => $refundSubtotal,
                    'refund_tax' => $refundTax,
                    'refund_total' => $refundTotal,
                    'original_quantity' => (float) $lineItem->quantity,
                    'refund_quantity' => $refundQuantity,
                    'service_catalog_id' => $lineItem->service_catalog_id,
                    'inventory_item_id' => $lineItem->inventory_item_id,
                ];
            }

            // 9. Validate total refund against payments received
            // $totalPaymentsReceived = (float) $billingCycle->patient_payment_received 
            //                        + (float) $billingCycle->insurance_payment_received;
            
            // if ($totalRefundAmount > $totalPaymentsReceived) {
            //     return [
            //         'success' => false,
            //         'message' => 'Refund amount exceeds total payments received.',
            //         'errors' => [
            //             'refund_amount' => [
            //                 "Requested refund: {$totalRefundAmount}, "
            //                 . "Available payments: {$totalPaymentsReceived}"
            //             ],
            //         ],
            //     ];
            // }

            // 10. Split refund proportionally between patient and insurance
            $originalTotal = (float) $billingCycle->patient_payment_received
                           + (float) $billingCycle->insurance_payment_received;
            
            $patientRatio = $originalTotal > 0
                ? (float) $billingCycle->patient_payment_received / $originalTotal
                : 0;
            
            $patientRefund = round($totalRefundAmount * $patientRatio, 2);
            $insuranceRefund = round($totalRefundAmount - $patientRefund, 2);
            
            $patientRefundSubtotal = round($totalRefundSubtotal * $patientRatio, 2);
            $patientRefundTax = round($totalRefundTax * $patientRatio, 2);
            $insuranceRefundSubtotal = $totalRefundSubtotal - $patientRefundSubtotal;
            $insuranceRefundTax = $totalRefundTax - $patientRefundTax;

            // 11. Persist the financial adjustment
            $adjustment = $this->createFinancialAdjustment([
                'facility_id' => $facilityId,
                'billing_cycle_id' => $billingCycle->id,
                'visit_id' => $billingCycle->visit_id,
                'patient_id' => $billingCycle->patient_id,
                'adjustment_type' => 'partial_refund',
                'adjustment_reason' => $refundData['reason'],
                'reason_notes' => $refundData['reason_notes'] ?? null,
                'original_amount' => (float) $billingCycle->net_amount,
                'adjustment_amount' => $totalRefundAmount,
                'remaining_amount' => (float) $billingCycle->net_amount - $totalRefundSubtotal,
                'patient_refund_amount' => $patientRefund,
                'insurance_refund_amount' => $insuranceRefund,
                'refund_methods' => $refundData['refund_methods'] ?? [],
                'affected_line_items' => $affectedLineItems,
                'restore_inventory' => $refundData['restore_inventory'] ?? false,
                'requested_by_staff_id' => $staffId,
                'original_billing_snapshot' => $this->createBillingSnapshot($billingCycle),
                'tax_details' => json_encode([
                    'total_tax_refunded' => $totalRefundTax,
                    'patient_tax_refund' => $patientRefundTax,
                    'insurance_tax_refund' => $insuranceRefundTax,
                    'original_tax_details' => $taxDetails,
                ]),
            ]);

            // 12. Update billing cycle financials
            $billingCycle->patient_payment_received -= $patientRefund;
            $billingCycle->insurance_payment_received -= $insuranceRefund;
            $billingCycle->bad_debt_adjustment = (float) $billingCycle->bad_debt_adjustment + $totalRefundSubtotal;
            $billingCycle->updated_by_staff_id = $staffId;

            // Recalculate billing status
            $remainingPaid = (float) $billingCycle->patient_payment_received
                           + (float) $billingCycle->insurance_payment_received;

            if ($remainingPaid <= 0) {
                $billingCycle->billing_status = 'partially_refunded';
            } elseif ($remainingPaid < (float) $billingCycle->net_amount) {
                $billingCycle->billing_status = 'partially_paid';
            }

            // Update metadata
            $billingCycle->metadata = $this->mergeMetadata($billingCycle->metadata, [
                'refunds' => array_merge(
                    $billingCycle->metadata['refunds'] ?? [],
                    [[
                        'refunded_at' => now()->toIso8601String(),
                        'refunded_by_staff_id' => $staffId,
                        'adjustment_id' => $adjustment->id,
                        'reference_number' => $adjustment->reference_number,
                        'refund_amount' => $totalRefundAmount,
                        'refund_subtotal' => $totalRefundSubtotal,
                        'refund_tax' => $totalRefundTax,
                        'refund_type' => 'partial_refund',
                        'patient_refund' => $patientRefund,
                        'insurance_refund' => $insuranceRefund,
                        'affected_reference_ids' => array_column($affectedLineItems, 'reference_id'),
                    ]]
                ),
            ]);
            
            $billingCycle->save();

            // 13. Update affected line items - store extra data in metadata
            foreach ($affectedLineItems as $affected) {
                $lineItem = $lineItems->firstWhere('id', $affected['line_item_id']);
                
                // Get existing metadata or create new
                $metadata = $lineItem->metadata ? json_decode($lineItem->metadata, true) : [];
                $metadata['refund_details'] = [
                    'tax_amount' => $affected['refund_tax'],
                    'total_amount' => $affected['refund_total'],
                    'adjusted_at' => now()->toIso8601String(),
                    'adjusted_by_staff_id' => $staffId,
                    'reference_id' => $affected['reference_id'] ?? null,
                    'reference_type' => $affected['reference_type'] ?? null,
                ];
                
                $lineItem->line_item_status = 'adjusted';
                $lineItem->adjustment_amount = $affected['refund_subtotal'];
                $lineItem->adjustment_reason = "Partial refund — {$refundData['reason']}";
                $lineItem->metadata = json_encode($metadata);
                
                $lineItem->save();
            }

            // 14. Restore inventory if requested
            if ($refundData['restore_inventory'] ?? false) {
                $itemsToRestore = $lineItems->whereIn('id', array_column($affectedLineItems, 'line_item_id'));
                $restored = $this->restoreInventory(
                    $itemsToRestore,
                    $staffId,
                    $adjustment->reference_number
                );
                
                $adjustment->inventory_restored = $restored['success'] ?? false;
                $adjustment->save();
            }

            // 15. Update visit payment state
            $this->updateVisitAfterRefund($billingCycle->visit, $billingCycle, $adjustment, $staffId);

            // 16. Seal the adjustment
            $this->completeAdjustment($adjustment, $staffId);

            Log::info('Partial refund processed successfully', [
                'billing_cycle_id' => $billingCycle->id,
                'adjustment_id' => $adjustment->id,
                'reference_number' => $adjustment->reference_number,
                'refund_amount' => $totalRefundAmount,
                'patient_refund' => $patientRefund,
                'insurance_refund' => $insuranceRefund,
                'affected_reference_ids' => array_column($affectedLineItems, 'reference_id'),
                'staff_id' => $staffId,
            ]);

            return [
                'success' => true,
                'message' => 'Partial refund processed successfully.',
                'data' => [
                    'refund_type' => 'partial_refund',
                    'adjustment_id' => $adjustment->id,
                    'reference_number' => $adjustment->reference_number,
                    'refund_amount' => $totalRefundAmount,
                    'refund_subtotal' => $totalRefundSubtotal,
                    'refund_tax' => $totalRefundTax,
                    'patient_refund' => $patientRefund,
                    'insurance_refund' => $insuranceRefund,
                    'affected_reference_ids' => array_column($affectedLineItems, 'reference_id'),
                    'remaining_balance' => (float) $adjustment->remaining_amount,
                    'inventory_restored' => $adjustment->restore_inventory ?? false,
                    'completed_at' => now()->toIso8601String(),
                ],
            ];
        });

    } catch (Throwable $e) {
        Log::error('Partial refund processing failed — all changes rolled back', [
            'billing_cycle_id' => $billingCycleId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return [
            'success' => false,
            'message' => 'Failed to process partial refund. All changes have been rolled back.',
            'error' => config('app.debug') ? $e->getMessage() : null,
        ];
    }
}
    // =========================================================================
    // PRIVATE MODULE / HELPER METHODS
    // =========================================================================

    /**
     * Validate that a billing cycle is eligible for a refund.
     *
     * Checks performed:
     *   1. Cycle must not be soft-deleted (already voided).
     *   2. Full refunds cannot be re-issued if one already exists.
     *   3. Must have recorded payments to refund against.
     *
     * @param BillingCycle $billingCycle
     * @param string       $refundType  'full_refund' | 'partial_refund'
     * @return array{success: bool, message?: string, errors?: array}
     */
    private function validateRefundEligibility(BillingCycle $billingCycle, string $refundType): array
    {
        // Cannot refund a voided (soft-deleted) cycle
        if ($billingCycle->trashed()) {
            return [
                'success' => false,
                'message' => 'Cannot refund a voided transaction.',
                'errors'  => ['refund' => ['This billing cycle has been voided.']],
            ];
        }

        // Prevent duplicate full refunds
        if ($refundType === 'full_refund') {
            $alreadyFullyRefunded = FinancialAdjustment::where('billing_cycle_id', $billingCycle->id)
                ->where('adjustment_type', 'full_refund')
                ->where('status', 'completed')
                ->exists();

            if ($alreadyFullyRefunded) {
                return [
                    'success' => false,
                    'message' => 'This billing cycle has already been fully refunded.',
                    'errors'  => ['refund' => ['A completed full refund already exists for this cycle.']],
                ];
            }
        }

        // Must have payments to refund
        $totalPaid = (float) $billingCycle->patient_payment_received
                   + (float) $billingCycle->insurance_payment_received;

        if ($totalPaid <= 0) {
            return [
                'success' => false,
                'message' => 'No payments have been received for this billing cycle.',
                'errors'  => ['refund' => ['Cannot issue a refund when no payment has been recorded.']],
            ];
        }

        return ['success' => true];
    }

    /**
     * Validate that a billing cycle can be voided.
     *
     * Checks performed:
     *   1. Cycle must not already be soft-deleted.
     *   2. Must be in 'draft' status, or created within the last 24 hours.
     *   3. Insurance claim must not yet have been submitted.
     *
     * @param BillingCycle $billingCycle
     * @return array{success: bool, message?: string, errors?: array}
     */
    private function validateVoidEligibility(BillingCycle $billingCycle): array
    {
        if ($billingCycle->trashed()) {
            return [
                'success' => false,
                'message' => 'This transaction has already been voided.',
                'errors'  => ['void' => ['Billing cycle is already in a voided state.']],
            ];
        }

        $hoursSinceCreation = now()->diffInHours($billingCycle->created_at);

        if ($billingCycle->billing_status !== 'draft' && $hoursSinceCreation > 24) {
            return [
                'success' => false,
                'message' => 'Only draft billings or those created within 24 hours can be voided.',
                'errors'  => ['void' => ['Transaction is too old to void. Process a refund instead.']],
            ];
        }

        if ($billingCycle->insurance_claim_submitted_at !== null) {
            return [
                'success' => false,
                'message' => 'Cannot void after an insurance claim has been submitted.',
                'errors'  => ['void' => ['Insurance claim already submitted. Process a refund instead.']],
            ];
        }

        return ['success' => true];
    }

    /**
     * Persist a FinancialAdjustment record with a guaranteed-unique reference number.
     *
     * Status is set to 'processing' on creation; call completeAdjustment() to seal it.
     *
     * @param array $data  All fillable fields except adjustment_uuid and reference_number.
     * @return FinancialAdjustment
     */
    private function createFinancialAdjustment(array $data): FinancialAdjustment
    {
        // Generate a collision-free reference number
        do {
            $referenceNumber = 'REF-' . strtoupper(Str::random(8));
        } while (FinancialAdjustment::where('reference_number', $referenceNumber)->exists());

        return FinancialAdjustment::create(array_merge($data, [
            'adjustment_uuid' => Str::uuid(),
            'reference_number'=> $referenceNumber,
            'status'          => 'processing',
        ]));
    }

    /**
     * Stamp 'completed' onto a FinancialAdjustment and record approval.
     *
     * Auto-approves the adjustment using the same staff member who initiated it.
     *
     * @param FinancialAdjustment $adjustment
     * @param int                 $staffId
     * @return void
     */
    private function completeAdjustment(FinancialAdjustment $adjustment, int $staffId): void
    {
        $adjustment->status               = 'completed';
        $adjustment->completed_at         = now();
        $adjustment->approved_by_staff_id = $staffId;
        $adjustment->approved_at          = now();
        $adjustment->save();
    }

    /**
     * Capture an exact snapshot of the billing_cycles row at the moment of
     * adjustment, matching every column in the billing_cycles migration.
     *
     * This snapshot is stored in financial_adjustments.original_billing_snapshot
     * and serves as an immutable audit record.
     *
     * @param BillingCycle $billingCycle
     * @return array
     */
    private function createBillingSnapshot(BillingCycle $billingCycle): array
    {
        return [
            // ---- Identity ---------------------------------------------------
            'id'                           => $billingCycle->id,
            'billing_cycle_uuid'           => $billingCycle->billing_cycle_uuid,
            'facility_id'                  => $billingCycle->facility_id,
            'visit_id'                     => $billingCycle->visit_id,
            'patient_id'                   => $billingCycle->patient_id,

            // ---- Cycle definition -------------------------------------------
            'cycle_type'                   => $billingCycle->cycle_type,
            'period_start'                 => optional($billingCycle->period_start)->toIso8601String(),
            'period_end'                   => optional($billingCycle->period_end)->toIso8601String(),
            'days_in_cycle'                => $billingCycle->days_in_cycle,

            // ---- Financial summary ------------------------------------------
            'total_amount_charged'         => $billingCycle->total_amount_charged,
            'total_adjustments'            => $billingCycle->total_adjustments,
            'net_amount'                   => $billingCycle->net_amount,

            // ---- Insurance --------------------------------------------------
            'primary_insurance_claim_number' => $billingCycle->primary_insurance_claim_number,
            'insurance_covered_amount'     => $billingCycle->insurance_covered_amount,
            'insurance_adjustment_amount'  => $billingCycle->insurance_adjustment_amount,
            'insurance_payment_received'   => $billingCycle->insurance_payment_received,
            'insurance_claim_submitted_at' => optional($billingCycle->insurance_claim_submitted_at)->toIso8601String(),
            'insurance_payment_received_at'=> optional($billingCycle->insurance_payment_received_at)->toIso8601String(),

            // ---- Patient responsibility ------------------------------------
            'patient_responsibility_amount'=> $billingCycle->patient_responsibility_amount,
            'patient_copay_amount'         => $billingCycle->patient_copay_amount,
            'patient_deductible_amount'    => $billingCycle->patient_deductible_amount,
            'patient_coinsurance_amount'   => $billingCycle->patient_coinsurance_amount,
            'patient_payment_received'     => $billingCycle->patient_payment_received,

            // ---- Discounts & adjustments ------------------------------------
            'discount_applied'             => $billingCycle->discount_applied,
            'discount_reason'              => $billingCycle->discount_reason,
            'contractual_adjustment'       => $billingCycle->contractual_adjustment,
            'charity_care_adjustment'      => $billingCycle->charity_care_adjustment,
            'bad_debt_adjustment'          => $billingCycle->bad_debt_adjustment,

            // ---- Tax --------------------------------------------------------
            'tax_details'                  => $billingCycle->tax_details,
            'total_tax_amount'             => $billingCycle->total_tax_amount,

            // ---- Billing status & dates ------------------------------------
            'billing_status'               => $billingCycle->billing_status,
            'billed_at'                    => optional($billingCycle->billed_at)->toIso8601String(),
            'payment_due_date'             => optional($billingCycle->payment_due_date)->toIso8601String(),
            'days_outstanding'             => $billingCycle->days_outstanding,

            // ---- Collections & follow-up ------------------------------------
            'statement_count'              => $billingCycle->statement_count,
            'last_statement_sent_at'       => optional($billingCycle->last_statement_sent_at)->toIso8601String(),
            'sent_to_collections_at'       => optional($billingCycle->sent_to_collections_at)->toIso8601String(),
            'collections_agency'           => $billingCycle->collections_agency,

            // ---- Dispute management ----------------------------------------
            'is_disputed'                  => $billingCycle->is_disputed,
            'dispute_reason'               => $billingCycle->dispute_reason,
            'dispute_opened_at'            => optional($billingCycle->dispute_opened_at)->toIso8601String(),
            'dispute_resolved_at'          => optional($billingCycle->dispute_resolved_at)->toIso8601String(),

            // ---- Audit trail ------------------------------------------------
            'created_by_staff_id'          => $billingCycle->created_by_staff_id,
            'updated_by_staff_id'          => $billingCycle->updated_by_staff_id,
            'created_at'                   => optional($billingCycle->created_at)->toIso8601String(),
            'updated_at'                   => optional($billingCycle->updated_at)->toIso8601String(),
            'deleted_at'                   => optional($billingCycle->deleted_at)->toIso8601String(),
            'metadata'                     => $billingCycle->metadata,

            // ---- Line items snapshot (full child rows) ----------------------
            'line_items' => $billingCycle->lineItems->map(fn($item) => [
                'id'               => $item->id,
                'line_item_uuid'   => $item->line_item_uuid,
                'service_code'     => $item->service_code,
                'service_name'     => $item->service_description,
                'quantity'         => $item->quantity,
                'unit_of_measure'  => $item->unit_of_measure,
                'unit_price'       => $item->unit_price_at_time,
                'line_total'       => $item->line_total_amount,
                'discount_amount'  => $item->discount_amount,
                'net_amount'       => $item->net_amount,
                'line_item_status' => $item->line_item_status,
            ])->toArray(),

            // ---- Snapshot metadata -----------------------------------------
            'snapshot_taken_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Restore inventory stock for a set of line items after a refund or void.
     *
     * Items with no matching InventoryItem record (pure services) are silently
     * skipped.  Failures on individual items are logged but do not abort the
     * parent transaction.
     *
     * @param iterable $lineItems        Line items whose stock should be restored.
     * @param int      $staffId          Staff performing the action.
     * @param string   $referenceNumber  The parent adjustment reference (for audit).
     * @return array   List of restored items with before/after quantities.
     */
    private function restoreInventory($lineItems, int $staffId, string $referenceNumber): array
    {
        $restored = [];

        foreach ($lineItems as $lineItem) {
            try {
                $inventoryItem = InventoryItem::where('item_code', $lineItem->service_code)
                    ->where('status', 'active')
                    ->lockForUpdate()
                    ->first();

                // Not an inventory-tracked item — skip silently
                if (!$inventoryItem) {
                    continue;
                }

                $previousQty  = (int) $inventoryItem->package_quantity;
                $restoreQty   = (int) $lineItem->quantity;
                $newQty       = $previousQty + $restoreQty;

                $inventoryItem->package_quantity    = $newQty;
                $inventoryItem->updated_by_staff_id = $staffId;

                // Append to the item's own audit trail
                $meta = is_array($inventoryItem->metadata)
                    ? $inventoryItem->metadata
                    : json_decode($inventoryItem->metadata ?? '{}', true);

                $meta['stock_restorations'][] = [
                    'restored_at'          => now()->toIso8601String(),
                    'restored_by_staff_id' => $staffId,
                    'units_restored'       => $restoreQty,
                    'previous_quantity'    => $previousQty,
                    'new_quantity'         => $newQty,
                    'reference_number'     => $referenceNumber,
                    'reason'               => 'refund_or_void',
                ];

                $inventoryItem->metadata = $meta;
                $inventoryItem->save();

                $restored[] = [
                    'item_code'         => $lineItem->service_code,
                    'item_name'         => $lineItem->service_description,
                    'quantity_restored' => $restoreQty,
                    'new_quantity'      => $newQty,
                ];

                Log::info('Inventory restored', [
                    'item_code'         => $lineItem->service_code,
                    'quantity_restored' => $restoreQty,
                    'new_quantity'      => $newQty,
                    'reference_number'  => $referenceNumber,
                ]);

            } catch (Throwable $e) {
                // Log individual failures without aborting the loop
                Log::error('Failed to restore inventory for line item', [
                    'item_code' => $lineItem->service_code ?? 'unknown',
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        return $restored;
    }

    /**
     * Recalculate and persist visit payment state after any refund operation.
     *
     * Updates payment_status, estimated responsibility, and appends a refund
     * entry to the visit's metadata.
     *
     * @param Visit               $visit
     * @param BillingCycle        $billingCycle  The (already mutated) billing cycle.
     * @param FinancialAdjustment $adjustment
     * @param int                 $staffId
     * @return void
     */
    private function updateVisitAfterRefund(
        Visit $visit,
        BillingCycle $billingCycle,
        FinancialAdjustment $adjustment,
        int $staffId
    ): void {
        $remainingPaid = (float) $billingCycle->patient_payment_received
                       + (float) $billingCycle->insurance_payment_received;

        $visit->payment_status = $remainingPaid <= 0
            ? 'pending'
            : ($remainingPaid < (float) $billingCycle->net_amount ? 'partially_paid' : 'paid_in_full');

        $visit->estimated_total_charges         = $billingCycle->net_amount;
        $visit->patient_estimated_responsibility = $remainingPaid;
        $visit->updated_by_staff_id              = $staffId;

        $meta = is_array($visit->metadata)
            ? $visit->metadata
            : json_decode($visit->metadata ?? '{}', true);

        $meta['refunds'][] = [
            'adjustment_id'   => $adjustment->id,
            'reference_number'=> $adjustment->reference_number,
            'adjustment_type' => $adjustment->adjustment_type,
            'refund_amount'   => $adjustment->adjustment_amount,
            'processed_at'    => now()->toIso8601String(),
            'processed_by'    => $staffId,
        ];

        $visit->metadata = $meta;
        $visit->save();
    }

    /**
     * Reset visit billing state after a void.
     *
     * Sets payment_status back to 'pending', zeroes out the financial snapshot
     * fields, removes the voided cycle from billing history, and records a void
     * entry in metadata.
     *
     * @param Visit               $visit
     * @param BillingCycle        $billingCycle
     * @param FinancialAdjustment $adjustment
     * @param int                 $staffId
     * @return void
     */
    private function updateVisitAfterVoid(
        Visit $visit,
        BillingCycle $billingCycle,
        FinancialAdjustment $adjustment,
        int $staffId
    ): void {
        $visit->payment_status                   = 'pending';
        $visit->estimated_total_charges          = 0;
        $visit->patient_estimated_responsibility = 0;
        $visit->updated_by_staff_id              = $staffId;

        $meta = is_array($visit->metadata)
            ? $visit->metadata
            : json_decode($visit->metadata ?? '{}', true);

        // Remove the voided cycle from any billing history entries
        if (isset($meta['billing'])) {
            $meta['billing'] = array_values(
                array_filter($meta['billing'], fn($entry) => $entry['billing_cycle_id'] !== $billingCycle->id)
            );
        }

        $meta['voided_transactions'][] = [
            'billing_cycle_id' => $billingCycle->id,
            'adjustment_id'    => $adjustment->id,
            'reference_number' => $adjustment->reference_number,
            'voided_at'        => now()->toIso8601String(),
            'voided_by'        => $staffId,
        ];

        $visit->metadata = $meta;
        $visit->save();
    }

    /**
     * Merge $additions into an existing metadata value that may be an array,
     * a JSON string, or null.
     *
     * @param mixed $existing
     * @param array $additions
     * @return array
     */
    private function mergeMetadata(mixed $existing, array $additions): array
    {
        $base = is_array($existing)
            ? $existing
            : json_decode($existing ?? '{}', true) ?? [];

        return array_merge($base, $additions);
    }

    /**
     * Build a standard "not found" error response.
     *
     * @param string $message
     * @param string $errorKey   Key used inside the 'errors' bag.
     * @return array
     */
    private function notFound(string $message, string $errorKey): array
    {
        return [
            'success' => false,
            'message' => $message,
            'errors'  => [$errorKey => ["Invalid or inaccessible {$errorKey}."]],
        ];
    }
}
