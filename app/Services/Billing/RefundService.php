<?php

namespace App\Services\Billing;

use App\Models\BillingCycle;
use App\Models\FinancialAdjustment;
use App\Models\InventoryItem;
use App\Models\InvoiceLineItem;
use App\Models\Visit;
use App\Services\Billing\Traits\BillingHelpers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class RefundService
{
    use BillingHelpers;

    /**
     * Public entry point.
     *
     * Rules:
     * - line_items present and non-empty => partial_refund
     * - otherwise => full_refund
     */
    public function refundTransaction(
        int $billingCycleId,
        array $refundData,
        int $facilityId,
        int $staffId
    ): array {
        $refundType = $this->determineRefundType($refundData);

        return $refundType === 'partial_refund'
            ? $this->processPartialRefund($billingCycleId, $refundData, $facilityId, $staffId)
            : $this->processFullRefund($billingCycleId, $refundData, $facilityId, $staffId);
    }

    /**
     * Void entry point.
     */
    public function voidTransaction(
        int $billingCycleId,
        array $voidData,
        int $facilityId,
        int $staffId
    ): array {
        try {
            return DB::transaction(function () use ($billingCycleId, $voidData, $facilityId, $staffId) {
                $billingCycle = BillingCycle::query()
                    ->where('id', $billingCycleId)
                    ->where('facility_id', $facilityId)
                    ->with(['lineItems', 'visit'])
                    ->lockForUpdate()
                    ->first();

                if (!$billingCycle) {
                    return $this->notFound('Billing cycle not found.', 'billing_cycle');
                }

                $eligibility = $this->validateVoidEligibility($billingCycle);
                if (!$eligibility['success']) {
                    return $eligibility;
                }

                $snapshot = $this->createBillingSnapshot($billingCycle);

                $adjustment = $this->createFinancialAdjustment([
                    'facility_id' => $facilityId,
                    'billing_cycle_id' => $billingCycle->id,
                    'visit_id' => $billingCycle->visit_id,
                    'patient_id' => $billingCycle->patient_id,
                    'adjustment_type' => 'void_transaction',
                    'adjustment_reason' => $voidData['reason'],
                    'reason_notes' => $voidData['reason_notes'] ?? null,
                    'original_amount' => round((float) ($billingCycle->grand_total_amount ?? $billingCycle->net_amount ?? 0), 2),
                    'adjustment_amount' => 0.00,
                    'remaining_amount' => 0.00,
                    'patient_refund_amount' => 0.00,
                    'insurance_refund_amount' => 0.00,
                    'restore_inventory' => $voidData['restore_inventory'] ?? true,
                    'requested_by_staff_id' => $staffId,
                    'original_billing_snapshot' => $snapshot,
                ]);

                $billingCycle->billing_status = 'written_off';
                $billingCycle->updated_by_staff_id = $staffId;
                $billingCycle->metadata = $this->mergeMetadata($billingCycle->metadata, [
                    'voided' => [
                        'voided_at' => now()->toIso8601String(),
                        'voided_by_staff_id' => $staffId,
                        'adjustment_id' => $adjustment->id,
                        'reference_number' => $adjustment->reference_number,
                        'reason' => $voidData['reason'],
                    ],
                ]);
                $billingCycle->save();
                $billingCycle->delete();

                foreach ($billingCycle->lineItems as $lineItem) {
                    $lineItem->line_item_status = 'written_off';
                    $lineItem->deleted_at = now();
                    $lineItem->save();
                }

                $inventoryRestored = false;
                $restoredInventoryDetails = [];

                if (($voidData['restore_inventory'] ?? true) === true) {
                    $restorePlans = $billingCycle->lineItems->map(function (InvoiceLineItem $lineItem) {
                        return [
                            'line_item_id' => (int) $lineItem->id,
                            'line_item_uuid' => $lineItem->line_item_uuid,
                            'matched_reference_id' => null,
                            'matched_reference_type' => null,
                            'service_code' => $lineItem->service_code,
                            'service_description' => $lineItem->service_description,
                            'inventory_item_id' => $lineItem->inventory_item_id,
                            'unit_price' => round((float) ($lineItem->unit_price_at_time ?? 0), 2),
                            'original_quantity' => round((float) ($lineItem->quantity ?? 0), 2),
                            'refund_quantity' => round((float) ($lineItem->quantity ?? 0), 2),
                            'remaining_quantity' => 0.00,
                            'original_subtotal' => round((float) ($lineItem->line_total_amount ?? 0), 2),
                            'refund_subtotal' => round((float) ($lineItem->line_total_amount ?? 0), 2),
                            'remaining_subtotal' => 0.00,
                        ];
                    })->values()->all();

                    $restoredInventoryDetails = $this->restoreInventoryForRefundedLineItems(
                        $restorePlans,
                        $staffId,
                        $adjustment->reference_number
                    );

                    $inventoryRestored = !empty($restoredInventoryDetails);
                    $adjustment->inventory_restored = $restoredInventoryDetails;
                    $adjustment->save();
                }

                if ($billingCycle->visit) {
                    $this->updateVisitAfterVoid($billingCycle->visit, $billingCycle, $adjustment, $staffId);
                }

                $this->completeAdjustment($adjustment, $staffId);

                Log::info('Transaction voided successfully', [
                    'billing_cycle_id' => $billingCycle->id,
                    'adjustment_id' => $adjustment->id,
                    'reference_number' => $adjustment->reference_number,
                    'staff_id' => $staffId,
                ]);

                return [
                    'success' => true,
                    'message' => 'Transaction voided successfully.',
                    'data' => [
                        'adjustment_id' => $adjustment->id,
                        'reference_number' => $adjustment->reference_number,
                        'voided_amount' => round((float) ($billingCycle->grand_total_amount ?? $billingCycle->net_amount ?? 0), 2),
                        'inventory_restored' => $inventoryRestored,
                        'completed_at' => optional($adjustment->completed_at)->toIso8601String() ?? now()->toIso8601String(),
                    ],
                ];
            });
        } catch (Throwable $e) {
            Log::error('Transaction void failed', [
                'billing_cycle_id' => $billingCycleId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to void transaction. All changes have been rolled back.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    /**
     * Determine refund type from payload.
     */
    private function determineRefundType(array $refundData): string
    {
        return !empty($refundData['line_items']) ? 'partial_refund' : 'full_refund';
    }

    /**
     * Full refund:
     * refund every currently active invoice line item in the billing cycle.
     */
    private function processFullRefund(
        int $billingCycleId,
        array $refundData,
        int $facilityId,
        int $staffId
    ): array {
        try {
            return DB::transaction(function () use ($billingCycleId, $refundData, $facilityId, $staffId) {
                $billingCycle = BillingCycle::query()
                    ->where('id', $billingCycleId)
                    ->where('facility_id', $facilityId)
                    ->with(['lineItems', 'visit'])
                    ->lockForUpdate()
                    ->first();

                if (!$billingCycle) {
                    return $this->notFound('Billing cycle not found.', 'billing_cycle');
                }

                $eligibility = $this->validateRefundEligibility($billingCycle, 'full_refund');
                if (!$eligibility['success']) {
                    return $eligibility;
                }

                $activeLineItems = $this->getActiveBillingCycleLineItems($billingCycle);

                if ($activeLineItems->isEmpty()) {
                    return [
                        'success' => false,
                        'message' => 'There are no active line items left to refund.',
                        'errors' => [
                            'refund' => ['This billing cycle has no refundable active line items.'],
                        ],
                    ];
                }

                $plans = [];

                foreach ($activeLineItems as $lineItem) {
                    try {
                        $plans[] = $this->buildRefundPlanForLineItem(
                            $lineItem,
                            null,
                            null,
                            null,
                            null
                        );
                    } catch (RuntimeException $e) {
                        return [
                            'success' => false,
                            'message' => 'Unable to prepare full refund.',
                            'errors' => [
                                'refund' => [$e->getMessage()],
                            ],
                        ];
                    }
                }

                return $this->executeRefundAdjustment(
                    $billingCycle,
                    $plans,
                    $refundData,
                    $staffId,
                    'full_refund'
                );
            });
        } catch (Throwable $e) {
            Log::error('Full refund processing failed', [
                'billing_cycle_id' => $billingCycleId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to process full refund. All changes have been rolled back.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    /**
     * Partial refund:
     * frontend line_item_id is a reference id that maps to either:
     * - service_catalog_id
     * - inventory_item_id
     *
     * We resolve it to the actual invoice_line_items row within this billing cycle.
     */
    private function processPartialRefund(
        int $billingCycleId,
        array $refundData,
        int $facilityId,
        int $staffId
    ): array {
        try {
            return DB::transaction(function () use ($billingCycleId, $refundData, $facilityId, $staffId) {
                $billingCycle = BillingCycle::query()
                    ->where('id', $billingCycleId)
                    ->where('facility_id', $facilityId)
                    ->with(['lineItems', 'visit'])
                    ->lockForUpdate()
                    ->first();

                if (!$billingCycle) {
                    return $this->notFound('Billing cycle not found.', 'billing_cycle');
                }

                $eligibility = $this->validateRefundEligibility($billingCycle, 'partial_refund');
                if (!$eligibility['success']) {
                    return $eligibility;
                }

                if (empty($refundData['line_items']) || !is_array($refundData['line_items'])) {
                    return [
                        'success' => false,
                        'message' => 'Line items are required for a partial refund.',
                        'errors' => [
                            'line_items' => ['At least one line item must be provided for a partial refund.'],
                        ],
                    ];
                }

                $requestedItems = collect($refundData['line_items'])->values();

                $referenceIds = $requestedItems
                    ->pluck('line_item_id')
                    ->filter(fn ($id) => $id !== null)
                    ->map(fn ($id) => (int) $id)
                    ->values()
                    ->all();

                if (count($referenceIds) !== count(array_unique($referenceIds))) {
                    return [
                        'success' => false,
                        'message' => 'Duplicate refund references were supplied.',
                        'errors' => [
                            'line_items' => ['Each refund reference may only appear once in a request.'],
                        ],
                    ];
                }

                $candidateLineItems = InvoiceLineItem::query()
                    ->where('billing_cycle_id', $billingCycle->id)
                    ->where(function ($query) use ($referenceIds) {
                        $query->whereIn('service_catalog_id', $referenceIds)
                            ->orWhereIn('inventory_item_id', $referenceIds);
                    })
                    ->lockForUpdate()
                    ->get();

                $plans = [];

                foreach ($requestedItems as $requestedItem) {
                    $referenceId = (int) $requestedItem['line_item_id'];

                    $matches = $candidateLineItems
                        ->filter(function (InvoiceLineItem $lineItem) use ($referenceId) {
                            return (int) ($lineItem->service_catalog_id ?? 0) === $referenceId
                                || (int) ($lineItem->inventory_item_id ?? 0) === $referenceId;
                        })
                        ->values();

                    if ($matches->isEmpty()) {
                        return [
                            'success' => false,
                            'message' => 'One or more refund references could not be resolved.',
                            'errors' => [
                                'line_item_id' => [
                                    "No invoice line item in this billing cycle matches reference id {$referenceId}.",
                                ],
                            ],
                        ];
                    }

                    $refundableMatches = $matches
                        ->filter(fn (InvoiceLineItem $lineItem) => $this->isRefundableLineItem($lineItem))
                        ->values();

                    if ($refundableMatches->isEmpty()) {
                        return [
                            'success' => false,
                            'message' => 'One or more matched line items are not refundable.',
                            'errors' => [
                                'line_items' => [
                                    "Matched line item for reference id {$referenceId} is not in a refundable state.",
                                ],
                            ],
                        ];
                    }

                    if ($refundableMatches->count() > 1) {
                        return [
                            'success' => false,
                            'message' => 'Refund reference is ambiguous.',
                            'errors' => [
                                'line_item_id' => [
                                    "Reference id {$referenceId} resolves to multiple active invoice line items in this billing cycle.",
                                ],
                            ],
                        ];
                    }

                    /** @var InvoiceLineItem $lineItem */
                    $lineItem = $refundableMatches->first();

                    $serviceCatalogMatch = (int) ($lineItem->service_catalog_id ?? 0) === $referenceId;
                    $inventoryMatch = (int) ($lineItem->inventory_item_id ?? 0) === $referenceId;

                    $matchedReferenceType = $serviceCatalogMatch && $inventoryMatch
                        ? 'service_catalog_or_inventory'
                        : ($serviceCatalogMatch ? 'service_catalog' : 'inventory');

                    try {
                        $plans[] = $this->buildRefundPlanForLineItem(
                            $lineItem,
                            isset($requestedItem['refund_amount']) ? (float) $requestedItem['refund_amount'] : null,
                            isset($requestedItem['quantity']) ? (float) $requestedItem['quantity'] : null,
                            $referenceId,
                            $matchedReferenceType
                        );
                    } catch (RuntimeException $e) {
                        return [
                            'success' => false,
                            'message' => 'Invalid partial refund request.',
                            'errors' => [
                                'line_items' => [$e->getMessage()],
                            ],
                        ];
                    }
                }

                return $this->executeRefundAdjustment(
                    $billingCycle,
                    $plans,
                    $refundData,
                    $staffId,
                    'partial_refund'
                );
            });
        } catch (Throwable $e) {
            Log::error('Partial refund processing failed', [
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

    /**
     * Shared refund executor.
     *
     * Financial rule:
     * - first adjust line items
     * - derive what the billing cycle should become
     * - refundable cash = original total paid - recalculated new grand total
     */
   private function executeRefundAdjustment(
    BillingCycle $billingCycle,
    array $plans,
    array $refundData,
    int $staffId,
    string $refundType
): array {
    $originalSnapshot = $this->createBillingSnapshot($billingCycle->fresh(['lineItems']));

    $originalSubtotal = $this->getActiveBillingCycleSubtotal($billingCycle);
    $originalGrandTotal = round((float) ($billingCycle->grand_total_amount ?? $billingCycle->net_amount ?? 0), 2);
    $originalPatientPaid = round((float) ($billingCycle->patient_payment_received ?? 0), 2);
    $originalInsurancePaid = round((float) ($billingCycle->insurance_payment_received ?? 0), 2);
    $originalTotalPaid = round($originalPatientPaid + $originalInsurancePaid, 2);

    $refundSubtotal = round((float) collect($plans)->sum('refund_subtotal'), 2);

    if ($refundSubtotal <= 0) {
        return [
            'success' => false,
            'message' => 'Refund amount must be greater than zero.',
            'errors' => [
                'refund_amount' => ['The requested refund did not reduce any billable amount.'],
            ],
        ];
    }

    $metadata = $this->decodeJsonishToArray($billingCycle->metadata ?? null);
    $requestedUiStatus = (string) ($metadata['frontend_ui_status'] ?? $this->mapBillingStatusToUI((string) $billingCycle->billing_status));
    $discountRule = $this->extractDiscountRuleFromCycle($billingCycle);
    $taxDefinitions = $this->extractTaxDefinitionsFromCycle($billingCycle);

    $newSubtotal = round(max(0, $originalSubtotal - $refundSubtotal), 2);

    $projectedStateBeforeCashRefund = $this->determineBillingState(
        $newSubtotal,
        $discountRule,
        $taxDefinitions,
        [
            'patient_payment' => 0,
            'insurance_payment' => 0,
            'total_paid' => 0,
        ],
        $requestedUiStatus,
        $billingCycle
    );

    $projectedGrandTotal = round((float) ($projectedStateBeforeCashRefund['grand_total'] ?? 0), 2);
    $cashRefundAmount = round(max(0, $originalTotalPaid - $projectedGrandTotal), 2);

    if ($cashRefundAmount <= 0) {
        return [
            'success' => false,
            'message' => 'This adjustment does not produce a refundable overpayment.',
            'errors' => [
                'refund_amount' => [
                    'The requested line item adjustment lowers charges, but does not create a cash refund.',
                ],
            ],
        ];
    }

    $refundMethods = $this->normalizeRefundMethods($refundData['refund_methods'] ?? []);
    $refundMethodsTotal = round((float) collect($refundMethods)->sum('amount'), 2);

    if ($refundMethodsTotal <= 0) {
        return [
            'success' => false,
            'message' => 'At least one valid refund method is required.',
            'errors' => [
                'refund_methods' => ['No valid refund methods were provided.'],
            ],
        ];
    }

    if (abs($refundMethodsTotal - $cashRefundAmount) > 0.01) {
        return [
            'success' => false,
            'message' => 'Refund method totals do not match the refundable amount.',
            'errors' => [
                'refund_methods' => [
                    "Refund methods total {$refundMethodsTotal} but refundable amount is {$cashRefundAmount}.",
                ],
            ],
        ];
    }

    [$patientRefundAmount, $insuranceRefundAmount] = $this->splitRefundAcrossPayers(
        $cashRefundAmount,
        $originalPatientPaid,
        $originalInsurancePaid
    );

    foreach ($plans as $plan) {
        $lineItem = InvoiceLineItem::query()
            ->lockForUpdate()
            ->findOrFail($plan['line_item_id']);

        $this->applyRefundPlanToLineItem(
            $lineItem,
            $plan,
            $refundData['reason'],
            $staffId
        );
    }

    $patientPaymentAfterRefund = round(max(0, $originalPatientPaid - $patientRefundAmount), 2);
    $insurancePaymentAfterRefund = round(max(0, $originalInsurancePaid - $insuranceRefundAmount), 2);
    $totalPaidAfterRefund = round($patientPaymentAfterRefund + $insurancePaymentAfterRefund, 2);

    $paymentProjectionCycle = $this->buildPaymentProjectionCycle(
        $billingCycle,
        $patientPaymentAfterRefund,
        $insurancePaymentAfterRefund
    );

    $projectedStateAfterCashRefund = $this->determineBillingState(
        $newSubtotal,
        $discountRule,
        $taxDefinitions,
        [
            'patient_payment' => 0,
            'insurance_payment' => 0,
            'total_paid' => 0,
        ],
        $requestedUiStatus,
        $paymentProjectionCycle
    );

    $finalGrandTotal = round((float) ($projectedStateAfterCashRefund['grand_total'] ?? 0), 2);
    $finalTaxTotal = round((float) ($projectedStateAfterCashRefund['tax_total'] ?? 0), 2);
    $finalDiscountAmount = round((float) ($projectedStateAfterCashRefund['discount_amount'] ?? 0), 2);
    $finalTaxableAmount = round((float) ($projectedStateAfterCashRefund['taxable_amount'] ?? 0), 2);
    $finalBalance = round(max(0, $finalGrandTotal - $totalPaidAfterRefund), 2);

    $adjustment = $this->createFinancialAdjustment([
        'facility_id' => $billingCycle->facility_id,
        'billing_cycle_id' => $billingCycle->id,
        'visit_id' => $billingCycle->visit_id,
        'patient_id' => $billingCycle->patient_id,
        'adjustment_type' => $refundType,
        'adjustment_reason' => $refundData['reason'],
        'reason_notes' => $refundData['reason_notes'] ?? null,
        'original_amount' => $originalGrandTotal,
        'adjustment_amount' => $cashRefundAmount,
        'remaining_amount' => $finalGrandTotal,
        'patient_refund_amount' => $patientRefundAmount,
        'insurance_refund_amount' => $insuranceRefundAmount,
        'refund_methods' => $refundMethods,
        'restore_inventory' => $refundData['restore_inventory'] ?? false,
        'requested_by_staff_id' => $staffId,
        'original_billing_snapshot' => $originalSnapshot,
        'affected_line_items' => $this->buildAffectedLineItemDocumentation($plans),
        'tax_details' => json_encode([
            'original_tax_total' => round((float) ($billingCycle->total_tax_amount ?? 0), 2),
            'final_tax_total' => $finalTaxTotal,
            'tax_delta' => round((float) ($billingCycle->total_tax_amount ?? 0) - $finalTaxTotal, 2),
        ]),
    ]);

    $existingRefunds = is_array($metadata['refunds'] ?? null) ? $metadata['refunds'] : [];

    $billingCycle->subtotal_amount = $newSubtotal;
    $billingCycle->total_amount_charged = $newSubtotal;
    $billingCycle->total_adjustments = $finalDiscountAmount;
    $billingCycle->discount_applied = $finalDiscountAmount;
    $billingCycle->discount_reason = $discountRule['reason'] ?? $billingCycle->discount_reason;
    $billingCycle->taxable_amount = $finalTaxableAmount;
    $billingCycle->tax_details = json_encode($projectedStateAfterCashRefund['taxes'] ?? []);
    $billingCycle->total_tax_amount = $finalTaxTotal;
    $billingCycle->net_amount = $finalGrandTotal;
    $billingCycle->grand_total_amount = $finalGrandTotal;
    $billingCycle->patient_payment_received = $patientPaymentAfterRefund;
    $billingCycle->insurance_payment_received = $insurancePaymentAfterRefund;
    $billingCycle->total_paid_amount = $totalPaidAfterRefund;
    $billingCycle->balance_amount = $finalBalance;
    $billingCycle->insurance_covered_amount = $insurancePaymentAfterRefund;
    $billingCycle->patient_responsibility_amount = round(max(0, $finalGrandTotal - $insurancePaymentAfterRefund), 2);
    $billingCycle->updated_by_staff_id = $staffId;
    $billingCycle->billing_status = $this->resolveRefundedBillingStatus($finalGrandTotal, $refundType);
    $billingCycle->payment_due_date = $finalBalance <= 0.01
        ? null
        : ($billingCycle->payment_due_date ?? now()->addDays(30));

    $billingCycle->metadata = $this->mergeMetadata($billingCycle->metadata, [
        'refunds' => array_values(array_merge($existingRefunds, [[
            'adjustment_id' => $adjustment->id,
            'reference_number' => $adjustment->reference_number,
            'refund_type' => $refundType,
            'refund_amount' => $cashRefundAmount,
            'refund_subtotal_adjustment' => $refundSubtotal,
            'original_grand_total' => $originalGrandTotal,
            'final_grand_total' => $finalGrandTotal,
            'patient_refund' => $patientRefundAmount,
            'insurance_refund' => $insuranceRefundAmount,
            'affected_line_items' => $this->buildAffectedLineItemDocumentation($plans),
            'refunded_at' => now()->toIso8601String(),
            'refunded_by_staff_id' => $staffId,
        ]])),
        'last_refund' => [
            'adjustment_id' => $adjustment->id,
            'reference_number' => $adjustment->reference_number,
            'refund_type' => $refundType,
            'refund_amount' => $cashRefundAmount,
            'processed_at' => now()->toIso8601String(),
            'processed_by_staff_id' => $staffId,
        ],
        'resolved_billing_status' => $billingCycle->billing_status,
        'resolved_payment_status' => $this->resolveVisitPaymentStatusFromCycleState(
            $finalGrandTotal,
            $totalPaidAfterRefund,
            $finalBalance
        ),
        'last_recalculated_at' => now()->toIso8601String(),
        'last_recalculated_by_staff_id' => $staffId,
    ]);

    $billingCycle->save();
    $billingCycle = $billingCycle->fresh(['lineItems', 'visit']);

    $this->syncRefundedCycleLineItemStatuses($billingCycle, $staffId);

    $inventoryRestored = false;
    $restoredInventoryDetails = [];

    if (($refundData['restore_inventory'] ?? false) === true) {
        $restoredInventoryDetails = $this->restoreInventoryForRefundedLineItems(
            $plans,
            $staffId,
            $adjustment->reference_number
        );

        $inventoryRestored = !empty($restoredInventoryDetails);
        $adjustment->inventory_restored = $restoredInventoryDetails;
        $adjustment->save();
    }

    if ($billingCycle->visit) {
        $this->updateVisitAfterRefund(
            $billingCycle->visit,
            $billingCycle,
            $adjustment,
            $staffId
        );
    }

    $this->completeAdjustment($adjustment, $staffId);

    Log::info('Refund processed successfully', [
        'billing_cycle_id' => $billingCycle->id,
        'adjustment_id' => $adjustment->id,
        'reference_number' => $adjustment->reference_number,
        'refund_type' => $refundType,
        'refund_amount' => $cashRefundAmount,
        'staff_id' => $staffId,
    ]);

    return [
        'success' => true,
        'message' => $refundType === 'full_refund'
            ? 'Full refund processed successfully.'
            : 'Partial refund processed successfully.',
        'data' => [
            'refund_type' => $refundType,
            'adjustment_id' => $adjustment->id,
            'reference_number' => $adjustment->reference_number,
            'refund_amount' => $cashRefundAmount,
            'patient_refund' => $patientRefundAmount,
            'insurance_refund' => $insuranceRefundAmount,
            'affected_line_items' => count($plans),
            'remaining_balance' => $finalBalance,
            'inventory_restored' => $inventoryRestored,
            'completed_at' => optional($adjustment->completed_at)->toIso8601String() ?? now()->toIso8601String(),
        ],
    ];
}
    /**
     * Billing-cycle level refund eligibility.
     */
    private function validateRefundEligibility(BillingCycle $billingCycle, string $refundType): array
    {
        if ($billingCycle->trashed()) {
            return [
                'success' => false,
                'message' => 'Cannot refund a voided transaction.',
                'errors' => [
                    'refund' => ['This billing cycle has been voided.'],
                ],
            ];
        }

        if (in_array($billingCycle->billing_status, ['written_off', 'charity_care', 'collections', 'disputed'], true)) {
            return [
                'success' => false,
                'message' => 'This billing cycle is not eligible for refund.',
                'errors' => [
                    'refund' => ["Billing cycle in status '{$billingCycle->billing_status}' cannot be refunded."],
                ],
            ];
        }

        if ($billingCycle->billing_status === 'fully_refunded') {
            return [
                'success' => false,
                'message' => 'This billing cycle has already been fully refunded.',
                'errors' => [
                    'refund' => ['No further refund can be issued for a fully refunded billing cycle.'],
                ],
            ];
        }

        $totalPaid = round(
            (float) ($billingCycle->patient_payment_received ?? 0)
            + (float) ($billingCycle->insurance_payment_received ?? 0),
            2
        );

        if ($totalPaid <= 0) {
            return [
                'success' => false,
                'message' => 'No payments have been received for this billing cycle.',
                'errors' => [
                    'refund' => ['Cannot issue a refund when no payment has been recorded.'],
                ],
            ];
        }

        if ($refundType === 'full_refund') {
            $alreadyFullyRefunded = FinancialAdjustment::query()
                ->where('billing_cycle_id', $billingCycle->id)
                ->where('adjustment_type', 'full_refund')
                ->where('status', 'completed')
                ->exists();

            if ($alreadyFullyRefunded) {
                return [
                    'success' => false,
                    'message' => 'A completed full refund already exists for this billing cycle.',
                    'errors' => [
                        'refund' => ['This billing cycle has already had a completed full refund.'],
                    ],
                ];
            }
        }

        return ['success' => true];
    }

    /**
     * Void eligibility.
     */
    private function validateVoidEligibility(BillingCycle $billingCycle): array
    {
        if ($billingCycle->trashed()) {
            return [
                'success' => false,
                'message' => 'This transaction has already been voided.',
                'errors' => [
                    'void' => ['Billing cycle is already in a voided state.'],
                ],
            ];
        }

        $hoursSinceCreation = now()->diffInHours($billingCycle->created_at);

        if ($billingCycle->billing_status !== 'draft' && $hoursSinceCreation > 24) {
            return [
                'success' => false,
                'message' => 'Only draft billings or those created within 24 hours can be voided.',
                'errors' => [
                    'void' => ['Transaction is too old to void. Process a refund instead.'],
                ],
            ];
        }

        if ($billingCycle->insurance_claim_submitted_at !== null) {
            return [
                'success' => false,
                'message' => 'Cannot void after an insurance claim has been submitted.',
                'errors' => [
                    'void' => ['Insurance claim already submitted. Process a refund instead.'],
                ],
            ];
        }

        return ['success' => true];
    }

    /**
     * Line item refundable if still active.
     */
    private function isRefundableLineItem(InvoiceLineItem $lineItem): bool
    {
        $quantity = round((float) ($lineItem->quantity ?? 0), 2);
        $status = strtolower((string) ($lineItem->line_item_status ?? ''));

        return $quantity > 0 && !in_array($status, ['adjusted', 'written_off', 'denied'], true);
    }

    /**
     * Build a persisted line-item adjustment plan.
     *
     * This uses the billing system's real persisted model:
     * - quantity reduced
     * - line_total_amount reduced
     * - discount stays cycle-owned
     */
    private function buildRefundPlanForLineItem(
        InvoiceLineItem $lineItem,
        ?float $requestedRefundAmount = null,
        ?float $requestedRefundQuantity = null,
        ?int $matchedReferenceId = null,
        ?string $matchedReferenceType = null
    ): array {
        $unitPrice = round((float) ($lineItem->unit_price_at_time ?? 0), 2);
        $currentQuantity = round((float) ($lineItem->quantity ?? 0), 2);
        $currentSubtotal = round((float) ($lineItem->line_total_amount ?? 0), 2);

        if ($currentQuantity <= 0) {
            throw new RuntimeException("Line item {$lineItem->id} has no remaining quantity to refund.");
        }

        if ($unitPrice <= 0) {
            throw new RuntimeException("Line item {$lineItem->id} has invalid unit price and cannot be refunded.");
        }

        if ($requestedRefundAmount === null && $requestedRefundQuantity === null) {
            $refundQuantity = $currentQuantity;
            $refundSubtotal = $currentSubtotal;
        } elseif ($requestedRefundAmount !== null && $requestedRefundQuantity === null) {
            $requestedRefundAmount = round($requestedRefundAmount, 2);

            if ($requestedRefundAmount <= 0) {
                throw new RuntimeException("Refund amount for line item {$lineItem->id} must be greater than zero.");
            }

            if ($requestedRefundAmount - $currentSubtotal > 0.01) {
                throw new RuntimeException("Refund amount for line item {$lineItem->id} exceeds billed amount.");
            }

            $refundQuantity = round($requestedRefundAmount / $unitPrice, 2);
            $reconstructedAmount = round($refundQuantity * $unitPrice, 2);

            if (abs($reconstructedAmount - $requestedRefundAmount) > 0.01) {
                throw new RuntimeException(
                    "Refund amount {$requestedRefundAmount} for line item {$lineItem->id} cannot be represented by the persisted quantity/unit-price model."
                );
            }

            if ($refundQuantity - $currentQuantity > 0.01) {
                throw new RuntimeException("Refund quantity derived for line item {$lineItem->id} exceeds billed quantity.");
            }

            $refundSubtotal = $reconstructedAmount;
        } elseif ($requestedRefundAmount === null && $requestedRefundQuantity !== null) {
            $requestedRefundQuantity = round($requestedRefundQuantity, 2);

            if ($requestedRefundQuantity <= 0) {
                throw new RuntimeException("Refund quantity for line item {$lineItem->id} must be greater than zero.");
            }

            if ($requestedRefundQuantity - $currentQuantity > 0.01) {
                throw new RuntimeException("Refund quantity for line item {$lineItem->id} exceeds billed quantity.");
            }

            $refundQuantity = $requestedRefundQuantity;
            $refundSubtotal = round($refundQuantity * $unitPrice, 2);
        } else {
            $requestedRefundAmount = round((float) $requestedRefundAmount, 2);
            $requestedRefundQuantity = round((float) $requestedRefundQuantity, 2);

            if ($requestedRefundAmount <= 0 || $requestedRefundQuantity <= 0) {
                throw new RuntimeException('Refund amount and quantity must both be greater than zero.');
            }

            if ($requestedRefundQuantity - $currentQuantity > 0.01) {
                throw new RuntimeException("Refund quantity for line item {$lineItem->id} exceeds billed quantity.");
            }

            $expectedAmount = round($requestedRefundQuantity * $unitPrice, 2);

            if (abs($expectedAmount - $requestedRefundAmount) > 0.01) {
                throw new RuntimeException(
                    "Refund amount and quantity for line item {$lineItem->id} do not match persisted unit price."
                );
            }

            if ($requestedRefundAmount - $currentSubtotal > 0.01) {
                throw new RuntimeException("Refund amount for line item {$lineItem->id} exceeds billed amount.");
            }

            $refundQuantity = $requestedRefundQuantity;
            $refundSubtotal = $requestedRefundAmount;
        }

        $remainingQuantity = round(max(0, $currentQuantity - $refundQuantity), 2);
        $remainingSubtotal = round(max(0, $currentSubtotal - $refundSubtotal), 2);

        return [
            'line_item_id' => (int) $lineItem->id,
            'line_item_uuid' => $lineItem->line_item_uuid,
            'matched_reference_id' => $matchedReferenceId,
            'matched_reference_type' => $matchedReferenceType,
            'service_code' => $lineItem->service_code,
            'service_description' => $lineItem->service_description,
            'inventory_item_id' => $lineItem->inventory_item_id,
            'unit_price' => $unitPrice,
            'original_quantity' => $currentQuantity,
            'refund_quantity' => $refundQuantity,
            'remaining_quantity' => $remainingQuantity,
            'original_subtotal' => $currentSubtotal,
            'refund_subtotal' => $refundSubtotal,
            'remaining_subtotal' => $remainingSubtotal,
        ];
    }

    /**
     * Apply line item adjustment.
     */
    private function applyRefundPlanToLineItem(
        InvoiceLineItem $lineItem,
        array $plan,
        string $reason,
        int $staffId
    ): void {
        $metadata = $this->decodeJsonishToArray($lineItem->metadata ?? null);
        $metadata['adjustment_history'] = is_array($metadata['adjustment_history'] ?? null)
            ? $metadata['adjustment_history']
            : [];

        $action = $plan['remaining_quantity'] <= 0 ? 'remove' : 'decrease';

        $metadata['adjustment_history'][] = [
            'adjusted_at' => now()->toIso8601String(),
            'adjusted_by_staff_id' => $staffId,
            'action' => $action,
            'reason' => "Refund — {$reason}",
            'old_quantity' => $plan['original_quantity'],
            'new_quantity' => $plan['remaining_quantity'],
            'delta_quantity' => round($plan['remaining_quantity'] - $plan['original_quantity'], 2),
            'unit_price' => $plan['unit_price'],
            'refund_subtotal' => $plan['refund_subtotal'],
            'refund_quantity' => $plan['refund_quantity'],
            'matched_reference_id' => $plan['matched_reference_id'] ?? null,
            'matched_reference_type' => $plan['matched_reference_type'] ?? null,
            'refund_processed' => true,
        ];

        $metadata['last_adjusted_at'] = now()->toIso8601String();
        $metadata['last_adjusted_by_staff_id'] = $staffId;
        $metadata['last_adjustment_action'] = $action;
        $metadata['last_adjustment_reason'] = "Refund — {$reason}";
        $metadata['discount_scope'] = 'billing_cycle';

        $lineItem->quantity = $plan['remaining_quantity'];
        $lineItem->line_total_amount = $plan['remaining_subtotal'];
        $lineItem->discount_amount = 0.00;
        $lineItem->applied_discount_percentage = 0.00;
        $lineItem->adjustment_amount = $plan['refund_subtotal'];
        $lineItem->adjustment_tax_amount = null;
        $lineItem->adjustment_total_amount = $plan['refund_subtotal'];
        $lineItem->adjustment_reason = "Refund — {$reason}";
        $lineItem->net_amount = $plan['remaining_subtotal'];
        $lineItem->line_item_status = $plan['remaining_quantity'] <= 0 ? 'adjusted' : 'pending';
        $lineItem->staff_performed_id = $staffId;
        $lineItem->service_performed_at = now();
        $lineItem->audit_trail_hash = hash('sha256', json_encode([
            'line_item_id' => $lineItem->id,
            'service_code' => $lineItem->service_code,
            'quantity' => $lineItem->quantity,
            'unit_price' => $lineItem->unit_price_at_time,
            'action' => 'refund_adjustment',
            'timestamp' => now()->toIso8601String(),
        ]));
        $lineItem->metadata = json_encode($metadata);
        $lineItem->save();
    }

    /**
     * Split refund between patient and insurance.
     */
    private function splitRefundAcrossPayers(
        float $cashRefundAmount,
        float $patientPaid,
        float $insurancePaid
    ): array {
        $totalPaid = round($patientPaid + $insurancePaid, 2);

        if ($cashRefundAmount <= 0 || $totalPaid <= 0) {
            return [0.00, 0.00];
        }

        $patientRatio = $patientPaid > 0 ? $patientPaid / $totalPaid : 0;
        $patientRefund = round(min($patientPaid, $cashRefundAmount * $patientRatio), 2);
        $insuranceRefund = round($cashRefundAmount - $patientRefund, 2);

        if ($insuranceRefund > $insurancePaid) {
            $overflow = round($insuranceRefund - $insurancePaid, 2);
            $insuranceRefund = round($insurancePaid, 2);
            $patientRefund = round(min($patientPaid, $patientRefund + $overflow), 2);
        }

        if ($patientRefund > $patientPaid) {
            $overflow = round($patientRefund - $patientPaid, 2);
            $patientRefund = round($patientPaid, 2);
            $insuranceRefund = round(min($insurancePaid, $insuranceRefund + $overflow), 2);
        }

        $distributed = round($patientRefund + $insuranceRefund, 2);

        if (abs($distributed - $cashRefundAmount) > 0.01) {
            $delta = round($cashRefundAmount - $distributed, 2);

            if (($insurancePaid - $insuranceRefund) >= $delta) {
                $insuranceRefund = round($insuranceRefund + $delta, 2);
            } else {
                $patientRefund = round($patientRefund + $delta, 2);
            }
        }

        return [
            round($patientRefund, 2),
            round($insuranceRefund, 2),
        ];
    }

    /**
     * Normalize refund methods.
     */
    private function normalizeRefundMethods(array $methods): array
    {
        return collect($methods)
            ->map(function ($method) {
                return [
                    'type' => trim((string) ($method['type'] ?? '')),
                    'amount' => round(max(0, (float) ($method['amount'] ?? 0)), 2),
                    'reference' => isset($method['reference']) ? trim((string) $method['reference']) : null,
                ];
            })
            ->filter(fn (array $method) => $method['type'] !== '' && $method['amount'] > 0)
            ->values()
            ->all();
    }

    /**
     * Determine final billing status.
     */
    private function resolveRefundedBillingStatus(float $finalGrandTotal, string $refundType): string
    {
        if ($refundType === 'full_refund' || $finalGrandTotal <= 0.01) {
            return 'fully_refunded';
        }

        return 'partially_refunded';
    }

    /**
     * Determine visit payment status from final cycle state.
     */
    private function resolveVisitPaymentStatusFromCycleState(
        float $grandTotal,
        float $totalPaid,
        float $balance
    ): string {
        if ($grandTotal <= 0.01) {
            return 'not_billed';
        }

        if ($balance <= 0.01) {
            return 'paid_in_full';
        }

        if ($totalPaid > 0.01) {
            return 'partially_paid';
        }

        return 'pending';
    }

    /**
     * Sync active line item statuses after refund.
     */
    private function syncRefundedCycleLineItemStatuses(BillingCycle $billingCycle, int $staffId): void
    {
        $isSettled = round((float) ($billingCycle->balance_amount ?? 0), 2) <= 0.01
            && round((float) ($billingCycle->grand_total_amount ?? 0), 2) > 0.00;

        InvoiceLineItem::query()
            ->where('billing_cycle_id', $billingCycle->id)
            ->whereNotIn('line_item_status', ['adjusted', 'written_off', 'denied'])
            ->update([
                'line_item_status' => $isSettled ? 'paid' : 'pending',
                'updated_at' => now(),
            ]);

        BillingCycle::query()
            ->where('id', $billingCycle->id)
            ->update([
                'updated_by_staff_id' => $staffId,
                'updated_at' => now(),
            ]);
    }

    /**
     * Build adjustment documentation for affected lines.
     */
    private function buildAffectedLineItemDocumentation(array $plans): array
    {
        return array_values(array_map(function (array $plan) {
            return [
                'line_item_id' => $plan['line_item_id'],
                'line_item_uuid' => $plan['line_item_uuid'],
                'matched_reference_id' => $plan['matched_reference_id'] ?? null,
                'matched_reference_type' => $plan['matched_reference_type'] ?? null,
                'service_code' => $plan['service_code'],
                'service_description' => $plan['service_description'],
                'original_quantity' => $plan['original_quantity'],
                'refund_quantity' => $plan['refund_quantity'],
                'remaining_quantity' => $plan['remaining_quantity'],
                'original_subtotal' => $plan['original_subtotal'],
                'refund_subtotal' => $plan['refund_subtotal'],
                'remaining_subtotal' => $plan['remaining_subtotal'],
            ];
        }, $plans));
    }

    /**
     * Build a projection cycle so determineBillingState sees post-refund payments.
     */
    private function buildPaymentProjectionCycle(
        BillingCycle $billingCycle,
        float $patientPaymentReceived,
        float $insurancePaymentReceived
    ): BillingCycle {
        $projected = clone $billingCycle;
        $projected->patient_payment_received = $patientPaymentReceived;
        $projected->insurance_payment_received = $insurancePaymentReceived;

        return $projected;
    }

    /**
     * Extract cycle-level discount rule from metadata/current cycle.
     */
    private function extractDiscountRuleFromCycle(BillingCycle $billingCycle): array
    {
        $metadata = $this->decodeJsonishToArray($billingCycle->metadata ?? null);
        $rule = is_array($metadata['discount_rule'] ?? null) ? $metadata['discount_rule'] : [];

        return $this->normalizeDiscount([
            'type' => $rule['type'] ?? 'fixed',
            'value' => $rule['value'] ?? (float) ($billingCycle->discount_applied ?? 0),
            'reason' => $rule['reason'] ?? $billingCycle->discount_reason,
        ]);
    }

    /**
     * Extract cycle-level tax definitions from metadata/current cycle.
     */
    private function extractTaxDefinitionsFromCycle(BillingCycle $billingCycle): array
    {
        $metadata = $this->decodeJsonishToArray($billingCycle->metadata ?? null);
        $taxDefinitions = $metadata['tax_definitions'] ?? null;

        if (is_array($taxDefinitions) && !empty($taxDefinitions)) {
            return $this->normalizeTaxDefinitions($taxDefinitions);
        }

        return $this->normalizeTaxDefinitions(
            $this->decodeJsonishToArray($billingCycle->tax_details ?? null)
        );
    }

    /**
     * Create financial adjustment.
     */
    private function createFinancialAdjustment(array $data): FinancialAdjustment
    {
        do {
            $referenceNumber = 'REF-' . strtoupper(Str::random(8));
        } while (FinancialAdjustment::query()->where('reference_number', $referenceNumber)->exists());

        return FinancialAdjustment::query()->create(array_merge($data, [
            'adjustment_uuid' => (string) Str::uuid(),
            'reference_number' => $referenceNumber,
            'status' => 'processing',
        ]));
    }

    /**
     * Complete adjustment.
     */
    private function completeAdjustment(FinancialAdjustment $adjustment, int $staffId): void
    {
        $adjustment->status = 'completed';
        $adjustment->completed_at = now();
        $adjustment->approved_by_staff_id = $staffId;
        $adjustment->approved_at = now();
        $adjustment->save();
    }

    /**
     * Build immutable billing snapshot for audit.
     */
    private function createBillingSnapshot(BillingCycle $billingCycle): array
    {
        $lineItems = $billingCycle->relationLoaded('lineItems')
            ? $billingCycle->lineItems
            : $billingCycle->lineItems()->get();

        return [
            'id' => $billingCycle->id,
            'billing_cycle_uuid' => $billingCycle->billing_cycle_uuid,
            'facility_id' => $billingCycle->facility_id,
            'visit_id' => $billingCycle->visit_id,
            'patient_id' => $billingCycle->patient_id,
            'cycle_type' => $billingCycle->cycle_type,
            'period_start' => optional($billingCycle->period_start)->toIso8601String(),
            'period_end' => optional($billingCycle->period_end)->toIso8601String(),
            'days_in_cycle' => $billingCycle->days_in_cycle,
            'subtotal_amount' => $billingCycle->subtotal_amount,
            'total_amount_charged' => $billingCycle->total_amount_charged,
            'total_adjustments' => $billingCycle->total_adjustments,
            'net_amount' => $billingCycle->net_amount,
            'grand_total_amount' => $billingCycle->grand_total_amount,
            'primary_insurance_claim_number' => $billingCycle->primary_insurance_claim_number,
            'insurance_covered_amount' => $billingCycle->insurance_covered_amount,
            'insurance_adjustment_amount' => $billingCycle->insurance_adjustment_amount,
            'insurance_payment_received' => $billingCycle->insurance_payment_received,
            'insurance_claim_submitted_at' => optional($billingCycle->insurance_claim_submitted_at)->toIso8601String(),
            'insurance_payment_received_at' => optional($billingCycle->insurance_payment_received_at)->toIso8601String(),
            'patient_responsibility_amount' => $billingCycle->patient_responsibility_amount,
            'patient_copay_amount' => $billingCycle->patient_copay_amount,
            'patient_deductible_amount' => $billingCycle->patient_deductible_amount,
            'patient_coinsurance_amount' => $billingCycle->patient_coinsurance_amount,
            'patient_payment_received' => $billingCycle->patient_payment_received,
            'total_paid_amount' => $billingCycle->total_paid_amount,
            'balance_amount' => $billingCycle->balance_amount,
            'discount_applied' => $billingCycle->discount_applied,
            'taxable_amount' => $billingCycle->taxable_amount,
            'discount_reason' => $billingCycle->discount_reason,
            'contractual_adjustment' => $billingCycle->contractual_adjustment,
            'charity_care_adjustment' => $billingCycle->charity_care_adjustment,
            'bad_debt_adjustment' => $billingCycle->bad_debt_adjustment,
            'tax_details' => $billingCycle->tax_details,
            'total_tax_amount' => $billingCycle->total_tax_amount,
            'billing_status' => $billingCycle->billing_status,
            'billed_at' => optional($billingCycle->billed_at)->toIso8601String(),
            'payment_due_date' => optional($billingCycle->payment_due_date)->toIso8601String(),
            'days_outstanding' => $billingCycle->days_outstanding,
            'statement_count' => $billingCycle->statement_count,
            'last_statement_sent_at' => optional($billingCycle->last_statement_sent_at)->toIso8601String(),
            'sent_to_collections_at' => optional($billingCycle->sent_to_collections_at)->toIso8601String(),
            'collections_agency' => $billingCycle->collections_agency,
            'is_disputed' => $billingCycle->is_disputed,
            'dispute_reason' => $billingCycle->dispute_reason,
            'dispute_opened_at' => optional($billingCycle->dispute_opened_at)->toIso8601String(),
            'dispute_resolved_at' => optional($billingCycle->dispute_resolved_at)->toIso8601String(),
            'created_by_staff_id' => $billingCycle->created_by_staff_id,
            'updated_by_staff_id' => $billingCycle->updated_by_staff_id,
            'created_at' => optional($billingCycle->created_at)->toIso8601String(),
            'updated_at' => optional($billingCycle->updated_at)->toIso8601String(),
            'deleted_at' => optional($billingCycle->deleted_at)->toIso8601String(),
            'metadata' => $billingCycle->metadata,
            'line_items' => $lineItems->map(function (InvoiceLineItem $item) {
                return [
                    'id' => $item->id,
                    'line_item_uuid' => $item->line_item_uuid,
                    'service_code' => $item->service_code,
                    'service_name' => $item->service_description,
                    'service_catalog_id' => $item->service_catalog_id,
                    'inventory_item_id' => $item->inventory_item_id,
                    'quantity' => $item->quantity,
                    'unit_of_measure' => $item->unit_of_measure,
                    'unit_price' => $item->unit_price_at_time,
                    'line_total' => $item->line_total_amount,
                    'discount_amount' => $item->discount_amount,
                    'adjustment_amount' => $item->adjustment_amount,
                    'adjustment_tax_amount' => $item->adjustment_tax_amount,
                    'adjustment_total_amount' => $item->adjustment_total_amount,
                    'adjustment_reason' => $item->adjustment_reason,
                    'net_amount' => $item->net_amount,
                    'line_item_status' => $item->line_item_status,
                ];
            })->values()->all(),
            'snapshot_taken_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Restore inventory only for refunded quantities.
     */
    private function restoreInventoryForRefundedLineItems(
        array $plans,
        int $staffId,
        string $referenceNumber
    ): array {
        $restored = [];

        foreach ($plans as $plan) {
            $unitsToRestore = (int) round((float) ($plan['refund_quantity'] ?? 0));

            if ($unitsToRestore <= 0) {
                continue;
            }

            try {
                $inventoryItem = null;

                if (!empty($plan['inventory_item_id'])) {
                    $inventoryItem = InventoryItem::query()
                        ->where('id', $plan['inventory_item_id'])
                        ->where('status', 'active')
                        ->lockForUpdate()
                        ->first();
                }

                if (!$inventoryItem && !empty($plan['service_code'])) {
                    $inventoryItem = InventoryItem::query()
                        ->where('item_code', $plan['service_code'])
                        ->where('status', 'active')
                        ->lockForUpdate()
                        ->first();
                }

                if (!$inventoryItem) {
                    continue;
                }

                $previousQuantity = (int) $inventoryItem->package_quantity;
                $newQuantity = $previousQuantity + $unitsToRestore;

                $metadata = $this->decodeJsonishToArray($inventoryItem->metadata ?? null);
                $metadata['stock_restorations'] = is_array($metadata['stock_restorations'] ?? null)
                    ? $metadata['stock_restorations']
                    : [];

                $metadata['stock_restorations'][] = [
                    'restored_at' => now()->toIso8601String(),
                    'restored_by_staff_id' => $staffId,
                    'units_restored' => $unitsToRestore,
                    'previous_quantity' => $previousQuantity,
                    'new_quantity' => $newQuantity,
                    'reference_number' => $referenceNumber,
                    'reason' => 'refund_line_item_adjustment',
                    'line_item_id' => $plan['line_item_id'] ?? null,
                    'matched_reference_id' => $plan['matched_reference_id'] ?? null,
                    'matched_reference_type' => $plan['matched_reference_type'] ?? null,
                ];

                $inventoryItem->package_quantity = $newQuantity;
                $inventoryItem->updated_by_staff_id = $staffId;
                $inventoryItem->metadata = $metadata;
                $inventoryItem->save();

                $restored[] = [
                    'inventory_item_id' => $inventoryItem->id,
                    'item_code' => $inventoryItem->item_code,
                    'quantity_restored' => $unitsToRestore,
                    'new_quantity' => $newQuantity,
                    'line_item_id' => $plan['line_item_id'] ?? null,
                    'matched_reference_id' => $plan['matched_reference_id'] ?? null,
                ];
            } catch (Throwable $e) {
                Log::error('Inventory restoration failed during refund', [
                    'line_item_id' => $plan['line_item_id'] ?? null,
                    'matched_reference_id' => $plan['matched_reference_id'] ?? null,
                    'reference_number' => $referenceNumber,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $restored;
    }

    /**
     * Update visit after refund.
     */
    private function updateVisitAfterRefund(
        Visit $visit,
        BillingCycle $billingCycle,
        FinancialAdjustment $adjustment,
        int $staffId
    ): void {
        $grandTotal = round((float) ($billingCycle->grand_total_amount ?? 0), 2);
        $totalPaid = round((float) ($billingCycle->total_paid_amount ?? 0), 2);
        $balance = round((float) ($billingCycle->balance_amount ?? 0), 2);

        $visit->payment_status = $this->resolveVisitPaymentStatusFromCycleState(
            $grandTotal,
            $totalPaid,
            $balance
        );

        $visit->estimated_total_charges = $grandTotal <= 0.01 ? 0.00 : $grandTotal;
        $visit->patient_estimated_responsibility = $balance <= 0.01 ? 0.00 : $balance;
        $visit->updated_by_staff_id = $staffId;

        $metadata = $this->decodeJsonishToArray($visit->metadata ?? null);
        $metadata['refunds'] = is_array($metadata['refunds'] ?? null) ? $metadata['refunds'] : [];

        $metadata['refunds'][] = [
            'adjustment_id' => $adjustment->id,
            'reference_number' => $adjustment->reference_number,
            'adjustment_type' => $adjustment->adjustment_type,
            'refund_amount' => $adjustment->adjustment_amount,
            'billing_cycle_id' => $billingCycle->id,
            'billing_cycle_status' => $billingCycle->billing_status,
            'processed_at' => now()->toIso8601String(),
            'processed_by_staff_id' => $staffId,
        ];

        $metadata['latest_billing'] = array_merge(
            is_array($metadata['latest_billing'] ?? null) ? $metadata['latest_billing'] : [],
            [
                'billing_cycle_id' => $billingCycle->id,
                'billing_cycle_uuid' => $billingCycle->billing_cycle_uuid,
                'receipt_number' => "REC-{$billingCycle->id}",
                'saved_at' => now()->toIso8601String(),
                'grand_total' => $grandTotal,
                'total_paid' => $totalPaid,
                'balance' => $balance,
                'payment_status' => $visit->payment_status,
                'billing_status' => $billingCycle->billing_status,
            ]
        );

        $visit->metadata = $metadata;
        $visit->save();
    }

    /**
     * Update visit after void.
     */
    private function updateVisitAfterVoid(
        Visit $visit,
        BillingCycle $billingCycle,
        FinancialAdjustment $adjustment,
        int $staffId
    ): void {
        $visit->payment_status = 'not_billed';
        $visit->estimated_total_charges = 0.00;
        $visit->patient_estimated_responsibility = 0.00;
        $visit->updated_by_staff_id = $staffId;

        $metadata = $this->decodeJsonishToArray($visit->metadata ?? null);

        if (isset($metadata['billing']) && is_array($metadata['billing'])) {
            $metadata['billing'] = array_values(array_filter(
                $metadata['billing'],
                fn ($entry) => (int) ($entry['billing_cycle_id'] ?? 0) !== (int) $billingCycle->id
            ));
        }

        $metadata['voided_transactions'] = is_array($metadata['voided_transactions'] ?? null)
            ? $metadata['voided_transactions']
            : [];

        $metadata['voided_transactions'][] = [
            'billing_cycle_id' => $billingCycle->id,
            'adjustment_id' => $adjustment->id,
            'reference_number' => $adjustment->reference_number,
            'voided_at' => now()->toIso8601String(),
            'voided_by_staff_id' => $staffId,
        ];

        $visit->metadata = $metadata;
        $visit->save();
    }

    /**
     * Merge metadata safely.
     */
    private function mergeMetadata(mixed $existing, array $additions): array
    {
        $base = is_array($existing)
            ? $existing
            : json_decode($existing ?? '{}', true);

        if (!is_array($base)) {
            $base = [];
        }

        return array_merge($base, $additions);
    }

    /**
     * Standard not-found helper.
     */
    private function notFound(string $message, string $errorKey): array
    {
        return [
            'success' => false,
            'message' => $message,
            'errors' => [
                $errorKey => ["Invalid or inaccessible {$errorKey}."],
            ],
        ];
    }
}
