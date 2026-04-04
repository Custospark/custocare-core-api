<?php

namespace App\Services\Billing\Traits;

use App\Models\BillingCycle;
use App\Models\Visit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

trait BillingHelpers
{
    /**
     * Determine whether the visit can enter a billing flow.
     *
     * We intentionally keep this rule narrow. A visit with an editable billing
     * cycle is still billable because the cycle may be appended or adjusted.
     */
    public function canBeBilled(Visit $visit, ?BillingCycle $existingBillingCycle = null): array
    {
        if ($visit->status === 'cancelled') {
            return [
                'success' => false,
                'message' => 'Cannot bill a cancelled visit.',
                'errors' => ['visit' => ['This visit has been cancelled and cannot be billed.']],
            ];
        }

        if (in_array($visit->current_phase, ['expired', 'transferred'], true)) {
            return [
                'success' => false,
                'message' => 'Cannot bill a visit in a terminal phase.',
                'errors' => ['visit' => ['This visit is already in a terminal phase.']],
            ];
        }

        if ($visit->payment_status === 'paid_in_full' && !$existingBillingCycle) {
            return [
                'success' => false,
                'message' => 'Visit payment is already settled.',
                'errors' => ['visit' => ['This visit has already been paid in full.']],
            ];
        }

        return [
            'success' => true,
            'message' => 'Visit is eligible for billing.',
        ];
    }

