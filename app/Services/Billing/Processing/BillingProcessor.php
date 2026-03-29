<?php

namespace App\Services\Billing\Processing;

use App\Models\BillingCycle;
use App\Models\InventoryItem;
use App\Models\InvoiceLineItem;
use App\Models\Visit;
use App\Support\HealthcareIdGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

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

            $lineItems = $this->createOrUpdateLineItems(
                $data,
                $billingCycle->id,
                $staffId,
                $existingBillingCycle
            );

            $this->deductInventoryStock($data['charge_items'], $staffId);

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
                'billing_cycle' => $billingCycle->fresh(),
                'line_items' => $lineItems,
            ];
        });
    }

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
        $subtotalAmount = round((float) ($data['billing_data']['subtotal'] ?? 0), 2);
        $taxableAmount = round((float) ($data['billing_data']['taxableAmount'] ?? max(0, $subtotalAmount - $discountAmount)), 2);
        $taxTotal = round((float) ($data['billing_data']['taxTotal'] ?? 0), 2);
        $grandTotal = round((float) ($data['billing_data']['grandTotal'] ?? 0), 2);

        $incomingTotalPaid = round((float) (
            $data['resolved_total_paid']
            ?? ($paymentSplit['total_paid'] ?? (($paymentSplit['insurance_payment'] ?? 0) + ($paymentSplit['patient_payment'] ?? 0)))
        ), 2);

        $discountRule = [
            'type' => $data['discount']['type'] ?? null,
            'value' => round((float) ($data['discount']['value'] ?? 0), 2),
            'reason' => $data['discount']['reason'] ?? null,
        ];

        $authoritativeTaxes = array_values(array_map(function ($tax) {
            return [
                'name' => (string) ($tax['name'] ?? 'Tax'),
                'rate' => round((float) ($tax['rate'] ?? 0), 2),
                'amount' => round((float) ($tax['amount'] ?? 0), 2),
            ];
        }, $data['taxes'] ?? []));

        if ($existingBillingCycle) {
            $existingMetadata = $this->decodeJsonArray($existingBillingCycle->metadata ?? null);

            $mergedPaymentMethods = array_values(array_merge(
                $existingMetadata['payment_methods'] ?? [],
                $data['payment_methods'] ?? []
            ));

            $newInsurancePayment = round(
                (float) ($existingBillingCycle->insurance_payment_received ?? 0) + (float) ($paymentSplit['insurance_payment'] ?? 0),
                2
            );

            $newPatientPayment = round(
                (float) ($existingBillingCycle->patient_payment_received ?? 0) + (float) ($paymentSplit['patient_payment'] ?? 0),
                2
            );

            $newTotalPaid = round(
                (float) ($existingBillingCycle->total_paid_amount ?? 0) + $incomingTotalPaid,
                2
            );

            $newBalance = round(max(0, $grandTotal - $newTotalPaid), 2);
            $isFullyPaid = abs($newBalance) < 0.01;

            $newBillingStatus = $isFullyPaid
                ? 'paid_in_full'
                : ($newTotalPaid > 0 ? 'partially_paid' : 'pending');

            $existingBillingCycle->fill([
                'subtotal_amount' => $subtotalAmount,
                'total_amount_charged' => $subtotalAmount,
                'total_adjustments' => $discountAmount,
                'discount_applied' => $discountAmount,
                'taxable_amount' => $taxableAmount,
                'total_tax_amount' => $taxTotal,
                'tax_details' => json_encode($authoritativeTaxes),
                'net_amount' => $grandTotal,
                'grand_total_amount' => $grandTotal,

                'insurance_covered_amount' => $newInsurancePayment,
                'insurance_payment_received' => $newInsurancePayment,
                'patient_responsibility_amount' => round(max(0, $grandTotal - $newInsurancePayment), 2),
                'patient_payment_received' => $newPatientPayment,
                'total_paid_amount' => $newTotalPaid,
                'balance_amount' => $newBalance,

                'discount_reason' => $discountRule['reason'],
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
                'discount_rule' => $discountRule,
                'is_cash_payment' => $isPrimaryCash,
                'validated_total_paid' => $newTotalPaid,
                'balance_amount' => $newBalance,
                'resolved_billing_status' => $newBillingStatus,
                'resolved_payment_status' => $newBillingStatus,
                'frontend_ui_status' => $data['status'] ?? null,
                'is_fully_paid' => $isFullyPaid,
                'last_submission_fingerprint' => $data['submission_fingerprint'] ?? null,
                'last_submission_at' => now()->toIso8601String(),
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
            'discount_applied' => $discountAmount,
            'taxable_amount' => $taxableAmount,
            'tax_details' => json_encode($authoritativeTaxes),
            'total_tax_amount' => $taxTotal,
            'net_amount' => $grandTotal,
            'grand_total_amount' => $grandTotal,

            'insurance_covered_amount' => round((float) ($paymentSplit['insurance_payment'] ?? 0), 2),
            'insurance_payment_received' => round((float) ($paymentSplit['insurance_payment'] ?? 0), 2),
            'insurance_claim_submitted_at' => $isInsuranceInvolved ? now() : null,
            'insurance_payment_received_at' => ((float) ($paymentSplit['insurance_payment'] ?? 0)) > 0 ? now() : null,

            'patient_responsibility_amount' => round(max(0, $grandTotal - (float) ($paymentSplit['insurance_payment'] ?? 0)), 2),
            'patient_payment_received' => round((float) ($paymentSplit['patient_payment'] ?? 0), 2),
            'total_paid_amount' => $validatedTotalPaid,
            'balance_amount' => $balanceAmount,

            'discount_reason' => $discountRule['reason'],
            'billing_status' => $billingStatus,
            'billed_at' => now(),
            'payment_due_date' => !$isFullyPaid ? now()->addDays(30) : null,

            'created_by_staff_id' => $staffId,
            'updated_by_staff_id' => $staffId,
            'metadata' => json_encode([
                'payment_methods' => $data['payment_methods'] ?? [],
                'additional_notes' => $data['additional_notes'] ?? null,
                'discount_rule' => $discountRule,
                'is_cash_payment' => $isPrimaryCash,
                'validated_total_paid' => $validatedTotalPaid,
                'balance_amount' => $balanceAmount,
                'resolved_billing_status' => $billingStatus,
                'resolved_payment_status' => $data['payment_status'] ?? null,
                'frontend_ui_status' => $data['status'] ?? null,
                'is_fully_paid' => $isFullyPaid,
                'last_submission_fingerprint' => $data['submission_fingerprint'] ?? null,
                'last_submission_at' => now()->toIso8601String(),
            ]),
        ]);
    }

    protected function createOrUpdateLineItems(
        array $data,
        int $billingCycleId,
        int $staffId,
        ?BillingCycle $existingBillingCycle = null
    ): array {
        $lineItems = [];

        $serviceCodes = array_map(function ($chargeItem) {
            return $chargeItem['service']['code'];
        }, $data['charge_items']);

        $inventoryItems = \App\Models\InventoryItem::query()
            ->whereIn('item_code', $serviceCodes)
            ->get()
            ->keyBy('item_code');

        $serviceCatalogs = \App\Models\ServiceCatalog::query()
            ->whereIn('service_code', $serviceCodes)
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
            $quantity = round((float) $chargeItem['quantity'], 2);
            $unitPrice = round((float) $service['unitPrice'], 2);
            $lineTotal = round((float) $chargeItem['totalAmount'], 2);
            $serviceCode = (string) $service['code'];
            $serviceKey = (string) ($chargeItem['service_key'] ?? '');

            $inventoryItem = $inventoryItems->get($serviceCode);
            $serviceCatalog = $serviceCatalogs->get($serviceCode);

            $indexKey = $serviceCode . '|' . $serviceKey;

            if (isset($existingLineItemsIndex[$indexKey])) {
                $existingLineItem = $existingLineItemsIndex[$indexKey];
                $existingMeta = $this->decodeJsonArray($existingLineItem->metadata ?? null);

                $newQuantity = round((float) $existingLineItem->quantity + $quantity, 2);
                $newLineTotal = round((float) $existingLineItem->line_total_amount + $lineTotal, 2);

                $existingLineItem->quantity = $newQuantity;
                $existingLineItem->line_total_amount = $newLineTotal;
                $existingLineItem->discount_amount = 0.00;
                $existingLineItem->applied_discount_percentage = 0.00;
                $existingLineItem->addToNetAmount($newLineTotal);
                $existingLineItem->unit_price_at_time = $unitPrice;
                $existingLineItem->staff_performed_id = $staffId;
                $existingLineItem->service_performed_at = now();
                $existingLineItem->line_item_status = !empty($data['resolved_is_fully_paid']) ? 'paid' : 'pending';
                $existingLineItem->metadata = json_encode(array_merge($existingMeta, [
                    'service_key' => $serviceKey,
                    'category' => $service['category'],
                    'source_type' => $inventoryItem ? 'inventory' : ($serviceCatalog ? 'service_catalog' : 'unknown'),
                    'originated_by_staff_id' => $existingMeta['originated_by_staff_id'] ?? $existingLineItem->created_by_staff_id,
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

            $lineItem = InvoiceLineItem::create([
                'line_item_uuid' => (string) Str::uuid(),
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

                'discount_amount' => 0.00,
                'applied_discount_percentage' => 0.00,
                'net_amount' => $lineTotal,

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
                    'originated_by_staff_id' => $staffId,
                ]),
            ]);

            $lineItems[] = $lineItem;
        }

        return $lineItems;
    }

    protected function reallocateCycleLineItemDiscounts(
        BillingCycle $billingCycle,
        array $discountRule,
        int $staffId
    ): void {
        $lineItems = InvoiceLineItem::query()
            ->where('billing_cycle_id', $billingCycle->id)
            ->lockForUpdate()
            ->orderBy('id')
            ->get()
            ->filter(function ($lineItem) {
                $quantity = (float) ($lineItem->quantity ?? 0);
                $status = strtolower((string) ($lineItem->line_item_status ?? ''));

                return $quantity > 0 && !in_array($status, [
                    'removed',
                    'voided',
                    'cancelled',
                    'deleted',
                    'adjusted',
                ], true);
            })
            ->values();

        if ($lineItems->isEmpty()) {
            return;
        }

        $subtotal = round((float) $lineItems->sum(function ($lineItem) {
            return (float) ($lineItem->line_total_amount ?? 0);
        }), 2);

        $discountType = (string) ($discountRule['type'] ?? 'fixed');
        $discountValue = round((float) ($discountRule['value'] ?? 0), 2);

        $totalDiscount = $discountType === 'percentage'
            ? round($subtotal * ($discountValue / 100), 2)
            : round($discountValue, 2);

        $totalDiscount = round(min($subtotal, max(0, $totalDiscount)), 2);

        if ($totalDiscount <= 0 || $subtotal <= 0) {
            foreach ($lineItems as $lineItem) {
                $lineTotal = round((float) ($lineItem->line_total_amount ?? 0), 2);
                $lineItem->discount_amount = 0.00;
                $lineItem->applied_discount_percentage = 0.00;
                $lineItem->net_amount = $lineTotal;
                $lineItem->updated_at = now();
                $lineItem->save();
            }

            return;
        }

        $allocated = 0.00;
        $lastIndex = $lineItems->count() - 1;

        foreach ($lineItems as $index => $lineItem) {
            $lineTotal = round((float) ($lineItem->line_total_amount ?? 0), 2);

            if ($index === $lastIndex) {
                $lineDiscount = round(max(0, $totalDiscount - $allocated), 2);
            } else {
                $lineDiscount = round(($lineTotal / $subtotal) * $totalDiscount, 2);
                $allocated = round($allocated + $lineDiscount, 2);
            }

            $lineDiscount = round(min($lineTotal, max(0, $lineDiscount)), 2);
            $netAmount = round(max(0, $lineTotal - $lineDiscount), 2);
            $discountPercent = $lineTotal > 0
                ? round(($lineDiscount / $lineTotal) * 100, 2)
                : 0.00;

            $metadata = $this->decodeJsonArray($lineItem->metadata ?? null);

            $lineItem->discount_amount = $lineDiscount;
            $lineItem->applied_discount_percentage = $discountPercent;
            $lineItem->net_amount = $netAmount;
            $lineItem->metadata = json_encode(array_merge($metadata, [
                'last_discount_reallocated_at' => now()->toIso8601String(),
                'last_discount_reallocated_by_staff_id' => $staffId,
            ]));
            $lineItem->save();
        }
    }

    protected function deductInventoryStock(array $chargeItems, int $staffId): void
    {
        foreach ($chargeItems as $chargeItem) {
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
            $newPackageQuantity = max(0, $previousQuantity - $quantity);

            $metadata = is_array($inventoryItem->metadata)
                ? $inventoryItem->metadata
                : (json_decode($inventoryItem->metadata ?? '{}', true) ?? []);

            if (!isset($metadata['stock_deductions']) || !is_array($metadata['stock_deductions'])) {
                $metadata['stock_deductions'] = [];
            }

            $metadata['stock_deductions'][] = [
                'deducted_at' => now()->toIso8601String(),
                'deducted_by_staff_id' => $staffId,
                'units_deducted' => $quantity,
                'previous_quantity' => $previousQuantity,
                'new_quantity' => $newPackageQuantity,
                'service_code' => $service['code'] ?? null,
                'service_name' => $service['name'] ?? 'Unknown',
                'reason' => 'billing_finalization',
            ];

            $metadata['last_stock_deduction'] = [
                'deducted_at' => now()->toIso8601String(),
                'deducted_by_staff_id' => $staffId,
                'units_deducted' => $quantity,
                'new_quantity' => $newPackageQuantity,
            ];

            $inventoryItem->package_quantity = $newPackageQuantity;
            $inventoryItem->updated_by_staff_id = $staffId;
            $inventoryItem->metadata = $metadata;
            $inventoryItem->save();
        }
    }

    protected function updateVisitBillingStatus(
        Visit $visit,
        array $data,
        BillingCycle $billingCycle,
        int $staffId,
        array $paymentSplit,
        bool $wasExistingCycleUpdated = false
    ): void {
        $grandTotal = round((float) ($billingCycle->grand_total_amount ?? $billingCycle->net_amount ?? 0), 2);
        $totalPaid = round((float) ($billingCycle->total_paid_amount ?? 0), 2);
        $balance = round((float) ($billingCycle->balance_amount ?? max(0, $grandTotal - $totalPaid)), 2);
        $isFullyPaid = abs($balance) < 0.01;

        $metadata = is_array($visit->metadata)
            ? $visit->metadata
            : (json_decode($visit->metadata ?? '{}', true) ?? []);

        if (!isset($metadata['billing']) || !is_array($metadata['billing'])) {
            $metadata['billing'] = [];
        }

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
        }

        $visit->metadata = $metadata;
        $visit->save();
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
            $lineItem->discount_amount = 0.00;
            $lineItem->applied_discount_percentage = 0.00;
            $lineItem->net_amount = $newLineTotal;
            $lineItem->adjustment_reason = $reason;
            $lineItem->adjustment_amount = round(max(0, ($oldQuantity * $unitPrice) - $newLineTotal), 2);
            $lineItem->line_item_status = $newQuantity <= 0 ? 'adjusted' : 'pending';
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

            $discountRule = $this->extractDiscountRuleFromCycle($billingCycle);

            $this->reallocateCycleLineItemDiscounts(
                $billingCycle->fresh(),
                $discountRule,
                $staffId
            );

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

    protected function recalculateBillingCycleTotalsFromLineItems(BillingCycle $billingCycle, int $staffId): BillingCycle
    {
        $lineItems = InvoiceLineItem::query()
            ->where('billing_cycle_id', $billingCycle->id)
            ->get()
            ->filter(function ($item) {
                $quantity = (float) ($item->quantity ?? 0);
                $status = strtolower((string) ($item->line_item_status ?? ''));

                return $quantity > 0 && !in_array($status, [
                    'removed',
                    'voided',
                    'cancelled',
                    'deleted',
                    'adjusted',
                ], true);
            })
            ->values();

        $subtotal = round((float) $lineItems->sum(function ($item) {
            return (float) ($item->line_total_amount ?? 0);
        }), 2);

        $discountTotal = round((float) $lineItems->sum(function ($item) {
            return (float) ($item->discount_amount ?? 0);
        }), 2);

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

        $insurancePaid = round((float) ($billingCycle->insurance_payment_received ?? 0), 2);
        $patientPaid = round((float) ($billingCycle->patient_payment_received ?? 0), 2);
        $totalPaid = round((float) ($billingCycle->total_paid_amount ?? ($insurancePaid + $patientPaid)), 2);

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
            'tax_details' => json_encode($recalculatedTaxes),
            'total_tax_amount' => $taxTotal,
            'net_amount' => $grandTotal,
            'grand_total_amount' => $grandTotal,
            'total_paid_amount' => $totalPaid,
            'balance_amount' => $balance,
            'patient_responsibility_amount' => round(max(0, $grandTotal - $insurancePaid), 2),
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

        $metadata = is_array($inventoryItem->metadata)
            ? $inventoryItem->metadata
            : (json_decode($inventoryItem->metadata ?? '{}', true) ?? []);

        if (!isset($metadata['stock_adjustments']) || !is_array($metadata['stock_adjustments'])) {
            $metadata['stock_adjustments'] = [];
        }

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
        $metadata = $this->decodeJsonArray($billingCycle->metadata ?? null);
        $rule = is_array($metadata['discount_rule'] ?? null)
            ? $metadata['discount_rule']
            : [];

        return [
            'type' => in_array(($rule['type'] ?? null), ['percentage', 'fixed'], true)
                ? $rule['type']
                : 'fixed',
            'value' => round((float) ($rule['value'] ?? $billingCycle->discount_applied ?? 0), 2),
            'reason' => $rule['reason'] ?? $billingCycle->discount_reason,
        ];
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
}