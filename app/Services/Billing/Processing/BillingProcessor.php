<?php

namespace App\Services\Billing\Processing;

use App\Models\BillingCycle;
use App\Models\InvoiceLineItem;
use App\Models\InventoryItem;
use App\Models\Visit;
use App\Support\HealthcareIdGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;
use RuntimeException;


/**
 * Billing Processor Service
 *
 * Handles core processing logic including transactions, inventory deduction,
 * and creation of billing records
 */
class BillingProcessor
{
    public function processBillingTransaction(
    array $data,
    int $facilityId,
    int $staffId,
    float $discountAmount,
    array $paymentSplit,
    bool $isPrimaryCash,
    bool $isInsuranceInvolved,
    Visit $visit,
    ?BillingCycle $existingBillingCycle = null
): array {
    return DB::transaction(function () use (
        $data,
        $facilityId,
        $staffId,
        $discountAmount,
        $paymentSplit,
        $isPrimaryCash,
        $isInsuranceInvolved,
        $visit,
        $existingBillingCycle
    ) {
        // 1. Create or update the billing cycle
        $billingCycle = $this->createOrUpdateBillingCycle(
            $data,
            $facilityId,
            $staffId,
            $discountAmount,
            $paymentSplit,
            $isPrimaryCash,
            $isInsuranceInvolved,
            $existingBillingCycle
        );

        // 2. Create or update invoice line items by ADDING values
        $lineItems = $this->createOrUpdateLineItems(
            $data,
            $billingCycle->id,
            $staffId,
            $discountAmount,
            $existingBillingCycle
        );

        // 3. Reduce stock only for newly submitted quantities
        $this->deductInventoryStock($data['charge_items'], $staffId);

        // 4. Ensure all line-item statuses follow the final billing status
        $this->syncCycleLineItemStatuses(
            $billingCycle->id,
            $billingCycle->billing_status === 'paid_in_full'
        );

        // 5. Update visit based on FULL cycle totals, not just current payload
        $this->updateVisitBillingStatus(
            $visit,
            $data,
            $billingCycle,
            $staffId,
            $paymentSplit,
            $existingBillingCycle !== null
        );

        return [
            'billing_cycle' => $billingCycle->fresh(),
            'line_items' => $lineItems,
        ];
    });
}