    /**
     * The single authoritative billing state engine.
     *
     * All core money values are derived here so that the application does not
     * drift into having separate formulas for save, update, adjustment, and read.
     *
     * Key rules:
     * - Discounts are always cycle-level, never line-level.
     * - Taxes are computed after the cycle-level discount is applied.
     * - Persisted payments already stored on the cycle are treated as historical
     *   facts and are combined with incoming payments for the current request.
     * - UI status is derived from the financial state, not the other way around.
     */
    protected function determineBillingState(
        float $subtotal,
        array $discountRule = [],
        array $taxDefinitions = [],
        array $incomingPaymentSplit = [],
        string $requestedUiStatus = 'ready',
        ?BillingCycle $existingBillingCycle = null
    ): array {
        $subtotal = round(max(0, $subtotal), 2);
        $discountRule = $this->normalizeDiscount($discountRule);
        $taxDefinitions = $this->normalizeTaxDefinitions($taxDefinitions);

        $existingPatientPaid = round(max(0, (float) ($existingBillingCycle->patient_payment_received ?? 0)), 2);
        $existingInsurancePaid = round(max(0, (float) ($existingBillingCycle->insurance_payment_received ?? 0)), 2);

        $incomingPatientPaid = round(max(0, (float) ($incomingPaymentSplit['patient_payment'] ?? 0)), 2);
        $incomingInsurancePaid = round(max(0, (float) ($incomingPaymentSplit['insurance_payment'] ?? 0)), 2);

        $discountAmount = $this->calculateDiscountAmount(
            $discountRule['type'],
            (float) $discountRule['value'],
            $subtotal
        );

        $taxableAmount = round(max(0, $subtotal - $discountAmount), 2);

        $taxes = collect($taxDefinitions)
            ->map(function (array $tax) use ($taxableAmount) {
                $rate = round((float) ($tax['rate'] ?? 0), 2);

                return [
                    'name' => trim((string) ($tax['name'] ?? 'Tax')),
                    'rate' => $rate,
                    'amount' => round($taxableAmount * ($rate / 100), 2),
                ];
            })
            ->values()
            ->all();

        $taxTotal = round((float) collect($taxes)->sum('amount'), 2);
        $grandTotal = round($taxableAmount + $taxTotal, 2);

        $patientPayment = round($existingPatientPaid + $incomingPatientPaid, 2);
        $insurancePayment = round($existingInsurancePaid + $incomingInsurancePaid, 2);
        $totalPaid = round($patientPayment + $insurancePayment, 2);
        $balance = round(max(0, $grandTotal - $totalPaid), 2);
        $isFullyPaid = abs($balance) < 0.01;

        if ($grandTotal <= 0.00) {
            return [
                'subtotal' => $subtotal,
                'discount_rule' => $discountRule,
                'discount_amount' => $discountAmount,
                'taxable_amount' => $taxableAmount,
                'taxes' => $taxes,
                'tax_total' => $taxTotal,
                'grand_total' => $grandTotal,
                'existing_patient_payment' => $existingPatientPaid,
                'existing_insurance_payment' => $existingInsurancePaid,
                'incoming_patient_payment' => $incomingPatientPaid,
                'incoming_insurance_payment' => $incomingInsurancePaid,
                'patient_payment' => $patientPayment,
                'insurance_payment' => $insurancePayment,
                'total_paid' => $totalPaid,
                'balance' => 0.00,
                'is_fully_paid' => true,
                'billing_status' => 'paid_in_full',
                'payment_status' => 'paid_in_full',
                'ui_status' => 'settled',
            ];
        }

        if ($isFullyPaid) {
            return [
                'subtotal' => $subtotal,
                'discount_rule' => $discountRule,
                'discount_amount' => $discountAmount,
                'taxable_amount' => $taxableAmount,
                'taxes' => $taxes,
                'tax_total' => $taxTotal,
                'grand_total' => $grandTotal,
                'existing_patient_payment' => $existingPatientPaid,
                'existing_insurance_payment' => $existingInsurancePaid,
                'incoming_patient_payment' => $incomingPatientPaid,
                'incoming_insurance_payment' => $incomingInsurancePaid,
                'patient_payment' => $patientPayment,
                'insurance_payment' => $insurancePayment,
                'total_paid' => $totalPaid,
                'balance' => $balance,
                'is_fully_paid' => true,
                'billing_status' => 'paid_in_full',
                'payment_status' => 'paid_in_full',
                'ui_status' => 'settled',
            ];
        }

        if ($totalPaid > 0.00) {
            return [
                'subtotal' => $subtotal,
                'discount_rule' => $discountRule,
                'discount_amount' => $discountAmount,
                'taxable_amount' => $taxableAmount,
                'taxes' => $taxes,
                'tax_total' => $taxTotal,
                'grand_total' => $grandTotal,
                'existing_patient_payment' => $existingPatientPaid,
                'existing_insurance_payment' => $existingInsurancePaid,
                'incoming_patient_payment' => $incomingPatientPaid,
                'incoming_insurance_payment' => $incomingInsurancePaid,
                'patient_payment' => $patientPayment,
                'insurance_payment' => $insurancePayment,
                'total_paid' => $totalPaid,
                'balance' => $balance,
                'is_fully_paid' => false,
                'billing_status' => 'partially_paid',
                'payment_status' => 'partially_paid',
                'ui_status' => 'ready',
            ];
        }

        $billingStatus = $requestedUiStatus === 'draft' ? 'draft' : 'pending';

        return [
            'subtotal' => $subtotal,
            'discount_rule' => $discountRule,
            'discount_amount' => $discountAmount,
            'taxable_amount' => $taxableAmount,
            'taxes' => $taxes,
            'tax_total' => $taxTotal,
            'grand_total' => $grandTotal,
            'existing_patient_payment' => $existingPatientPaid,
            'existing_insurance_payment' => $existingInsurancePaid,
            'incoming_patient_payment' => $incomingPatientPaid,
            'incoming_insurance_payment' => $incomingInsurancePaid,
            'patient_payment' => $patientPayment,
            'insurance_payment' => $insurancePayment,
            'total_paid' => $totalPaid,
            'balance' => $balance,
            'is_fully_paid' => false,
            'billing_status' => $billingStatus,
            'payment_status' => 'pending',
            'ui_status' => $requestedUiStatus === 'draft' ? 'draft' : 'ready',
        ];
    }

