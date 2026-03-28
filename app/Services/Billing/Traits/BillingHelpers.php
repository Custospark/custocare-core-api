<?php

namespace App\Services\Billing\Traits;

use App\Models\Visit;
use App\Models\BillingCycle;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Billing Helpers Trait
 *
 * Contains utility and helper methods for billing operations
 */
trait BillingHelpers
{
    public function canBeBilled(Visit $visit): array
{
    // Cancelled visits should never be billable
    if ($visit->status === 'cancelled') {
        return [
            'success' => false,
            'message' => 'Cannot bill a cancelled visit.',
            'errors' => ['visit' => ['This visit has been cancelled and cannot be billed.']],
        ];
    }

    // Only truly terminal operational states should block billing
    // NOTE:
    // - discharged is intentionally NOT blocked here because billing may still continue
    // - completed is also NOT blocked if payment is still pending/partial (legacy correction case)
    if (in_array($visit->current_phase, ['expired', 'transferred'], true)) {
        return [
            'success' => false,
            'message' => 'Cannot bill a visit that is already ' . $visit->current_phase . '.',
            'errors' => ['visit' => ['This visit is already in a terminal phase.']],
        ];
    }

    // Fully settled visits should not be billed again
    if ($visit->payment_status === 'paid_in_full') {
        return [
            'success' => false,
            'message' => 'Visit payment is already settled.',
            'errors' => ['visit' => ['This visit has already been paid in full.']],
        ];
    }

    // IMPORTANT:
    // If a visit was incorrectly marked completed while money is still pending/partial,
    // allow billing to continue so we can correct the financial state.
    return [
        'success' => true,
        'message' => 'Visit is eligible for billing.',
    ];
}

    /**
     * Determine authoritative billing/payment state from actual money values.
     *
     * Rules:
     * - pending        => total paid is exactly zero
     * - partially_paid => total paid is greater than zero but balance remains
     * - paid_in_full   => balance is zero
     *
     * @param float $grandTotal
     * @param float $totalPaid
     * @param string $requestedUiStatus
     * @return array
     */
    public function determineBillingState(
        float $grandTotal,
        float $totalPaid,
        string $requestedUiStatus = 'ready'
    ): array {
        $balance = max(0, round($grandTotal - $totalPaid, 2));
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
            // IMPORTANT: zero payment must remain pending, never partially_paid
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
    // Editable/open cycle: reuse it instead of blocking
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

    // Locked/finalized states should still block mutation
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


    /**
     * Calculate discount amount based on type
     *
     * @param string $type Discount type (percentage or fixed)
     * @param float $value Discount value
     * @param float $subtotal Subtotal amount
     * @return float Calculated discount amount
     */
    public function calculateDiscountAmount(string $type, float $value, float $subtotal): float
    {
        return $type === 'percentage'
            ? $subtotal * ($value / 100)
            : $value;
    }

    /**
     * Determine if insurance is involved in payment methods
     *
     * @param array $paymentMethods Payment methods array
     * @return bool True if insurance is involved
     */
    public function isInsuranceInvolved(array $paymentMethods): bool
    {
        return collect($paymentMethods)->contains('type', 'insurance');
    }

    /**
     * Calculate insurance and patient payments
     * 
     * FIXED: Properly calculate payment split by summing actual payment methods
     * instead of relying on totalPaid which might be inconsistent
     *
     * @param array $paymentMethods Payment methods array
     * @param float $totalPaid Total amount paid (for validation)
     * @return array Insurance and patient payment amounts
     */
    public function calculatePaymentSplit(array $paymentMethods, float $totalPaid): array
    {
        // Calculate insurance payment by summing all insurance payment methods
        $insurancePayment = collect($paymentMethods)
            ->where('type', 'insurance')
            ->sum('amount');
        
        // Calculate patient payment by summing all non-insurance payment methods
        $patientPayment = collect($paymentMethods)
            ->where('type', '!=', 'insurance')
            ->sum('amount');
        
        // Calculate total from payment methods for validation
        $calculatedTotal = $insurancePayment + $patientPayment;
        
        // If there's a mismatch, log it and use the calculated total
        if (abs($calculatedTotal - $totalPaid) > 0.01) {
            Log::warning('Payment total mismatch detected in calculatePaymentSplit', [
                'provided_total_paid' => $totalPaid,
                'calculated_total' => $calculatedTotal,
                'insurance_payment' => $insurancePayment,
                'patient_payment' => $patientPayment,
                'payment_methods' => $paymentMethods,
                'difference' => $calculatedTotal - $totalPaid,
            ]);
            
            // Use the calculated total from payment methods for consistency
            // This ensures we store what was actually paid according to payment methods
            $totalPaid = $calculatedTotal;
        }

        return [
            'insurance_payment' => $insurancePayment,
            'patient_payment' => $patientPayment,
            'total_paid' => $calculatedTotal, // Add the validated total
        ];
    }

    /**
     * Map database billing status to UI status
     *
     * @param string $billingStatus Database billing status
     * @return string UI status (draft, ready, settled)
     */
    public function mapBillingStatusToUI(string $billingStatus): string
    {
       $statusMap = [
        'draft' => 'draft',
        'pending' => 'ready',             // unpaid bill but already created
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
     * Calculate pro-rated discount for a line item
     *
     * @param float $lineTotal Line item total
     * @param float $subtotal Overall subtotal
     * @param float $totalDiscount Total discount amount
     * @return float Pro-rated discount for this line item
     */
    public function calculateLineItemDiscount(float $lineTotal, float $subtotal, float $totalDiscount): float
    {
        if ($subtotal <= 0) {
            return 0;
        }

        return ($lineTotal / $subtotal) * $totalDiscount;
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
            return collect($paymentMethods)->map(function ($method) {
                $type = (string) ($method['type'] ?? '');

                if ($type === 'mobile') {
                    $type = 'mobile_money';
                }

                $method['type'] = $type;
                $method['amount'] = round((float) ($method['amount'] ?? 0), 2);

                return $method;
            })->filter(function ($method) {
                return !empty($method['type']) || ((float) ($method['amount'] ?? 0)) > 0;
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

            $discountType = (string) ($discount['type'] ?? 'fixed');
            $discountValue = (float) ($discount['value'] ?? 0);

            $discountAmount = round(
                min($subtotal, $this->calculateDiscountAmount($discountType, $discountValue, $subtotal)),
                2
            );

            $taxableAmount = round(max(0, $subtotal - $discountAmount), 2);

            $normalizedTaxes = collect($taxes)->map(function ($tax) use ($taxableAmount) {
                $rate = round((float) ($tax['rate'] ?? 0), 2);

                return [
                    'name' => (string) ($tax['name'] ?? 'Tax'),
                    'rate' => $rate,
                    'amount' => round($taxableAmount * ($rate / 100), 2),
                ];
            })->values()->all();

            $taxTotal = round(
                (float) collect($normalizedTaxes)->sum(function ($tax) {
                    return (float) ($tax['amount'] ?? 0);
                }),
                2
            );

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


    /**
     * Verify visit belongs to facility and patient
     *
     * @param int $visitId Visit ID
     * @param int $facilityId Facility ID
     * @param int $patientId Patient ID
     * @return array Success status and visit data
     */
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