        /**
         * Create BillingCycle record
         * 
         * FIXED: Use paymentSplit['total_paid'] instead of data['billing_data']['totalPaid']
         * to ensure consistency with actual payment methods
         *
         * @param array $data Billing data
         * @param int $facilityId Facility ID
         * @param int $staffId Staff ID
         * @param float $discountAmount Calculated discount amount
         * @param array $paymentSplit Payment split data (with total_paid)
         * @param bool $isPrimaryCash Is primary payment cash
         * @param bool $isInsuranceInvolved Is insurance involved
         * @return BillingCycle
         */
       protected function createOrUpdateBillingCycle(
            array $data,
            int $facilityId,
            int $staffId,
            float $discountAmount,
            array $paymentSplit,
            bool $isPrimaryCash,
            bool $isInsuranceInvolved,
            ?BillingCycle $existingBillingCycle = null
        ): BillingCycle {
            $subtotalAmount = (float) ($data['billing_data']['subtotal'] ?? 0);
            $taxableAmount = (float) ($data['billing_data']['taxableAmount'] ?? max(0, $subtotalAmount - $discountAmount));
            $taxTotal = (float) ($data['billing_data']['taxTotal'] ?? 0);
            $grandTotal = (float) ($data['billing_data']['grandTotal'] ?? 0);

            $incomingTotalPaid = (float) (
                $data['resolved_total_paid']
                ?? ($paymentSplit['total_paid'] ?? ($paymentSplit['insurance_payment'] + $paymentSplit['patient_payment']))
            );

            if ($existingBillingCycle) {
                $existingMetadata = $this->decodeJsonArray($existingBillingCycle->metadata ?? null);
                $existingTaxes = $this->decodeJsonArray($existingBillingCycle->tax_details ?? null);

                $mergedPaymentMethods = array_values(array_merge(
                    $existingMetadata['payment_methods'] ?? [],
                    $data['payment_methods'] ?? []
                ));

                $mergedTaxes = $this->mergeTaxDetails($existingTaxes, $data['taxes'] ?? []);

                $newSubtotal = round((float) $existingBillingCycle->subtotal_amount + $subtotalAmount, 2);
                $newDiscount = round((float) $existingBillingCycle->discount_applied + $discountAmount, 2);
                $newTaxableAmount = round((float) $existingBillingCycle->taxable_amount + $taxableAmount, 2);
                $newTaxTotal = round((float) $existingBillingCycle->total_tax_amount + $taxTotal, 2);
                $newGrandTotal = round((float) $existingBillingCycle->grand_total_amount + $grandTotal, 2);

                $newInsurancePayment = round((float) $existingBillingCycle->insurance_payment_received + (float) ($paymentSplit['insurance_payment'] ?? 0), 2);
                $newPatientPayment = round((float) $existingBillingCycle->patient_payment_received + (float) ($paymentSplit['patient_payment'] ?? 0), 2);
                $newTotalPaid = round((float) $existingBillingCycle->total_paid_amount + $incomingTotalPaid, 2);

                $newBalance = round(max(0, $newGrandTotal - $newTotalPaid), 2);
                $isFullyPaid = abs($newBalance) < 0.01;

                $newBillingStatus = $isFullyPaid
                    ? 'paid_in_full'
                    : ($newTotalPaid > 0 ? 'partially_paid' : 'pending');

                $existingBillingCycle->fill([
                    'subtotal_amount' => $newSubtotal,
                    'total_amount_charged' => $newSubtotal,
                    'total_adjustments' => $newDiscount,
                    'taxable_amount' => $newTaxableAmount,
                    'net_amount' => $newGrandTotal,
                    'grand_total_amount' => $newGrandTotal,

                    'insurance_covered_amount' => $newInsurancePayment,
                    'insurance_payment_received' => $newInsurancePayment,
                    'patient_responsibility_amount' => max(0, $newGrandTotal - $newInsurancePayment),
                    'patient_payment_received' => $newPatientPayment,
                    'total_paid_amount' => $newTotalPaid,
                    'balance_amount' => $newBalance,

                    'discount_applied' => $newDiscount,
                    'discount_reason' => $data['discount']['reason'] ?? $existingBillingCycle->discount_reason,

                    'tax_details' => json_encode($mergedTaxes),
                    'total_tax_amount' => $newTaxTotal,

                    'billing_status' => $newBillingStatus,
                    'payment_due_date' => !$isFullyPaid
                        ? ($existingBillingCycle->payment_due_date ?? now()->addDays(30))
                        : null,

                    'updated_by_staff_id' => $staffId,
                ]);

                if ($isInsuranceInvolved && !$existingBillingCycle->insurance_claim_submitted_at) {
                    $existingBillingCycle->insurance_claim_submitted_at = now();
                }

                if ($newInsurancePayment > 0) {
                    $existingBillingCycle->insurance_payment_received_at =
                        $existingBillingCycle->insurance_payment_received_at ?? now();
                }

                $existingBillingCycle->metadata = json_encode(array_merge($existingMetadata, [
                    'payment_methods' => $mergedPaymentMethods,
                    'additional_notes' => $this->mergeAdditionalNotes(
                        $existingMetadata['additional_notes'] ?? null,
                        $data['additional_notes'] ?? null
                    ),
                    'is_cash_payment' => $isPrimaryCash,
                    'validated_total_paid' => $newTotalPaid,
                    'balance_amount' => $newBalance,
                    'resolved_billing_status' => $newBillingStatus,
                    'resolved_payment_status' => $newBillingStatus,
                    'frontend_ui_status' => $data['status'] ?? null,
                    'is_fully_paid' => $isFullyPaid,
                    'last_appended_at' => now()->toIso8601String(),
                    'last_appended_by_staff_id' => $staffId,
                ]));

                $existingBillingCycle->save();

                return $existingBillingCycle->fresh();
            }

            $validatedTotalPaid = $incomingTotalPaid;
            $balanceAmount = round(max(0, $grandTotal - $validatedTotalPaid), 2);
            $isFullyPaid = abs($balanceAmount) < 0.01;

            $billingStatus = $isFullyPaid
                ? 'paid_in_full'
                : ($validatedTotalPaid > 0 ? 'partially_paid' : 'pending');

            return BillingCycle::create([
                'billing_cycle_uuid' => HealthcareIdGenerator::generate('billing'),
                'facility_id' => $facilityId,
                'visit_id' => $data['visit_id'],
                'patient_id' => $data['patient_id'],

                'cycle_type' => 'visit_based',
                'period_start' => now(),
                'period_end' => now(),
                'days_in_cycle' => 1,

                'subtotal_amount' => $subtotalAmount,
                'total_amount_charged' => $subtotalAmount,
                'total_adjustments' => $discountAmount,
                'taxable_amount' => $taxableAmount,
                'net_amount' => $grandTotal,
                'grand_total_amount' => $grandTotal,

                'insurance_covered_amount' => $paymentSplit['insurance_payment'],
                'insurance_payment_received' => $paymentSplit['insurance_payment'],
                'insurance_claim_submitted_at' => $isInsuranceInvolved ? now() : null,
                'insurance_payment_received_at' => ($paymentSplit['insurance_payment'] ?? 0) > 0 ? now() : null,

                'patient_responsibility_amount' => max(0, $grandTotal - $paymentSplit['insurance_payment']),
                'patient_payment_received' => $paymentSplit['patient_payment'],
                'total_paid_amount' => $validatedTotalPaid,
                'balance_amount' => $balanceAmount,

                'discount_applied' => $discountAmount,
                'discount_reason' => $data['discount']['reason'] ?? null,

                'tax_details' => json_encode($data['taxes'] ?? []),
                'total_tax_amount' => $taxTotal,

                'billing_status' => $billingStatus,
                'billed_at' => now(),
                'payment_due_date' => !$isFullyPaid ? now()->addDays(30) : null,

                'created_by_staff_id' => $staffId,
                'updated_by_staff_id' => $staffId,
                'metadata' => json_encode([
                    'payment_methods' => $data['payment_methods'] ?? [],
                    'additional_notes' => $data['additional_notes'] ?? null,
                    'is_cash_payment' => $isPrimaryCash,
                    'validated_total_paid' => $validatedTotalPaid,
                    'balance_amount' => $balanceAmount,
                    'resolved_billing_status' => $billingStatus,
                    'resolved_payment_status' => $data['payment_status'] ?? null,
                    'frontend_ui_status' => $data['status'] ?? null,
                    'is_fully_paid' => $isFullyPaid,
                ]),
            ]);
        }