    public function checkExistingBilling(int $visitId, int $facilityId): array
    {
        $editableBillingCycle = BillingCycle::query()
            ->where('visit_id', $visitId)
            ->where('facility_id', $facilityId)
            ->whereIn('billing_status', ['draft', 'pending', 'partially_paid'])
            ->latest('id')
            ->first();

        if ($editableBillingCycle) {
            return [
                'success' => true,
                'message' => 'Editable billing cycle found for this visit.',
                'data' => $editableBillingCycle,
                'is_existing_editable' => true,
            ];
        }

        $lockedBillingCycle = BillingCycle::query()
            ->where('visit_id', $visitId)
            ->where('facility_id', $facilityId)
            ->whereIn('billing_status', [
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
            ])
            ->latest('id')
            ->first();

        if ($lockedBillingCycle) {
            return [
                'success' => false,
                'message' => 'This visit already has a locked billing cycle.',
                'errors' => [
                    'billing' => [
                        "Billing cycle already exists in status '{$lockedBillingCycle->billing_status}' and cannot be modified.",
                    ],
                ],
            ];
        }

        return [
            'success' => true,
            'message' => 'No existing billing cycle found.',
            'data' => null,
            'is_existing_editable' => false,
        ];
    }

    public function calculateDiscountAmount(string $type, float $value, float $subtotal): float
    {
        $subtotal = round(max(0, $subtotal), 2);
        $value = round(max(0, $value), 2);

        $amount = $type === 'percentage'
            ? $subtotal * ($value / 100)
            : $value;

        return round(min($subtotal, max(0, $amount)), 2);
    }

    public function isInsuranceInvolved(array $paymentMethods): bool
    {
        return collect($paymentMethods)->contains(function ($method) {
            return (string) ($method['type'] ?? '') === 'insurance';
        });
    }

    public function calculatePaymentSplit(array $paymentMethods, float $totalPaid = 0): array
    {
        $paymentMethods = $this->normalizePaymentMethods($paymentMethods);

        $insurancePayment = round((float) collect($paymentMethods)
            ->filter(fn (array $method) => ($method['type'] ?? null) === 'insurance')
            ->sum('amount'), 2);

        $patientPayment = round((float) collect($paymentMethods)
            ->reject(fn (array $method) => ($method['type'] ?? null) === 'insurance')
            ->sum('amount'), 2);

        $calculatedTotal = round($insurancePayment + $patientPayment, 2);
        $providedTotal = round(max(0, $totalPaid), 2);

        if ($providedTotal > 0 && abs($calculatedTotal - $providedTotal) > 0.01) {
            Log::warning('Payment total mismatch detected; backend payment split will be authoritative.', [
                'provided_total_paid' => $providedTotal,
                'calculated_total' => $calculatedTotal,
                'insurance_payment' => $insurancePayment,
                'patient_payment' => $patientPayment,
            ]);
        }

        return [
            'insurance_payment' => $insurancePayment,
            'patient_payment' => $patientPayment,
            'total_paid' => $calculatedTotal,
        ];
    }

    public function mapBillingStatusToUI(string $billingStatus): string
    {
        $statusMap = [
            'draft' => 'draft',
            'pending' => 'ready',
            'pending_review' => 'draft',
            'pending_submission' => 'ready',
            'submitted_to_insurance' => 'ready',
            'partially_paid' => 'ready',
            'paid_in_full' => 'settled',
            'payment_plan' => 'ready',
            'collections' => 'ready',
            'disputed' => 'ready',
            'written_off' => 'settled',
            'charity_care' => 'settled',
        ];

        return $statusMap[$billingStatus] ?? 'draft';
    }

    /**
     * Line-item discounts are intentionally unsupported.
     *
     * We keep this method for compatibility, but it always returns zero because
     * the rewrite enforces discount ownership at the billing cycle level.
     */
    public function calculateLineItemDiscount(float $lineTotal, float $subtotal, float $totalDiscount): float
    {
        return 0.0;
    }

