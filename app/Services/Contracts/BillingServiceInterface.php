<?php

namespace App\Services\Contracts;

/**
 * Billing Service Interface
 *
 * Defines the contract for billing operations
 */
interface BillingServiceInterface
{
    /**
     * Finalize billing and persist to database
     *
     * @param array $data Validated billing data
     * @param int $facilityId Facility ID
     * @param int $staffId Staff ID performing the operation
     * @return array Success status and result data
     */
    public function finalizeBilling(array $data, int $facilityId, int $staffId): array;

    /**
     * Get billing data for a visit
     *
     * @param int $visitId Visit ID
     * @param int $facilityId Facility ID
     * @return array Success status and billing data
     */
    public function getBillingByVisit(int $visitId, int $facilityId): array;

    /**
     * Verify visit belongs to facility and patient
     *
     * @param int $visitId Visit ID
     * @param int $facilityId Facility ID
     * @param int $patientId Patient ID
     * @return array Success status and visit data
     */
    public function verifyVisit(int $visitId, int $facilityId, int $patientId): array;

    /**
     * Calculate discount amount based on type
     *
     * @param string $type Discount type (percentage or fixed)
     * @param float $value Discount value
     * @param float $subtotal Subtotal amount
     * @return float Calculated discount amount
     */
    public function calculateDiscountAmount(string $type, float $value, float $subtotal): float;

    /**
     * Determine if insurance is involved in payment methods
     *
     * @param array $paymentMethods Payment methods array
     * @return bool True if insurance is involved
     */
    public function isInsuranceInvolved(array $paymentMethods): bool;

    /**
     * Calculate insurance and patient payments
     *
     * @param array $paymentMethods Payment methods array
     * @param float $totalPaid Total amount paid
     * @return array Insurance and patient payment amounts
     */
    public function calculatePaymentSplit(array $paymentMethods, float $totalPaid): array;

    /**
     * Map database billing status to UI status
     *
     * @param string $billingStatus Database billing status
     * @return string UI status (draft, ready, settled)
     */
    public function mapBillingStatusToUI(string $billingStatus): string;

    /**
     * Calculate pro-rated discount for a line item
     *
     * @param float $lineTotal Line item total
     * @param float $subtotal Overall subtotal
     * @param float $totalDiscount Total discount amount
     * @return float Pro-rated discount for this line item
     */
    public function calculateLineItemDiscount(float $lineTotal, float $subtotal, float $totalDiscount): float;
}
