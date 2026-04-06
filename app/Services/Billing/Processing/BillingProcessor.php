<?php

namespace App\Services\Billing\Processing;

use App\Models\BillingCycle;
use App\Models\InventoryItem;
use App\Models\InvoiceLineItem;
use App\Models\ServiceCatalog;
use App\Models\Staff;
use App\Models\Visit;
use App\Services\Billing\Traits\BillingHelpers;
use App\Support\HealthcareIdGenerator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class BillingProcessor
{
    use BillingHelpers;

    /**
     * Persist a billing submission in one transaction.
     *
     * The transaction deliberately follows this order:
     * 1. Lock/create the billing cycle shell and store request-level metadata.
     * 2. Persist or append line items.
     * 3. Apply stock deductions for newly billed inventory-backed items.
     * 4. Re-normalize line items so discounts remain cycle-owned.
     * 5. Recalculate the cycle using the single authoritative billing state engine.
     * 6. Reflect the billing outcome onto the visit.
     */
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
            $paymentSplit,
            $isPrimaryCash,
            $isInsuranceInvolved,
            $visit,
            $existingBillingCycle
        ) {
            $billingCycle = $this->createOrUpdateBillingCycleSkeleton(
                $data,
                $facilityId,
                $staffId,
                $paymentSplit,
                $isPrimaryCash,
                $isInsuranceInvolved,
                $existingBillingCycle
            );

            $lineItems = $this->createOrUpdateLineItems(
                $data,
                $billingCycle->id,
                $staffId,
                $existingBillingCycle
            );

            $this->deductInventoryStock($data['charge_items'] ?? [], $staffId);

            $this->reallocateCycleLineItemDiscounts(
                $billingCycle->fresh(),
                $data['discount'] ?? [],
                $staffId
            );

            $billingCycle = $this->recalculateBillingCycleTotalsFromLineItems(
                $billingCycle->fresh(),
                $staffId
            );

            $this->syncCycleLineItemStatuses(
                $billingCycle->id,
                $billingCycle->billing_status === 'paid_in_full'
            );

            $this->updateVisitBillingStatus(
                $visit,
                $data,
                $billingCycle,
                $staffId,
                $paymentSplit,
                $existingBillingCycle !== null
            );

            return [
                'billing_cycle' => $billingCycle->fresh(['lineItems']),
                'line_items' => $lineItems,
            ];
        });
    }

    /**
     * Create a new cycle or update the editable cycle shell.
     *
     * Monetary totals are not finalized here. Instead, we store request metadata,
     * cumulative payments, and cycle-level discount/tax definitions. The final
     * financial state is recalculated after line items are persisted.
     */
    protected function createOrUpdateBillingCycleSkeleton(
        array $data,
        int $facilityId,
        int $staffId,
        array $paymentSplit,
        bool $isPrimaryCash,
        bool $isInsuranceInvolved,
        ?BillingCycle $existingBillingCycle = null
    ): BillingCycle {
        $discountRule = $this->normalizeDiscount($data['discount'] ?? []);
        $taxDefinitions = $this->normalizeTaxDefinitions($data['taxes'] ?? []);
        $paymentMethods = $this->normalizePaymentMethods($data['payment_methods'] ?? []);
        $requestedUiStatus = (string) ($data['status'] ?? 'ready');
        $submissionFingerprint = $data['submission_fingerprint'] ?? null;

        if ($existingBillingCycle) {
            Log::debug('Updating existing billing cycle', [
                'billing_cycle_id' => $existingBillingCycle->id,
                'facility_id' => $facilityId,
                'staff_id' => $staffId,
                'is_primary_cash' => $isPrimaryCash,
                'is_insurance_involved' => $isInsuranceInvolved,
            ]);

            $billingCycle = BillingCycle::query()
                ->lockForUpdate()
                ->findOrFail($existingBillingCycle->id);

            $metadata = $this->decodeJsonishToArray($billingCycle->metadata ?? null);
            $existingPaymentMethods = is_array($metadata['payment_methods'] ?? null)
                ? $metadata['payment_methods']
                : [];

            // Calculate new payment amounts
            $newInsurancePayment = round(
                (float) ($billingCycle->insurance_payment_received ?? 0) + (float) ($paymentSplit['insurance_payment'] ?? 0),
                2
            );
            $newPatientPayment = round(
                (float) ($billingCycle->patient_payment_received ?? 0) + (float) ($paymentSplit['patient_payment'] ?? 0),
                2
            );
            
            // Store raw totals before capping
            $rawInsurancePayment = $newInsurancePayment;
            $rawPatientPayment = $newPatientPayment;
            $rawTotalPaid = round($newInsurancePayment + $newPatientPayment, 2);
            
            $billingCycle->insurance_payment_received = $newInsurancePayment;
            $billingCycle->patient_payment_received = $newPatientPayment;
            
            // DON'T set total_paid_amount here - let recalculateBillingCycleTotalsFromLineItems handle it
            // because we don't know grand_total yet
            
            $billingCycle->updated_by_staff_id = $staffId;
            $billingCycle->discount_reason = $discountRule['reason'];
            $billingCycle->insurance_claim_submitted_at = $isInsuranceInvolved
                ? ($billingCycle->insurance_claim_submitted_at ?? now())
                : $billingCycle->insurance_claim_submitted_at;
            $billingCycle->insurance_payment_received_at = ((float) $billingCycle->insurance_payment_received) > 0
                ? ($billingCycle->insurance_payment_received_at ?? now())
                : $billingCycle->insurance_payment_received_at;

            $billingCycle->metadata = json_encode(array_merge($metadata, [
                'payment_methods' => array_values(array_merge($existingPaymentMethods, $paymentMethods)),
                'additional_notes' => $this->mergeAdditionalNotes(
                    $metadata['additional_notes'] ?? null,
                    $data['additional_notes'] ?? null
                ),
                'discount_scope' => 'billing_cycle',
                'discount_rule' => $discountRule,
                'tax_definitions' => $taxDefinitions,
                'is_cash_payment' => $isPrimaryCash,
                'frontend_ui_status' => $requestedUiStatus,
                'last_submission_fingerprint' => $submissionFingerprint,
                'last_submission_at' => now()->toIso8601String(),
                'last_appended_at' => now()->toIso8601String(),
                'last_appended_by_staff_id' => $staffId,
                'raw_insurance_payment_before_cap' => $rawInsurancePayment,
                'raw_patient_payment_before_cap' => $rawPatientPayment,
                'raw_total_paid_before_cap' => $rawTotalPaid,
            ]));

            $billingCycle->save();

            $persistedData = $billingCycle->fresh();
            Log::debug('Billing cycle updated successfully', [
                'billing_cycle_id' => $persistedData->id,
                'billing_cycle_uuid' => $persistedData->billing_cycle_uuid ?? null,
                'insurance_payment_received' => $persistedData->insurance_payment_received,
                'patient_payment_received' => $persistedData->patient_payment_received,
                'billing_status' => $persistedData->billing_status,
                'metadata' => $persistedData->metadata,
            ]);

            return $persistedData;
        }

        Log::debug('Creating new billing cycle', [
            'facility_id' => $facilityId,
            'staff_id' => $staffId,
            'visit_id' => $data['visit_id'] ?? null,
            'patient_id' => $data['patient_id'] ?? null,
            'is_primary_cash' => $isPrimaryCash,
            'is_insurance_involved' => $isInsuranceInvolved,
            'requested_status' => $requestedUiStatus,
        ]);

        $newBillingCycle = BillingCycle::query()->create([
            'billing_cycle_uuid' => HealthcareIdGenerator::generate('billing'),
            'facility_id' => $facilityId,
            'visit_id' => $data['visit_id'],
            'patient_id' => $data['patient_id'],
            'cycle_type' => 'visit_based',
            'period_start' => now(),
            'period_end' => now(),
            'days_in_cycle' => 1,
            'subtotal_amount' => 0.00,
            'total_amount_charged' => 0.00,
            'total_adjustments' => 0.00,
            'discount_applied' => 0.00,
            'discount_reason' => $discountRule['reason'],
            'taxable_amount' => 0.00,
            'tax_details' => json_encode([]),
            'total_tax_amount' => 0.00,
            'net_amount' => 0.00,
            'grand_total_amount' => 0.00,
            'insurance_covered_amount' => round((float) ($paymentSplit['insurance_payment'] ?? 0), 2),
            'insurance_payment_received' => round((float) ($paymentSplit['insurance_payment'] ?? 0), 2),
            'insurance_claim_submitted_at' => $isInsuranceInvolved ? now() : null,
            'insurance_payment_received_at' => ((float) ($paymentSplit['insurance_payment'] ?? 0)) > 0 ? now() : null,
            'patient_responsibility_amount' => 0.00,
            'patient_payment_received' => round((float) ($paymentSplit['patient_payment'] ?? 0), 2),
            'total_paid_amount' => 0.00, // Will be set correctly in recalculation
            'balance_amount' => 0.00,
            'billing_status' => $requestedUiStatus === 'draft' ? 'draft' : 'pending',
            'billed_at' => now(),
            'payment_due_date' => now()->addDays(30),
            'created_by_staff_id' => $staffId,
            'updated_by_staff_id' => $staffId,
            'metadata' => json_encode([
                'payment_methods' => $paymentMethods,
                'additional_notes' => $data['additional_notes'] ?? null,
                'discount_scope' => 'billing_cycle',
                'discount_rule' => $discountRule,
                'tax_definitions' => $taxDefinitions,
                'is_cash_payment' => $isPrimaryCash,
                'frontend_ui_status' => $requestedUiStatus,
                'last_submission_fingerprint' => $submissionFingerprint,
                'last_submission_at' => now()->toIso8601String(),
                'raw_total_paid_before_cap' => round((float) ($paymentSplit['total_paid'] ?? 0), 2),
            ]),
        ]);

        Log::debug('New billing cycle created successfully', [
            'billing_cycle_id' => $newBillingCycle->id,
            'billing_cycle_uuid' => $newBillingCycle->billing_cycle_uuid,
            'insurance_payment_received' => $newBillingCycle->insurance_payment_received,
            'patient_payment_received' => $newBillingCycle->patient_payment_received,
            'billing_status' => $newBillingCycle->billing_status,
            'metadata' => $newBillingCycle->metadata,
        ]);

        return $newBillingCycle;
    }

    /**
     * Persist incoming charges.
     *
     * Existing active line items are appended when the service code + service key
     * match. This preserves one commercial line for one logical billed service.
     * If the old line was fully adjusted away, a new line is created instead so
     * that the historical audit trail remains intact.
     */
    protected function createOrUpdateLineItems(
        array $data,
        int $billingCycleId,
        int $staffId,
        ?BillingCycle $existingBillingCycle = null
    ): array {
        $chargeItems = $this->normalizeChargeItems($data['charge_items'] ?? []);
        if (empty($chargeItems)) {
            return [];
        }

        $serviceCodes = collect($chargeItems)
            ->map(fn (array $item) => (string) ($item['service']['code'] ?? ''))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $inventoryItems = InventoryItem::query()
            ->whereIn('item_code', $serviceCodes)
            ->get()
            ->keyBy('item_code');

        $serviceCatalogs = ServiceCatalog::query()
            ->whereIn('service_code', $serviceCodes)
            ->get()
            ->keyBy('service_code');

        $existingLineItemsIndex = [];

        if ($existingBillingCycle) {
            InvoiceLineItem::query()
                ->where('billing_cycle_id', $billingCycleId)
                ->get()
                ->each(function (InvoiceLineItem $lineItem) use (&$existingLineItemsIndex) {
                    $metadata = $this->decodeJsonishToArray($lineItem->metadata ?? null);
                    $status = strtolower((string) ($lineItem->line_item_status ?? ''));
                    $isActive = (float) ($lineItem->quantity ?? 0) > 0
                        && !in_array($status, ['removed', 'voided', 'cancelled', 'deleted', 'adjusted'], true);

                    if (!$isActive) {
                        return;
                    }

                    $key = (string) ($lineItem->service_code ?? '') . '|' . (string) ($metadata['service_key'] ?? '');
                    $existingLineItemsIndex[$key] = $lineItem;
                });
        }

        $persisted = [];

        foreach ($chargeItems as $chargeItem) {
            $service = $chargeItem['service'];
            $quantity = round((float) $chargeItem['quantity'], 2);
            $unitPrice = round((float) ($service['unitPrice'] ?? 0), 2);
            $lineTotal = round($quantity * $unitPrice, 2);
            $serviceCode = (string) ($service['code'] ?? '');
            $serviceKey = (string) ($chargeItem['service_key'] ?? $chargeItem['serviceKey'] ?? '');
            $indexKey = $serviceCode . '|' . $serviceKey;

            $inventoryItem = $inventoryItems->get($serviceCode);
            $serviceCatalog = $serviceCatalogs->get($serviceCode);

            if (isset($existingLineItemsIndex[$indexKey])) {
                $lineItem = $existingLineItemsIndex[$indexKey];
                $metadata = $this->decodeJsonishToArray($lineItem->metadata ?? null);

                $lineItem->quantity = round((float) $lineItem->quantity + $quantity, 2);
                $lineItem->unit_price_at_time = $unitPrice;
                $lineItem->line_total_amount = round((float) $lineItem->quantity * $unitPrice, 2);
                $lineItem->discount_amount = 0.00;
                $lineItem->applied_discount_percentage = 0.00;
                $lineItem->net_amount = $lineItem->line_total_amount;
                $lineItem->staff_performed_id = $staffId;
                $lineItem->service_performed_at = now();
                $lineItem->line_item_status = 'pending';
                $lineItem->metadata = json_encode(array_merge($metadata, [
                    'service_key' => $serviceKey,
                    'category' => (string) ($service['category'] ?? 'General'),
                    'source_type' => $inventoryItem ? 'inventory' : ($serviceCatalog ? 'service_catalog' : 'unknown'),
                    'discount_scope' => 'billing_cycle',
                    'originated_by_staff_id' => $metadata['originated_by_staff_id'] ?? $lineItem->created_by_staff_id,
                    'last_appended_at' => now()->toIso8601String(),
                    'last_appended_by_staff_id' => $staffId,
                ]));
                $lineItem->audit_trail_hash = $this->buildLineItemAuditHash(
                    $lineItem->id,
                    $serviceCode,
                    $lineItem->quantity,
                    $unitPrice,
                    'append'
                );
                $lineItem->save();

                $persisted[] = $lineItem->fresh();
                continue;
            }

            $lineItem = InvoiceLineItem::query()->create([
                'line_item_uuid' => (string) Str::uuid(),
                'billing_cycle_id' => $billingCycleId,
                'visit_id' => $data['visit_id'],
                'service_version_snapshot' => json_encode($service),
                'service_code' => $serviceCode,
                'service_description' => (string) ($service['name'] ?? ''),
                'inventory_item_id' => $inventoryItem?->id,
                'service_catalog_id' => $serviceCatalog?->id,
                'quantity' => $quantity,
                'unit_of_measure' => 'each',
                'unit_price_at_time' => $unitPrice,
                'line_total_amount' => $lineTotal,
                'discount_amount' => 0.00,
                'applied_discount_percentage' => 0.00,
                'net_amount' => $lineTotal,
                'staff_performed_id' => $staffId,
                'service_performed_at' => now(),
                'line_item_status' => 'pending',
                'audit_trail_hash' => $this->buildLineItemAuditHash(null, $serviceCode, $quantity, $unitPrice, 'create'),
                'created_by_staff_id' => $staffId,
                'metadata' => json_encode([
                    'service_key' => $serviceKey,
                    'category' => (string) ($service['category'] ?? 'General'),
                    'source_type' => $inventoryItem ? 'inventory' : ($serviceCatalog ? 'service_catalog' : 'unknown'),
                    'discount_scope' => 'billing_cycle',
                    'originated_by_staff_id' => $staffId,
                ]),
            ]);

            $persisted[] = $lineItem;
        }

        return $persisted;
    }

    /**
     * Enforce the rewrite rule that line items never own discount values.
     */
    protected function reallocateCycleLineItemDiscounts(
        BillingCycle $billingCycle,
        array $discountRule,
        int $staffId
    ): void {
        $lineItems = InvoiceLineItem::query()
            ->where('billing_cycle_id', $billingCycle->id)
            ->lockForUpdate()
            ->get();

        foreach ($lineItems as $lineItem) {
            $lineTotal = round((float) ($lineItem->line_total_amount ?? 0), 2);
            $metadata = $this->decodeJsonishToArray($lineItem->metadata ?? null);

            $lineItem->discount_amount = 0.00;
            $lineItem->applied_discount_percentage = 0.00;
            $lineItem->net_amount = $lineTotal;
            $lineItem->metadata = json_encode(array_merge($metadata, [
                'discount_scope' => 'billing_cycle',
                'discount_normalized_at' => now()->toIso8601String(),
                'discount_normalized_by_staff_id' => $staffId,
            ]));
            $lineItem->save();
        }

        $cycleMetadata = $this->decodeJsonishToArray($billingCycle->metadata ?? null);
        $billingCycle->discount_applied = $this->calculateDiscountAmount(
            (string) ($discountRule['type'] ?? 'fixed'),
            (float) ($discountRule['value'] ?? 0),
            $this->getActiveBillingCycleSubtotal($billingCycle->fresh(['lineItems']))
        );
        $billingCycle->total_adjustments = $billingCycle->discount_applied;
        $billingCycle->metadata = json_encode(array_merge($cycleMetadata, [
            'discount_scope' => 'billing_cycle',
            'discount_rule' => $this->normalizeDiscount($discountRule),
        ]));
        $billingCycle->save();
    }

    /**
     * Deduct stock only for inventory-backed services represented in the incoming
     * request. Existing already persisted quantities are never re-deducted.
     */
    protected function deductInventoryStock(array $chargeItems, int $staffId): void
    {
        foreach ($this->normalizeChargeItems($chargeItems) as $chargeItem) {
            $service = $chargeItem['service'] ?? [];
            $quantity = (int) round((float) ($chargeItem['quantity'] ?? 0));

            if ($quantity <= 0) {
                continue;
            }

            $inventoryItem = InventoryItem::query()
                ->where('item_code', $service['code'] ?? null)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if (!$inventoryItem) {
                continue;
            }

            if ((int) $inventoryItem->package_quantity < $quantity) {
                throw new RuntimeException(
                    "Insufficient stock for item '{$service['name']}' (Code: {$service['code']})."
                );
            }

            $previousQuantity = (int) $inventoryItem->package_quantity;
            $newQuantity = $previousQuantity - $quantity;
            $metadata = $this->decodeJsonishToArray($inventoryItem->metadata ?? null);
            $metadata['stock_deductions'] = is_array($metadata['stock_deductions'] ?? null)
                ? $metadata['stock_deductions']
                : [];

            $metadata['stock_deductions'][] = [
                'deducted_at' => now()->toIso8601String(),
                'deducted_by_staff_id' => $staffId,
                'units_deducted' => $quantity,
                'previous_quantity' => $previousQuantity,
                'new_quantity' => $newQuantity,
                'service_code' => $service['code'] ?? null,
                'service_name' => $service['name'] ?? 'Unknown',
                'reason' => 'billing_finalization',
            ];

            $metadata['last_stock_deduction'] = end($metadata['stock_deductions']);

            $inventoryItem->package_quantity = $newQuantity;
            $inventoryItem->updated_by_staff_id = $staffId;
            $inventoryItem->metadata = $metadata;
            $inventoryItem->save();
        }
    }

    /**
     * Keep the visit synchronized with the billing cycle's authoritative state.
     */
    protected function updateVisitBillingStatus(
        Visit $visit,
        array $data,
        BillingCycle $billingCycle,
        int $staffId,
        array $paymentSplit,
        bool $wasExistingCycleUpdated = false
    ): void {
        $visit = Visit::query()->lockForUpdate()->findOrFail($visit->id);
        $assignedStaffId=Staff::where('user_id',Auth::user()->id)->first()->id;

        $grandTotal = round((float) ($billingCycle->grand_total_amount ?? $billingCycle->net_amount ?? 0), 2);
        $totalPaid = round((float) ($billingCycle->total_paid_amount ?? 0), 2);
        $balance = round((float) ($billingCycle->balance_amount ?? max(0, $grandTotal - $totalPaid)), 2);
        $isFullyPaid = abs($balance) < 0.01;

        $metadata = $this->decodeJsonishToArray($visit->metadata ?? null);
        $metadata['billing'] = is_array($metadata['billing'] ?? null) ? $metadata['billing'] : [];

        if ($isFullyPaid) {
            $visit->payment_status = 'paid_in_full';
            $visit->current_phase = 'discharged';
            $visit->status = 'completed';
            $visit->clinical_care_ended_at = $visit->clinical_care_ended_at ?? now();
            $visit->discharged_at = $visit->discharged_at ?? now();
        } else {
            $visit->payment_status = $totalPaid > 0 ? 'partially_paid' : 'pending';

            if (!in_array($visit->current_phase, ['expired', 'transferred'], true)) {
                $visit->current_phase = 'billing';
            }

            if (!in_array($visit->status, ['cancelled', 'no_show'], true)) {
                $visit->status = 'active';
            }

            unset($metadata['visit_completion']);
        }

        $visit->estimated_total_charges = $grandTotal;
        $visit->patient_estimated_responsibility = $balance;
        $visit->updated_by_staff_id = $staffId;
        $visit->assigned_staff_id = $assignedStaffId;

        $metadata['billing'][] = [
            'billing_cycle_id' => $billingCycle->id,
            'billing_cycle_uuid' => $billingCycle->billing_cycle_uuid,
            'event_type' => $wasExistingCycleUpdated ? 'billing_cycle_updated' : 'billing_cycle_created',
            'saved_at' => now()->toIso8601String(),
            'saved_by_staff_id' => $staffId,
            'receipt_number' => "REC-{$billingCycle->id}",
            'subtotal' => round((float) ($billingCycle->subtotal_amount ?? 0), 2),
            'discount_amount' => round((float) ($billingCycle->discount_applied ?? 0), 2),
            'tax_amount' => round((float) ($billingCycle->total_tax_amount ?? 0), 2),
            'grand_total' => $grandTotal,
            'total_paid' => $totalPaid,
            'balance' => $balance,
            'is_fully_paid' => $isFullyPaid,
            'payment_methods' => $this->normalizePaymentMethods($data['payment_methods'] ?? []),
            'payment_split' => [
                'insurance' => round((float) ($billingCycle->insurance_payment_received ?? 0), 2),
                'patient' => round((float) ($billingCycle->patient_payment_received ?? 0), 2),
            ],
            'discount' => $this->normalizeDiscount($data['discount'] ?? []),
        ];

        $metadata['latest_billing'] = [
            'billing_cycle_id' => $billingCycle->id,
            'billing_cycle_uuid' => $billingCycle->billing_cycle_uuid,
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
        }

        $visit->metadata = $metadata;
        $visit->save();
    }

    /**
     * Apply the cycle's settlement state down to line item statuses.
     */
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
     * Adjust a persisted line item and then recalculate the owning cycle.
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

            $oldQuantity = round((float) ($lineItem->quantity ?? 0), 2);
            $unitPrice = round((float) ($lineItem->unit_price_at_time ?? 0), 2);

            if ($action === 'decrease' && $quantity > $oldQuantity) {
                throw new RuntimeException('Cannot decrease by more than the currently billed quantity.');
            }

            $newQuantity = match ($action) {
                'increase' => round($oldQuantity + $quantity, 2),
                'decrease' => round(max(0, $oldQuantity - $quantity), 2),
                'remove' => 0.00,
                default => $oldQuantity,
            };

            $deltaQuantity = round($newQuantity - $oldQuantity, 2);

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
            $metadata = $this->decodeJsonishToArray($lineItem->metadata ?? null);
            $metadata['adjustment_history'] = is_array($metadata['adjustment_history'] ?? null)
                ? $metadata['adjustment_history']
                : [];

            $metadata['adjustment_history'][] = [
                'adjusted_at' => now()->toIso8601String(),
                'adjusted_by_staff_id' => $staffId,
                'action' => $action,
                'reason' => $reason,
                'old_quantity' => $oldQuantity,
                'new_quantity' => $newQuantity,
                'delta_quantity' => $deltaQuantity,
                'unit_price' => $unitPrice,
            ];
            $metadata['last_adjusted_at'] = now()->toIso8601String();
            $metadata['last_adjusted_by_staff_id'] = $staffId;
            $metadata['last_adjustment_action'] = $action;
            $metadata['last_adjustment_reason'] = $reason;
            $metadata['discount_scope'] = 'billing_cycle';
            $metadata['originated_by_staff_id'] = $metadata['originated_by_staff_id'] ?? $lineItem->created_by_staff_id;

            $lineItem->quantity = $newQuantity;
            $lineItem->line_total_amount = $newLineTotal;
            $lineItem->discount_amount = 0.00;
            $lineItem->applied_discount_percentage = 0.00;
            $lineItem->net_amount = $newLineTotal;
            $lineItem->adjustment_reason = $reason;
            $lineItem->adjustment_amount = round(max(0, ($oldQuantity * $unitPrice) - $newLineTotal), 2);
            $lineItem->line_item_status = $newQuantity <= 0 ? 'adjusted' : 'pending';
            $lineItem->staff_performed_id = $staffId;
            $lineItem->service_performed_at = now();
            $lineItem->audit_trail_hash = $this->buildLineItemAuditHash(
                $lineItem->id,
                (string) $lineItem->service_code,
                $newQuantity,
                $unitPrice,
                $action
            );
            $lineItem->metadata = json_encode($metadata);
            $lineItem->save();

            $discountRule = $this->extractDiscountRuleFromCycle($billingCycle);

            $this->reallocateCycleLineItemDiscounts($billingCycle->fresh(), $discountRule, $staffId);

            $billingCycle = $this->recalculateBillingCycleTotalsFromLineItems($billingCycle->fresh(), $staffId);

            $this->syncCycleLineItemStatuses(
                $billingCycle->id,
                $billingCycle->billing_status === 'paid_in_full'
            );

            $this->updateVisitBillingStatus(
                $visit,
                [
                    'payment_methods' => [],
                    'discount' => $discountRule,
                ],
                $billingCycle,
                $staffId,
                [
                    'insurance_payment' => 0,
                    'patient_payment' => 0,
                ],
                true
            );

            return [
                'billing_cycle' => $billingCycle->fresh(),
                'line_item' => $lineItem->fresh(),
            ];
        });
    }

    /**
     * Recalculate the billing cycle using stored line items, stored discount rule,
     * stored tax definitions, and the authoritative state engine.
     */
    protected function recalculateBillingCycleTotalsFromLineItems(BillingCycle $billingCycle, int $staffId): BillingCycle
    {
        $billingCycle = BillingCycle::query()
            ->lockForUpdate()
            ->findOrFail($billingCycle->id);

        $metadata = $this->decodeJsonishToArray($billingCycle->metadata ?? null);
        $discountRule = $this->extractDiscountRuleFromCycle($billingCycle);
        $taxDefinitions = $this->extractTaxDefinitionsFromCycle($billingCycle);
        $requestedUiStatus = (string) ($metadata['frontend_ui_status'] ?? $this->mapBillingStatusToUI((string) $billingCycle->billing_status));

        $subtotal = $this->getActiveBillingCycleSubtotal($billingCycle->fresh(['lineItems']));
        $state = $this->determineBillingState(
            $subtotal,
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

        $billingCycle->fill([
            'subtotal_amount' => round((float) $state['subtotal'], 2),
            'total_amount_charged' => round((float) $state['subtotal'], 2),
            'total_adjustments' => round((float) $state['discount_amount'], 2),
            'discount_applied' => round((float) $state['discount_amount'], 2),
            'discount_reason' => $discountRule['reason'] ?? null,
            'taxable_amount' => round((float) $state['taxable_amount'], 2),
            'tax_details' => json_encode($state['taxes']),
            'total_tax_amount' => round((float) $state['tax_total'], 2),
            'net_amount' => round((float) $state['grand_total'], 2),
            'grand_total_amount' => round((float) $state['grand_total'], 2),
            'insurance_covered_amount' => round((float) $state['insurance_payment'], 2),
            'patient_responsibility_amount' => round(max(0, (float) $state['grand_total'] - (float) $state['insurance_payment']), 2),
            'total_paid_amount' => round((float) $state['total_paid'], 2),
            'balance_amount' => round((float) $state['balance'], 2),
            'billing_status' => (string) $state['billing_status'],
            'payment_due_date' => !empty($state['is_fully_paid']) ? null : ($billingCycle->payment_due_date ?? now()->addDays(30)),
            'updated_by_staff_id' => $staffId,
            'metadata' => json_encode(array_merge($metadata, [
                'discount_scope' => 'billing_cycle',
                'discount_rule' => $discountRule,
                'tax_definitions' => $taxDefinitions,
                'validated_total_paid' => round((float) $state['total_paid'], 2),
                'balance_amount' => round((float) $state['balance'], 2),
                'resolved_billing_status' => (string) $state['billing_status'],
                'resolved_payment_status' => (string) $state['payment_status'],
                'frontend_ui_status' => (string) $state['ui_status'],
                'is_fully_paid' => (bool) $state['is_fully_paid'],
                'last_recalculated_at' => now()->toIso8601String(),
                'last_recalculated_by_staff_id' => $staffId,
            ])),
        ]);

        $billingCycle->save();

        return $billingCycle->fresh();
    }

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

        $metadata = $this->decodeJsonishToArray($inventoryItem->metadata ?? null);
        $metadata['stock_adjustments'] = is_array($metadata['stock_adjustments'] ?? null)
            ? $metadata['stock_adjustments']
            : [];

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

    protected function extractDiscountRuleFromCycle(BillingCycle $billingCycle): array
    {
        $metadata = $this->decodeJsonishToArray($billingCycle->metadata ?? null);
        $rule = is_array($metadata['discount_rule'] ?? null) ? $metadata['discount_rule'] : [];

        return $this->normalizeDiscount([
            'type' => $rule['type'] ?? 'fixed',
            'value' => $rule['value'] ?? $billingCycle->discount_applied ?? 0,
            'reason' => $rule['reason'] ?? $billingCycle->discount_reason,
        ]);
    }

    protected function extractTaxDefinitionsFromCycle(BillingCycle $billingCycle): array
    {
        $metadata = $this->decodeJsonishToArray($billingCycle->metadata ?? null);
        $taxDefinitions = $metadata['tax_definitions'] ?? null;

        if (is_array($taxDefinitions) && !empty($taxDefinitions)) {
            return $this->normalizeTaxDefinitions($taxDefinitions);
        }

        return $this->normalizeTaxDefinitions($this->decodeJsonishToArray($billingCycle->tax_details ?? null));
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

    protected function buildLineItemAuditHash(
        ?int $lineItemId,
        string $serviceCode,
        float $quantity,
        float $unitPrice,
        string $action
    ): string {
        return hash('sha256', json_encode([
            'line_item_id' => $lineItemId,
            'service_code' => $serviceCode,
            'quantity' => round($quantity, 2),
            'unit_price' => round($unitPrice, 2),
            'action' => $action,
            'timestamp' => now()->toIso8601String(),
        ]));
    }
}