    public function normalizeChargeItems(array $chargeItems): array
    {
        return collect($chargeItems)
            ->map(function ($chargeItem) {
                $service = is_array($chargeItem['service'] ?? null) ? $chargeItem['service'] : [];

                $quantity = round(max(0, (float) ($chargeItem['quantity'] ?? 0)), 2);
                $unitPrice = round(max(0, (float) ($service['unitPrice'] ?? 0)), 2);
                $lineTotal = round($quantity * $unitPrice, 2);
                $serviceKey = (string) ($chargeItem['service_key'] ?? $chargeItem['serviceKey'] ?? '');

                return [
                    'service_key' => $serviceKey,
                    'serviceKey' => $serviceKey,
                    'service' => [
                        'id' => $service['id'] ?? null,
                        'code' => trim((string) ($service['code'] ?? '')),
                        'name' => trim((string) ($service['name'] ?? '')),
                        'unitPrice' => $unitPrice,
                        'category' => trim((string) ($service['category'] ?? 'General')),
                    ],
                    'quantity' => $quantity,
                    'totalAmount' => $lineTotal,
                ];
            })
            ->filter(function (array $item) {
                return $item['quantity'] > 0
                    && !empty($item['service']['code'])
                    && !empty($item['service']['name']);
            })
            ->values()
            ->all();
    }

    public function normalizePaymentMethods(array $paymentMethods): array
    {
        return collect($paymentMethods)
            ->map(function ($method) {
                $type = trim((string) ($method['type'] ?? ''));
                if ($type === 'mobile') {
                    $type = 'mobile_money';
                }

                return [
                    'type' => $type,
                    'amount' => round(max(0, (float) ($method['amount'] ?? 0)), 2),
                    'reference' => isset($method['reference']) ? trim((string) $method['reference']) : null,
                    'details' => $method['details'] ?? null,
                ];
            })
            ->filter(function (array $method) {
                return $method['type'] !== '' && $method['amount'] > 0;
            })
            ->values()
            ->all();
    }

    public function normalizeDiscount(array $discount): array
    {
        $type = (string) ($discount['type'] ?? 'fixed');
        if (!in_array($type, ['percentage', 'fixed'], true)) {
            $type = 'fixed';
        }

        return [
            'type' => $type,
            'value' => round(max(0, (float) ($discount['value'] ?? 0)), 2),
            'reason' => isset($discount['reason']) && trim((string) $discount['reason']) !== ''
                ? trim((string) $discount['reason'])
                : null,
        ];
    }

    public function normalizeTaxDefinitions(array $taxes): array
    {
        return collect($taxes)
            ->map(function ($tax) {
                return [
                    'name' => trim((string) ($tax['name'] ?? 'Tax')),
                    'rate' => round(max(0, (float) ($tax['rate'] ?? 0)), 2),
                    'amount' => round(max(0, (float) ($tax['amount'] ?? 0)), 2),
                ];
            })
            ->filter(function (array $tax) {
                return $tax['name'] !== '' && $tax['rate'] >= 0;
            })
            ->values()
            ->all();
    }

    public function buildAuthoritativeBillingData(
        array $chargeItems,
        array $discount = [],
        array $taxes = [],
        array $paymentMethods = [],
        string $requestedUiStatus = 'ready',
        ?BillingCycle $existingBillingCycle = null
    ): array {
        $subtotal = round((float) collect($this->normalizeChargeItems($chargeItems))->sum('totalAmount'), 2);
        $paymentSplit = $this->calculatePaymentSplit($paymentMethods);

        $state = $this->determineBillingState(
            $subtotal,
            $discount,
            $taxes,
            $paymentSplit,
            $requestedUiStatus,
            $existingBillingCycle
        );

        return $this->buildBillingDataFromState($state);
    }