            /**
         * Create InvoiceLineItem records
         *
         * @param array $data Billing data
         * @param int $billingCycleId Billing cycle ID
         * @param int $staffId Staff ID
         * @param float $discountAmount Total discount amount
         * @return array Created line items
         */ 
        protected function createOrUpdateLineItems(
            array $data,
            int $billingCycleId,
            int $staffId,
            float $discountAmount,
            ?BillingCycle $existingBillingCycle = null
        ): array {
            $lineItems = [];

            $serviceCodes = array_map(function ($chargeItem) {
                return $chargeItem['service']['code'];
            }, $data['charge_items']);

            $inventoryItems = \App\Models\InventoryItem::whereIn('item_code', $serviceCodes)
                ->get()
                ->keyBy('item_code');

            $serviceCatalogs = \App\Models\ServiceCatalog::whereIn('service_code', $serviceCodes)
                ->get()
                ->keyBy('service_code');

            $existingLineItemsIndex = [];

            if ($existingBillingCycle) {
                $existingLineItems = InvoiceLineItem::query()
                    ->where('billing_cycle_id', $billingCycleId)
                    ->get();

                foreach ($existingLineItems as $existingLineItem) {
                    $existingMeta = $this->decodeJsonArray($existingLineItem->metadata ?? null);
                    $indexKey = ($existingLineItem->service_code ?? '') . '|' . ($existingMeta['service_key'] ?? '');
                    $existingLineItemsIndex[$indexKey] = $existingLineItem;
                }
            }

            foreach ($data['charge_items'] as $chargeItem) {
                $service = $chargeItem['service'];
                $quantity = (float) $chargeItem['quantity'];
                $unitPrice = (float) $service['unitPrice'];
                $lineTotal = (float) $chargeItem['totalAmount'];
                $serviceCode = $service['code'];
                $serviceKey = $chargeItem['service_key'] ?? '';

                $inventoryItem = $inventoryItems->get($serviceCode);
                $serviceCatalog = $serviceCatalogs->get($serviceCode);

                $lineDiscountAmount = $this->calculateLineItemDiscount(
                    $lineTotal,
                    (float) ($data['billing_data']['subtotal'] ?? 0),
                    $discountAmount
                );

                $netAmount = round($lineTotal - $lineDiscountAmount, 2);
                $indexKey = $serviceCode . '|' . $serviceKey;

                if (isset($existingLineItemsIndex[$indexKey])) {
                    $existingLineItem = $existingLineItemsIndex[$indexKey];
                    $existingMeta = $this->decodeJsonArray($existingLineItem->metadata ?? null);

                    $existingLineItem->quantity = round((float) $existingLineItem->quantity + $quantity, 2);
                    $existingLineItem->line_total_amount = round((float) $existingLineItem->line_total_amount + $lineTotal, 2);
                    $existingLineItem->discount_amount = round((float) $existingLineItem->discount_amount + $lineDiscountAmount, 2);
                    $existingLineItem->addToNetAmount($netAmount);
                    $existingLineItem->unit_price_at_time = $unitPrice;
                    $existingLineItem->staff_performed_id = $staffId;
                    $existingLineItem->service_performed_at = now();
                    $existingLineItem->line_item_status = !empty($data['resolved_is_fully_paid']) ? 'paid' : 'pending';
                    $existingLineItem->metadata = json_encode(array_merge($existingMeta, [
                        'service_key' => $serviceKey,
                        'category' => $service['category'],
                        'source_type' => $inventoryItem ? 'inventory' : ($serviceCatalog ? 'service_catalog' : 'unknown'),
                        'last_appended_at' => now()->toIso8601String(),
                        'last_appended_by_staff_id' => $staffId,
                    ]));
                    $existingLineItem->audit_trail_hash = hash('sha256', json_encode([
                        'service_code' => $serviceCode,
                        'quantity' => $existingLineItem->quantity,
                        'unit_price' => $unitPrice,
                        'timestamp' => now()->toIso8601String(),
                    ]));
                    $existingLineItem->save();

                    $lineItems[] = $existingLineItem;
                    continue;
                }

                $lineItemData = [
                    'line_item_uuid' => Str::uuid(),
                    'billing_cycle_id' => $billingCycleId,
                    'visit_id' => $data['visit_id'],

                    'service_version_snapshot' => json_encode($service),
                    'service_code' => $serviceCode,
                    'service_description' => $service['name'],

                    'inventory_item_id' => $inventoryItem?->id,
                    'service_catalog_id' => $serviceCatalog?->id,

                    'quantity' => $quantity,
                    'unit_of_measure' => 'each',
                    'unit_price_at_time' => $unitPrice,
                    'line_total_amount' => $lineTotal,

                    'discount_amount' => $lineDiscountAmount,
                    'net_amount' => $netAmount,

                    'staff_performed_id' => $staffId,
                    'service_performed_at' => now(),
                    'line_item_status' => !empty($data['resolved_is_fully_paid']) ? 'paid' : 'pending',

                    'audit_trail_hash' => hash('sha256', json_encode([
                        'service_code' => $serviceCode,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'timestamp' => now()->toIso8601String(),
                    ])),

                    'created_by_staff_id' => $staffId,
                    'metadata' => json_encode([
                        'service_key' => $serviceKey,
                        'category' => $service['category'],
                        'source_type' => $inventoryItem ? 'inventory' : ($serviceCatalog ? 'service_catalog' : 'unknown'),
                    ]),
                ];

                $lineItem = InvoiceLineItem::create($lineItemData);
                $lineItems[] = $lineItem;
            }

            return $lineItems;
        }


