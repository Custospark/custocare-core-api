<?php

namespace App\Services\Billing\Traits;

use App\Models\BillingCycle;
use App\Models\Visit;
use Illuminate\Support\Facades\Log;

trait BillingHelpers
{
    public function canBeBilled(Visit $visit): array
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
                'message' => 'Cannot bill a visit that is already ' . $visit->current_phase . '.',
                'errors' => ['visit' => ['This visit is already in a terminal phase.']],
            ];
        }

        if ($visit->payment_status === 'paid_in_full') {
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

    public function determineBillingState(
        float $grandTotal,
        float $totalPaid,
        string $requestedUiStatus = 'ready'
    ): array {
        $grandTotal = round(max(0, $grandTotal), 2);
        $totalPaid = round(max(0, $totalPaid), 2);
        $balance = round(max(0, $grandTotal - $totalPaid), 2);
        $isFullyPaid = abs($balance) < 0.01;

        if ($isFullyPaid) {
            return [
                'billing_status' => 'paid_in_full',
                'payment_status' => 'paid_in_full',
                'ui_status' => 'settled',
                'total_paid' => $totalPaid,
                'balance' => $balance,
                'is_fully_paid' => true,
            ];
        }

        if ($totalPaid > 0) {
            return [
                'billing_status' => 'partially_paid',
                'payment_status' => 'partially_paid',
                'ui_status' => 'ready',
                'total_paid' => $totalPaid,
                'balance' => $balance,
                'is_fully_paid' => false,
            ];
        }

        return [
            'billing_status' => 'pending',
            'payment_status' => 'pending',
            'ui_status' => $requestedUiStatus === 'draft' ? 'draft' : 'ready',
            'total_paid' => 0.00,
            'balance' => $balance,
            'is_fully_paid' => false,
        ];
    }

    public function checkExistingBilling(int $visitId, int $facilityId): array
    {
        $editableBillingCycle = BillingCycle::query()
            ->where('visit_id', $visitId)
            ->where('facility_id', $facilityId)
            ->whereIn('billing_status', ['pending', 'partially_paid'])
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
                        "Billing cycle already exists in status '{$lockedBillingCycle->billing_status}' and cannot be modified."
                    ]
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
        return collect($paymentMethods)->contains('type', 'insurance');
    }

    public function calculatePaymentSplit(array $paymentMethods, float $totalPaid): array
    {
        $insurancePayment = round((float) collect($paymentMethods)
            ->where('type', 'insurance')
            ->sum('amount'), 2);

        $patientPayment = round((float) collect($paymentMethods)
            ->where('type', '!=', 'insurance')
            ->sum('amount'), 2);

        $calculatedTotal = round($insurancePayment + $patientPayment, 2);
        $providedTotal = round((float) $totalPaid, 2);

        if (abs($calculatedTotal - $providedTotal) > 0.01) {
            Log::warning('Payment total mismatch detected in calculatePaymentSplit', [
                'provided_total_paid' => $providedTotal,
                'calculated_total' => $calculatedTotal,
                'insurance_payment' => $insurancePayment,
                'patient_payment' => $patientPayment,
                'payment_methods' => $paymentMethods,
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

    public function calculateLineItemDiscount(float $lineTotal, float $subtotal, float $totalDiscount): float
    {
        if ($subtotal <= 0 || $lineTotal <= 0 || $totalDiscount <= 0) {
            return 0.0;
        }

        return round(($lineTotal / $subtotal) * $totalDiscount, 2);
    }

    public function normalizeChargeItems(array $chargeItems): array
    {
        return collect($chargeItems)->map(function ($chargeItem) {
            $service = $chargeItem['service'] ?? [];

            $quantity = round((float) ($chargeItem['quantity'] ?? 0), 2);
            $unitPrice = round((float) ($service['unitPrice'] ?? 0), 2);
            $lineTotal = round($quantity * $unitPrice, 2);

            $chargeItem['quantity'] = $quantity;
            $chargeItem['totalAmount'] = $lineTotal;
            $chargeItem['service']['unitPrice'] = $unitPrice;

            if (!isset($chargeItem['service_key']) && isset($chargeItem['serviceKey'])) {
                $chargeItem['service_key'] = $chargeItem['serviceKey'];
            }

            if (!isset($chargeItem['serviceKey']) && isset($chargeItem['service_key'])) {
                $chargeItem['serviceKey'] = $chargeItem['service_key'];
            }

            return $chargeItem;
        })->values()->all();
    }

    public function normalizePaymentMethods(array $paymentMethods): array
    {
        return collect($paymentMethods)
            ->map(function ($method) {
                $type = (string) ($method['type'] ?? '');

                if ($type === 'mobile') {
                    $type = 'mobile_money';
                }

                return [
                    'type' => $type,
                    'amount' => round((float) ($method['amount'] ?? 0), 2),
                    'reference' => $method['reference'] ?? null,
                    'details' => $method['details'] ?? null,
                ];
            })
            ->filter(function ($method) {
                return !empty($method['type']) || ((float) ($method['amount'] ?? 0)) > 0;
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
            'reason' => isset($discount['reason']) ? trim((string) $discount['reason']) : null,
        ];
    }

    public function normalizeTaxDefinitions(array $taxes): array
    {
        return collect($taxes)->map(function ($tax) {
            return [
                'name' => trim((string) ($tax['name'] ?? 'Tax')),
                'rate' => round(max(0, (float) ($tax['rate'] ?? 0)), 2),
                'amount' => round(max(0, (float) ($tax['amount'] ?? 0)), 2),
            ];
        })->values()->all();
    }

    public function buildAuthoritativeBillingData(
        array $chargeItems,
        array $discount = [],
        array $taxes = []
    ): array {
        $subtotal = round(
            (float) collect($chargeItems)->sum(function ($item) {
                return (float) ($item['totalAmount'] ?? 0);
            }),
            2
        );

        $discount = $this->normalizeDiscount($discount);
        $discountAmount = $this->calculateDiscountAmount(
            $discount['type'],
            (float) $discount['value'],
            $subtotal
        );

        $taxableAmount = round(max(0, $subtotal - $discountAmount), 2);

        $normalizedTaxes = collect($this->normalizeTaxDefinitions($taxes))
            ->map(function ($tax) use ($taxableAmount) {
                return [
                    'name' => $tax['name'],
                    'rate' => $tax['rate'],
                    'amount' => round($taxableAmount * ($tax['rate'] / 100), 2),
                ];
            })
            ->values()
            ->all();

        $taxTotal = round((float) collect($normalizedTaxes)->sum('amount'), 2);
        $grandTotal = round($taxableAmount + $taxTotal, 2);

        return [
            'subtotal' => $subtotal,
            'discountAmount' => $discountAmount,
            'taxableAmount' => $taxableAmount,
            'taxes' => $normalizedTaxes,
            'taxTotal' => $taxTotal,
            'grandTotal' => $grandTotal,
        ];
    }

    public function getActiveBillingCycleLineItems(?BillingCycle $billingCycle)
    {
        if (!$billingCycle) {
            return collect();
        }

        $lineItems = $billingCycle->relationLoaded('lineItems')
            ? $billingCycle->lineItems
            : $billingCycle->lineItems()->get();

        return collect($lineItems)->filter(function ($lineItem) {
            $quantity = (float) ($lineItem->quantity ?? 0);
            $status = strtolower((string) ($lineItem->line_item_status ?? ''));

            return $quantity > 0 && !in_array($status, [
                'removed',
                'voided',
                'cancelled',
                'deleted',
                // 'adjusted',
            ], true);
        })->values();
    }

    public function getActiveBillingCycleSubtotal(?BillingCycle $billingCycle): float
    {
        return round((float) $this->getActiveBillingCycleLineItems($billingCycle)
            ->sum(function ($lineItem) {
                return (float) ($lineItem->line_total_amount ?? 0);
            }), 2);
    }

    public function buildAuthoritativeBillingDataForSave(
        array $incomingChargeItems,
        ?BillingCycle $existingBillingCycle,
        array $discount = [],
        array $taxes = []
    ): array {
        $incomingSubtotal = round((float) collect($incomingChargeItems)->sum(function ($item) {
            return (float) ($item['totalAmount'] ?? 0);
        }), 2);

        $persistedSubtotal = $this->getActiveBillingCycleSubtotal($existingBillingCycle);
        $combinedSubtotal = round($persistedSubtotal + $incomingSubtotal, 2);

        $discount = $this->normalizeDiscount($discount);
        $normalizedTaxes = $this->normalizeTaxDefinitions($taxes);

        $discountAmount = $this->calculateDiscountAmount(
            $discount['type'],
            (float) $discount['value'],
            $combinedSubtotal
        );

        $taxableAmount = round(max(0, $combinedSubtotal - $discountAmount), 2);

        $computedTaxes = collect($normalizedTaxes)->map(function ($tax) use ($taxableAmount) {
            return [
                'name' => $tax['name'],
                'rate' => $tax['rate'],
                'amount' => round($taxableAmount * ($tax['rate'] / 100), 2),
            ];
        })->values()->all();

        $taxTotal = round((float) collect($computedTaxes)->sum('amount'), 2);
        $grandTotal = round($taxableAmount + $taxTotal, 2);

        return [
            'subtotal' => $combinedSubtotal,
            'discountAmount' => $discountAmount,
            'taxableAmount' => $taxableAmount,
            'taxes' => $computedTaxes,
            'taxTotal' => $taxTotal,
            'grandTotal' => $grandTotal,
        ];
    }

    public function billingDataMismatch(array $provided, array $computed): bool
    {
        $keys = ['subtotal', 'discountAmount', 'taxableAmount', 'taxTotal', 'grandTotal'];

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
            ->sortBy(function ($item) {
                return implode('|', [
                    $item['service_key'],
                    $item['service_code'],
                    number_format($item['quantity'], 2, '.', ''),
                    number_format($item['unit_price'], 2, '.', ''),
                    number_format($item['total_amount'], 2, '.', ''),
                ]);
            })
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
            ->sortBy(function ($method) {
                return implode('|', [
                    $method['type'],
                    number_format($method['amount'], 2, '.', ''),
                    $method['reference'] ?? '',
                    $method['details'] ?? '',
                ]);
            })
            ->values()
            ->all();

        $normalizedTaxes = collect($this->normalizeTaxDefinitions($data['taxes'] ?? []))
            ->map(function ($tax) {
                return [
                    'name' => strtolower(trim((string) ($tax['name'] ?? 'Tax'))),
                    'rate' => round((float) ($tax['rate'] ?? 0), 2),
                ];
            })
            ->sortBy(function ($tax) {
                return $tax['name'] . '|' . number_format($tax['rate'], 2, '.', '');
            })
            ->values()
            ->all();

        $normalizedDiscount = $this->normalizeDiscount($data['discount'] ?? []);
        $normalizedAdditionalNotes = trim((string) ($data['additional_notes'] ?? ''));

        $explicitIdempotencyKey = trim((string) ($data['idempotency_key'] ?? ''));
        if ($explicitIdempotencyKey !== '') {
            return hash('sha256', 'explicit:' . $facilityId . ':' . $explicitIdempotencyKey);
        }

        $fingerprintPayload = [
            'facility_id' => (int) $facilityId,
            'visit_id' => (int) ($data['visit_id'] ?? 0),
            'patient_id' => (int) ($data['patient_id'] ?? 0),
            'charge_items' => $normalizedChargeItems,
            'discount' => [
                'type' => $normalizedDiscount['type'],
                'value' => round((float) $normalizedDiscount['value'], 2),
                'reason' => trim((string) ($normalizedDiscount['reason'] ?? '')),
            ],
            'taxes' => $normalizedTaxes,
            'payment_methods' => $normalizedPaymentMethods,
            'additional_notes' => $normalizedAdditionalNotes,
        ];

        return hash('sha256', json_encode($fingerprintPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
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
}