    public function getActiveBillingCycleLineItems(?BillingCycle $billingCycle): Collection
    {
        if (!$billingCycle) {
            return collect();
        }

        $lineItems = $billingCycle->relationLoaded('lineItems')
            ? $billingCycle->lineItems
            : $billingCycle->lineItems()->get();

        return collect($lineItems)
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
    }

    public function getActiveBillingCycleSubtotal(?BillingCycle $billingCycle): float
    {
        return round((float) $this->getActiveBillingCycleLineItems($billingCycle)
            ->sum(function ($lineItem) {
                return (float) ($lineItem->line_total_amount ?? 0);
            }), 2);
    }

    protected function buildAuthoritativeBillingDataForSave(
        array $incomingChargeItems,
        ?BillingCycle $existingBillingCycle,
        array $discount = [],
        array $taxes = [],
        array $paymentMethods = [],
        string $requestedUiStatus = 'ready'
    ): array {
        $incomingSubtotal = round((float) collect($this->normalizeChargeItems($incomingChargeItems))->sum('totalAmount'), 2);
        $persistedSubtotal = $this->getActiveBillingCycleSubtotal($existingBillingCycle);
        $combinedSubtotal = round($persistedSubtotal + $incomingSubtotal, 2);
        $paymentSplit = $this->calculatePaymentSplit($paymentMethods);

        $state = $this->determineBillingState(
            $combinedSubtotal,
            $discount,
            $taxes,
            $paymentSplit,
            $requestedUiStatus,
            $existingBillingCycle
        );

        Log::info('Authoritative billing state computed for save.', [
            'persisted_subtotal' => $persistedSubtotal,
            'incoming_subtotal' => $incomingSubtotal,
            'combined_subtotal' => $combinedSubtotal,
            'grand_total' => $state['grand_total'],
            'total_paid' => $state['total_paid'],
            'balance' => $state['balance'],
            'billing_status' => $state['billing_status'],
        ]);

        return $state;
    }

    public function buildBillingDataFromState(array $state): array
    {
        return [
            'subtotal' => round((float) ($state['subtotal'] ?? 0), 2),
            'discountAmount' => round((float) ($state['discount_amount'] ?? 0), 2),
            'taxableAmount' => round((float) ($state['taxable_amount'] ?? 0), 2),
            'taxes' => array_values($state['taxes'] ?? []),
            'taxTotal' => round((float) ($state['tax_total'] ?? 0), 2),
            'grandTotal' => round((float) ($state['grand_total'] ?? 0), 2),
            'totalPaid' => round((float) ($state['total_paid'] ?? 0), 2),
            'balance' => round((float) ($state['balance'] ?? 0), 2),
            'isPaid' => (bool) ($state['is_fully_paid'] ?? false),
        ];
    }

    public function billingDataMismatch(array $provided, array $state): bool
    {
        $computed = $this->buildBillingDataFromState($state);
        $keys = ['subtotal', 'discountAmount', 'taxableAmount', 'taxTotal', 'grandTotal', 'totalPaid', 'balance'];

        foreach ($keys as $key) {
            $providedValue = round((float) ($provided[$key] ?? 0), 2);
            $computedValue = round((float) ($computed[$key] ?? 0), 2);

            if (abs($providedValue - $computedValue) > 0.01) {
                return true;
            }
        }

        return false;
    }