    /**
     * Deduct inventory stock for billed inventory items with proper locking
     *
     * @param array $chargeItems Charge items from billing payload
     * @param int $staffId Staff performing the action
     * @return void
     * @throws \Exception
     */
    protected function deductInventoryStock(array $chargeItems, int $staffId): void
    {
        foreach ($chargeItems as $chargeItem) {
            try {
                $service = $chargeItem['service'];
                $quantity = (int) $chargeItem['quantity'];

                if ($quantity <= 0) {
                    continue;
                }

                // Only process items that have a service code (inventory items will match)
                $inventoryItem = InventoryItem::query()
                    ->where('item_code', $service['code'])
                    ->where('status', 'active')
                    ->lockForUpdate() // Prevent race conditions
                    ->first();

                if (!$inventoryItem) {
                    // It's a service (no inventory record) — skip silently
                    continue;
                }

                // Double-check stock availability (race condition safety)
                if ($inventoryItem->package_quantity < $quantity) {
                    Log::error('Race condition detected: Insufficient stock during deduction', [
                        'item_code' => $service['code'],
                        'available' => $inventoryItem->package_quantity,
                        'requested' => $quantity,
                        'staff_id' => $staffId,
                    ]);

                    throw new \Exception(
                        "Insufficient stock for item '{$service['name']}' (Code: {$service['code']}). "
                        . "Available: {$inventoryItem->package_quantity}, Requested: {$quantity}. "
                        . "This may be due to concurrent transactions. Please try again."
                    );
                }

                // Calculate new quantity
                $previousQuantity = $inventoryItem->package_quantity;
                $unitsToDeduct = $quantity;
                $newPackageQuantity = max(0, $previousQuantity - $unitsToDeduct);

                $inventoryItem->package_quantity = $newPackageQuantity;
                $inventoryItem->updated_by_staff_id = $staffId;

                // Append deduction to metadata audit trail
                $metadata = is_array($inventoryItem->metadata) 
                    ? $inventoryItem->metadata 
                    : (json_decode($inventoryItem->metadata ?? '{}', true) ?? []);

                if (!isset($metadata['stock_deductions'])) {
                    $metadata['stock_deductions'] = [];
                }

                $metadata['stock_deductions'][] = [
                    'deducted_at' => now()->toIso8601String(),
                    'deducted_by_staff_id' => $staffId,
                    'units_deducted' => $unitsToDeduct,
                    'previous_quantity' => $previousQuantity,
                    'new_quantity' => $newPackageQuantity,
                    'service_code' => $service['code'],
                    'service_name' => $service['name'] ?? 'Unknown',
                    'reason' => 'billing_finalization',
                ];

                // Track last deduction for quick reference
                $metadata['last_stock_deduction'] = [
                    'deducted_at' => now()->toIso8601String(),
                    'deducted_by_staff_id' => $staffId,
                    'units_deducted' => $unitsToDeduct,
                    'new_quantity' => $newPackageQuantity,
                ];

                $inventoryItem->metadata = $metadata;
                $inventoryItem->save();

                Log::info('Inventory stock deducted successfully', [
                    'item_code' => $service['code'],
                    'item_name' => $service['name'] ?? 'Unknown',
                    'units_deducted' => $unitsToDeduct,
                    'previous_quantity' => $previousQuantity,
                    'new_quantity' => $newPackageQuantity,
                    'status' => $inventoryItem->status,
                    'staff_id' => $staffId,
                ]);

            } catch (\Exception $e) {
                Log::error('Failed to deduct inventory stock', [
                    'item_code' => $service['code'] ?? 'unknown',
                    'item_name' => $service['name'] ?? 'unknown',
                    'error_message' => $e->getMessage(),
                    'staff_id' => $staffId,
                ]);

                throw $e; // Re-throw to trigger transaction rollback
            }
        }
    }

