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
    /**
     * Check if a visit can be billed
     *
     * @param Visit $visit
     * @return array Success status and message
     */
    public function canBeBilled(Visit $visit): array
    {
        // Check if visit is already completed
        if ($visit->status === 'completed') {
            return [
                'success' => false,
                'message' => 'Cannot bill a completed visit.',
                'errors' => ['visit' => ['This visit has already been completed and cannot be billed again.']],
            ];
        }

        // Check if visit is cancelled
        if ($visit->status === 'cancelled') {
            return [
                'success' => false,
                'message' => 'Cannot bill a cancelled visit.',
                'errors' => ['visit' => ['This visit has been cancelled and cannot be billed.']],
            ];
        }

        // Check if visit is in terminal phase
        $terminalPhases = ['discharged', 'expired', 'transferred'];
        if (in_array($visit->current_phase, $terminalPhases)) {
            return [
                'success' => false,
                'message' => 'Cannot bill a visit that is already ' . $visit->current_phase . '.',
                'errors' => ['visit' => ['This visit is already in a terminal phase.']],
            ];
        }

        // Check payment status - if already paid in full, prevent re-billing
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

    /**
     * Check if visit already has an existing billing cycle
     *
     * @param int $visitId
     * @param int $facilityId
     * @return array Success status and message
     */
    public function checkExistingBilling(int $visitId, int $facilityId): array
    {
        $existingBillingCycle = BillingCycle::query()
            ->where('visit_id', $visitId)
            ->where('facility_id', $facilityId)
            ->whereIn('billing_status', ['paid_in_full', 'submitted_to_insurance', 'pending_submission'])
            ->first();

        if ($existingBillingCycle) {
            return [
                'success' => false,
                'message' => 'This visit already has an active billing cycle.',
                'errors' => ['billing' => ['A billing cycle already exists for this visit.']],
            ];
        }

        return [
            'success' => true,
            'message' => 'No existing billing cycle found.',
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