    public function buildBillingSubmissionFingerprint(array $data, int $facilityId): string
    {
        $normalizedChargeItems = collect($this->normalizeChargeItems($data['charge_items'] ?? []))
            ->map(function ($item) {
                $service = $item['service'] ?? [];

                return [
                    'service_key' => (string) ($item['service_key'] ?? $item['serviceKey'] ?? ''),
                    'service_code' => (string) ($service['code'] ?? ''),
                    'quantity' => round((float) ($item['quantity'] ?? 0), 2),
                    'unit_price' => round((float) ($service['unitPrice'] ?? 0), 2),
                    'total_amount' => round((float) ($item['totalAmount'] ?? 0), 2),
                ];
            })
            ->sortBy(fn ($item) => implode('|', $item))
            ->values()
            ->all();

        $normalizedPaymentMethods = collect($this->normalizePaymentMethods($data['payment_methods'] ?? []))
            ->map(function ($method) {
                return [
                    'type' => (string) ($method['type'] ?? ''),
                    'amount' => round((float) ($method['amount'] ?? 0), 2),
                    'reference' => trim((string) ($method['reference'] ?? '')),
                    'details' => is_scalar($method['details'] ?? null)
                        ? trim((string) $method['details'])
                        : null,
                ];
            })
            ->sortBy(fn ($method) => implode('|', [
                $method['type'],
                $method['amount'],
                $method['reference'],
                $method['details'],
            ]))
            ->values()
            ->all();

        $normalizedTaxes = collect($this->normalizeTaxDefinitions($data['taxes'] ?? []))
            ->map(function ($tax) {
                return [
                    'name' => strtolower(trim((string) ($tax['name'] ?? 'Tax'))),
                    'rate' => round((float) ($tax['rate'] ?? 0), 2),
                ];
            })
            ->sortBy(fn ($tax) => $tax['name'] . '|' . $tax['rate'])
            ->values()
            ->all();

        $normalizedDiscount = $this->normalizeDiscount($data['discount'] ?? []);
        $normalizedAdditionalNotes = trim((string) ($data['additional_notes'] ?? ''));
        $explicitIdempotencyKey = trim((string) ($data['idempotency_key'] ?? ''));

        if ($explicitIdempotencyKey !== '') {
            return hash('sha256', 'explicit:' . $facilityId . ':' . $explicitIdempotencyKey);
        }

        return hash('sha256', json_encode([
            'facility_id' => (int) $facilityId,
            'visit_id' => (int) ($data['visit_id'] ?? 0),
            'patient_id' => (int) ($data['patient_id'] ?? 0),
            'charge_items' => $normalizedChargeItems,
            'discount' => $normalizedDiscount,
            'taxes' => $normalizedTaxes,
            'payment_methods' => $normalizedPaymentMethods,
            'additional_notes' => $normalizedAdditionalNotes,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    public function findReplaySubmission(
        int $visitId,
        int $facilityId,
        string $fingerprint,
        int $windowSeconds = 300
    ): ?BillingCycle {
        $recentCycles = BillingCycle::query()
            ->where('visit_id', $visitId)
            ->where('facility_id', $facilityId)
            ->latest('id')
            ->limit(5)
            ->get();

        foreach ($recentCycles as $cycle) {
            $metadata = $this->decodeJsonishToArray($cycle->metadata ?? null);
            $storedFingerprint = (string) ($metadata['last_submission_fingerprint'] ?? '');

            if ($storedFingerprint === '' || $storedFingerprint !== $fingerprint) {
                continue;
            }

            $storedAt = $metadata['last_submission_at'] ?? null;
            $storedTs = $storedAt ? strtotime((string) $storedAt) : null;
            $fallbackTs = $cycle->updated_at ? strtotime((string) $cycle->updated_at) : null;
            $comparisonTs = $storedTs ?: $fallbackTs;

            if ($comparisonTs && (time() - $comparisonTs) <= $windowSeconds) {
                return $cycle;
            }
        }

        return null;
    }

    public function verifyVisit(int $visitId, int $facilityId, int $patientId): array
    {
        $visit = Visit::query()
            ->where('id', $visitId)
            ->where('facility_id', $facilityId)
            ->where('patient_id', $patientId)
            ->first();

        if (!$visit) {
            return [
                'success' => false,
                'message' => 'Visit not found or does not belong to this facility/patient.',
                'errors' => ['visit_id' => ['Invalid visit for the given facility and patient.']],
            ];
        }

        return [
            'success' => true,
            'data' => $visit,
        ];
    }

    public function decodeJsonishToArray($value): array
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
}