    /**
     * Update visit with billing information
     * 
     * FIXED: Use paymentSplit for accurate payment calculations
     *
     * @param Visit $visit
     * @param array $data
     * @param BillingCycle $billingCycle
     * @param int $staffId
     * @param array $paymentSplit
     * @return void
     */
    protected function updateVisitBillingStatus(
    Visit $visit,
    array $data,
    BillingCycle $billingCycle,
    int $staffId,
    array $paymentSplit,
    bool $wasExistingCycleUpdated = false
): void {
    // IMPORTANT:
    // Always derive visit financial state from the FULL billing cycle,
    // not only from the current request payload.
    $grandTotal = (float) ($billingCycle->grand_total_amount ?? $billingCycle->net_amount ?? 0);
    $totalPaid = (float) ($billingCycle->total_paid_amount ?? 0);
    $balance = (float) ($billingCycle->balance_amount ?? max(0, $grandTotal - $totalPaid));
    $isFullyPaid = abs($balance) < 0.01;

    if ($isFullyPaid) {
        $visit->current_phase = 'discharged';
        $visit->status = 'completed';
        $visit->clinical_care_ended_at = $visit->clinical_care_ended_at ?? now();
        $visit->discharged_at = $visit->discharged_at ?? now();
        $visit->payment_status = 'paid_in_full';
    } else {
        // When any money is still pending, visit must NOT remain completed
        if (!in_array($visit->current_phase, ['expired', 'transferred'], true)) {
            $visit->current_phase = 'billing';
        }

        if ($visit->status === 'completed' || $visit->status === 'in_progress' || $visit->status === 'active') {
            $visit->status = 'active';
        }

        $visit->payment_status = $totalPaid > 0 ? 'partially_paid' : 'pending';
    }

    $visit->estimated_total_charges = $grandTotal;
    $visit->patient_estimated_responsibility = $balance;
    $visit->updated_by_staff_id = $staffId;

    $metadata = is_array($visit->metadata)
        ? $visit->metadata
        : (json_decode($visit->metadata ?? '{}', true) ?? []);

    if (!isset($metadata['billing'])) {
        $metadata['billing'] = [];
    }

    $metadata['billing'][] = [
        'billing_cycle_id' => $billingCycle->id,
        'billing_cycle_uuid' => $billingCycle->billing_cycle_uuid,
        'event_type' => $wasExistingCycleUpdated ? 'billing_cycle_updated' : 'billing_cycle_created',
        'saved_at' => now()->toIso8601String(),
        'saved_by_staff_id' => $staffId,
        'receipt_number' => "REC-{$billingCycle->id}",
        'grand_total' => $grandTotal,
        'total_paid' => $totalPaid,
        'balance' => $balance,
        'is_fully_paid' => $isFullyPaid,
        'payment_methods' => $data['payment_methods'] ?? [],
        'payment_split' => [
            'insurance' => $billingCycle->insurance_payment_received ?? 0,
            'patient' => $billingCycle->patient_payment_received ?? 0,
        ],
        'discount' => [
            'type' => $data['discount']['type'] ?? null,
            'value' => $data['discount']['value'] ?? 0,
            'reason' => $data['discount']['reason'] ?? null,
        ],
    ];

    $metadata['latest_billing'] = [
        'billing_cycle_id' => $billingCycle->id,
        'receipt_number' => "REC-{$billingCycle->id}",
        'saved_at' => now()->toIso8601String(),
        'grand_total' => $grandTotal,
        'total_paid' => $totalPaid,
        'balance' => $balance,
        'payment_status' => $visit->payment_status,
        'billing_status' => $billingCycle->billing_status,
    ];

    if ($isFullyPaid) {
        $metadata['visit_completion'] = [
            'completed_at' => now()->toIso8601String(),
            'completed_by_staff_id' => $staffId,
            'completion_reason' => 'billing_fully_paid',
            'final_balance' => $balance,
            'billing_cycle_id' => $billingCycle->id,
            'receipt_number' => "REC-{$billingCycle->id}",
        ];
    } else {
        // If visit had been incorrectly completed before, clear billing-completion marker
        unset($metadata['visit_completion']);
    }

    $visit->metadata = $metadata;
    $visit->save();

    Log::info('Visit billing status updated', [
        'visit_id' => $visit->id,
        'payment_status' => $visit->payment_status,
        'current_phase' => $visit->current_phase,
        'visit_status' => $visit->status,
        'grand_total' => $grandTotal,
        'total_paid' => $totalPaid,
        'balance' => $balance,
        'is_fully_paid' => $isFullyPaid,
        'billing_cycle_id' => $billingCycle->id,
        'was_existing_cycle_updated' => $wasExistingCycleUpdated,
    ]);
}


    /**
     * Calculate pro-rated discount for a line item
     *
     * @param float $lineTotal Line item total
     * @param float $subtotal Overall subtotal
     * @param float $totalDiscount Total discount amount
     * @return float Pro-rated discount for this line item
     */
    protected function calculateLineItemDiscount(float $lineTotal, float $subtotal, float $totalDiscount): float
    {
        if ($subtotal <= 0) {
            return 0;
        }

        return ($lineTotal / $subtotal) * $totalDiscount;
    }


  protected function syncCycleLineItemStatuses(int $billingCycleId, bool $isFullyPaid): void
{
    InvoiceLineItem::query()
        ->where('billing_cycle_id', $billingCycleId)
        ->whereNotIn('line_item_status', ['adjusted', 'written_off', 'denied'])
        ->update([
            'line_item_status' => $isFullyPaid ? 'paid' : 'pending',
            'updated_at' => now(),
        ]);
}

/**
 * Adjust a persisted line item in an auditable and inventory-safe way.
 *
 * remove => quantity becomes zero and line item is marked adjusted.
 * We do NOT hard-delete persisted billing items in healthcare billing.
 */
public function processPersistedLineItemAdjustment(
    InvoiceLineItem $lineItem,
    string $action,
    float $quantity,
    ?string $reason,
    int $staffId
): array {
    return DB::transaction(function () use ($lineItem, $action, $quantity, $reason, $staffId) {
        $lineItem = InvoiceLineItem::query()
            ->lockForUpdate()
            ->findOrFail($lineItem->id);

        $billingCycle = BillingCycle::query()
            ->lockForUpdate()
            ->findOrFail($lineItem->billing_cycle_id);

        $visit = Visit::query()
            ->lockForUpdate()
            ->findOrFail($billingCycle->visit_id);

        if (in_array($billingCycle->billing_status, [
            'paid_in_full',
            'pending_submission',
            'submitted_to_insurance',
            'payment_plan',
            'collections',
            'disputed',
            'written_off',
            'charity_care',
            'partially_refunded',
            'fully_refunded',
        ], true)) {
            throw new RuntimeException(
                "Billing cycle is in locked status '{$billingCycle->billing_status}' and cannot be adjusted."
            );
        }

        $oldQuantity = (float) $lineItem->quantity;
        $unitPrice = (float) $lineItem->unit_price_at_time;
        $oldDiscountAmount = (float) $lineItem->discount_amount;
        $discountPerUnit = $oldQuantity > 0 ? round($oldDiscountAmount / $oldQuantity, 6) : 0.0;

        if ($action === 'decrease' && $quantity > $oldQuantity) {
            throw new RuntimeException('Cannot decrease by more than the currently billed quantity.');
        }

        $newQuantity = $oldQuantity;

        switch ($action) {
            case 'increase':
                $newQuantity = $oldQuantity + $quantity;
                break;

            case 'decrease':
                $newQuantity = max(0, $oldQuantity - $quantity);
                break;

            case 'remove':
                $newQuantity = 0;
                break;
        }

        $deltaQuantity = round($newQuantity - $oldQuantity, 2);

        // Inventory impact:
        // + increase => deduct stock
        // + decrease/remove => restore stock
        if ($deltaQuantity > 0) {
            $this->adjustInventoryForPersistedLineItem(
                (string) $lineItem->service_code,
                $deltaQuantity,
                $staffId,
                'deduct'
            );
        } elseif ($deltaQuantity < 0) {
            $this->adjustInventoryForPersistedLineItem(
                (string) $lineItem->service_code,
                abs($deltaQuantity),
                $staffId,
                'restore'
            );
        }

        $newLineTotal = round($newQuantity * $unitPrice, 2);
        $newDiscountAmount = round($discountPerUnit * $newQuantity, 2);
        $newNetAmount = round(max(0, $newLineTotal - $newDiscountAmount), 2);

        $metadata = $this->decodeJsonArray($lineItem->metadata ?? null);
        $adjustmentHistory = $metadata['adjustment_history'] ?? [];

        $adjustmentHistory[] = [
            'adjusted_at' => now()->toIso8601String(),
            'adjusted_by_staff_id' => $staffId,
            'action' => $action,
            'reason' => $reason,
            'old_quantity' => $oldQuantity,
            'new_quantity' => $newQuantity,
            'delta_quantity' => $deltaQuantity,
            'unit_price' => $unitPrice,
        ];

        $metadata['adjustment_history'] = $adjustmentHistory;
        $metadata['last_adjusted_at'] = now()->toIso8601String();
        $metadata['last_adjusted_by_staff_id'] = $staffId;
        $metadata['last_adjustment_action'] = $action;
        $metadata['last_adjustment_reason'] = $reason;
        $metadata['originated_by_staff_id'] = $metadata['originated_by_staff_id'] ?? $lineItem->created_by_staff_id;

        $lineItem->quantity = $newQuantity;
        $lineItem->line_total_amount = $newLineTotal;
        $lineItem->discount_amount = $newDiscountAmount;
        $lineItem->net_amount = $newNetAmount;
        $lineItem->adjustment_reason = $reason;
        $lineItem->adjustment_amount = round(max(0, ($oldQuantity * $unitPrice) - $newLineTotal), 2);
        $lineItem->line_item_status = $newQuantity <= 0 ? 'adjusted' : 'adjusted';
        $lineItem->staff_performed_id = $staffId;
        $lineItem->service_performed_at = now();
        $lineItem->audit_trail_hash = hash('sha256', json_encode([
            'line_item_id' => $lineItem->id,
            'action' => $action,
            'old_quantity' => $oldQuantity,
            'new_quantity' => $newQuantity,
            'unit_price' => $unitPrice,
            'reason' => $reason,
            'timestamp' => now()->toIso8601String(),
        ]));
        $lineItem->metadata = json_encode($metadata);
        $lineItem->save();

        // Recalculate the cycle from actual line items after adjustment
        $billingCycle = $this->recalculateBillingCycleTotalsFromLineItems($billingCycle, $staffId);

        // Update visit status based on the refreshed cycle totals
        $this->updateVisitBillingStatus(
            $visit,
            [
                'payment_methods' => [],
                'discount' => [],
            ],
            $billingCycle,
            $staffId,
            [
                'insurance_payment' => (float) ($billingCycle->insurance_payment_received ?? 0),
                'patient_payment' => (float) ($billingCycle->patient_payment_received ?? 0),
            ],
            true
        );

        return [
            'billing_cycle' => $billingCycle->fresh(),
            'line_item' => $lineItem->fresh(),
        ];
    });
}


protected function decodeJsonArray($value): array
{
    if (is_array($value)) {
        return $value;
    }

    if (is_string($value) && trim($value) !== '') {
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    return [];
}

protected function mergeTaxDetails(array $existingTaxes, array $incomingTaxes): array
{
    $merged = [];

    foreach (array_merge($existingTaxes, $incomingTaxes) as $tax) {
        $name = (string) ($tax['name'] ?? 'Tax');
        $rate = round((float) ($tax['rate'] ?? 0), 2);
        $amount = round((float) ($tax['amount'] ?? 0), 2);

        $key = strtolower($name) . '|' . number_format($rate, 2, '.', '');

        if (!isset($merged[$key])) {
            $merged[$key] = [
                'name' => $name,
                'rate' => $rate,
                'amount' => $amount,
            ];
            continue;
        }

        $merged[$key]['amount'] = round($merged[$key]['amount'] + $amount, 2);
    }

    return array_values($merged);
}

protected function mergeAdditionalNotes(?string $existing, ?string $incoming): ?string
{
    $existing = trim((string) $existing);
    $incoming = trim((string) $incoming);

    if ($existing === '' && $incoming === '') {
        return null;
    }

    if ($existing === '') {
        return $incoming;
    }

    if ($incoming === '') {
        return $existing;
    }

    return $existing . PHP_EOL . $incoming;
}

/**
 * Recalculate billing cycle totals from current persisted line items.
 *
 * This is safer than manually applying deltas because it always rebuilds
 * the cycle from the authoritative stored line items.
 */
protected function recalculateBillingCycleTotalsFromLineItems(BillingCycle $billingCycle, int $staffId): BillingCycle
{
    $lineItems = InvoiceLineItem::query()
        ->where('billing_cycle_id', $billingCycle->id)
        ->get();

    $subtotal = round((float) $lineItems->sum('line_total_amount'), 2);
    $discountTotal = round((float) $lineItems->sum('discount_amount'), 2);
    $taxableAmount = round(max(0, $subtotal - $discountTotal), 2);

    $existingTaxes = $this->decodeJsonArray($billingCycle->tax_details ?? null);

    $recalculatedTaxes = collect($existingTaxes)->map(function ($tax) use ($taxableAmount) {
        $rate = round((float) ($tax['rate'] ?? 0), 2);

        return [
            'name' => (string) ($tax['name'] ?? 'Tax'),
            'rate' => $rate,
            'amount' => round($taxableAmount * ($rate / 100), 2),
        ];
    })->values()->toArray();

    $taxTotal = round((float) collect($recalculatedTaxes)->sum('amount'), 2);
    $grandTotal = round($taxableAmount + $taxTotal, 2);

    $totalPaid = round((float) ($billingCycle->total_paid_amount ?? 0), 2);
    $balance = round(max(0, $grandTotal - $totalPaid), 2);
    $isFullyPaid = abs($balance) < 0.01;

    $billingStatus = $isFullyPaid
        ? 'paid_in_full'
        : ($totalPaid > 0 ? 'partially_paid' : 'pending');

    $metadata = $this->decodeJsonArray($billingCycle->metadata ?? null);

    $billingCycle->fill([
        'subtotal_amount' => $subtotal,
        'total_amount_charged' => $subtotal,
        'total_adjustments' => $discountTotal,
        'discount_applied' => $discountTotal,
        'taxable_amount' => $taxableAmount,
        'total_tax_amount' => $taxTotal,
        'tax_details' => json_encode($recalculatedTaxes),
        'net_amount' => $grandTotal,
        'grand_total_amount' => $grandTotal,
        'balance_amount' => $balance,
        'patient_responsibility_amount' => max(0, $grandTotal - (float) ($billingCycle->insurance_payment_received ?? 0)),
        'billing_status' => $billingStatus,
        'payment_due_date' => !$isFullyPaid
            ? ($billingCycle->payment_due_date ?? now()->addDays(30))
            : null,
        'updated_by_staff_id' => $staffId,
        'metadata' => json_encode(array_merge($metadata, [
            'validated_total_paid' => $totalPaid,
            'balance_amount' => $balance,
            'resolved_billing_status' => $billingStatus,
            'resolved_payment_status' => $billingStatus,
            'is_fully_paid' => $isFullyPaid,
            'last_recalculated_at' => now()->toIso8601String(),
            'last_recalculated_by_staff_id' => $staffId,
        ])),
    ]);

    $billingCycle->save();

    return $billingCycle->fresh();
}

/**
 * Apply inventory effect for persisted line-item adjustment.
 *
 * direction:
 * - deduct  => used for increase
 * - restore => used for decrease/remove
 */
protected function adjustInventoryForPersistedLineItem(
    string $serviceCode,
    float $deltaQuantity,
    int $staffId,
    string $direction
): void {
    $units = (int) round($deltaQuantity);

    if ($units <= 0) {
        return;
    }

    $inventoryItem = InventoryItem::query()
        ->where('item_code', $serviceCode)
        ->where('status', 'active')
        ->lockForUpdate()
        ->first();

    // If no inventory item exists, it is probably a service-catalog item.
    if (!$inventoryItem) {
        return;
    }

    $previousQuantity = (int) $inventoryItem->package_quantity;

    if ($direction === 'deduct') {
        if ($previousQuantity < $units) {
            throw new RuntimeException(
                "Insufficient stock for item code '{$serviceCode}'. Available: {$previousQuantity}, Requested: {$units}."
            );
        }

        $newQuantity = $previousQuantity - $units;
    } else {
        $newQuantity = $previousQuantity + $units;
    }

    $metadata = is_array($inventoryItem->metadata)
        ? $inventoryItem->metadata
        : (json_decode($inventoryItem->metadata ?? '{}', true) ?? []);

    $metadata['stock_adjustments'][] = [
        'adjusted_at' => now()->toIso8601String(),
        'adjusted_by_staff_id' => $staffId,
        'service_code' => $serviceCode,
        'direction' => $direction,
        'units' => $units,
        'previous_quantity' => $previousQuantity,
        'new_quantity' => $newQuantity,
        'reason' => 'billing_line_item_adjustment',
    ];

    $inventoryItem->package_quantity = $newQuantity;
    $inventoryItem->updated_by_staff_id = $staffId;
    $inventoryItem->metadata = $metadata;
    $inventoryItem->save();
}


}

