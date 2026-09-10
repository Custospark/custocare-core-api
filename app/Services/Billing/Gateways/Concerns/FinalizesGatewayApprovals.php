<?php

declare(strict_types=1);

namespace App\Services\Billing\Gateways\Concerns;

use App\Enums\Billing\PaymentType;
use App\Models\Payment;
use App\Models\Plan;
use App\Services\Billing\Gateways\Exceptions\GatewayException;
use Illuminate\Support\Facades\Log;

/**
 * FinalizesGatewayApprovals
 *
 * Shared post-approval steps for every gateway approval path (webhook,
 * status-poll verification, local bypass): invoice + receipt, then the
 * subscription transition for the payment type.
 *
 * One path, no drift. Callers must already run inside a DB transaction -
 * subscription and status changes commit atomically with the payment
 * approval, never independently (see vera-logic rule gateway-atomic-approval).
 *
 * Requires the using class to expose:
 *   - $this->billingDocuments (SubscriptionBillingDocumentServiceInterface)
 *   - $this->subscriptionService (SubscriptionServiceInterface)
 *   - $this->fxService (CurrencyExchangeServiceInterface)
 */
trait FinalizesGatewayApprovals
{
    private function finalizeApprovedPayment(Payment $payment): void
    {
        // Invoice + receipt first, so payments never sit approved-without-paperwork.
        try {
            $this->billingDocuments->createInvoiceForPayment($payment->subscription, $payment);
            $this->billingDocuments->issueReceiptForApprovedPayment($payment);
            $payment->refresh();
        } catch (\Throwable $e) {
            Log::error('[GatewayService] Billing documents failed after approval', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Subscription state change for the payment type.
        $paymentType = $payment->payment_type instanceof PaymentType
            ? $payment->payment_type
            : PaymentType::from($payment->payment_type);

        match ($paymentType) {
            PaymentType::ONBOARDING,
            PaymentType::SUBSCRIPTION => $this->subscriptionService->activateSubscription(
                $payment->subscription, $payment, null
            ),
            PaymentType::RENEWAL => $this->subscriptionService->renewSubscription(
                $payment->subscription, $payment, null
            ),
            PaymentType::UPGRADE_PRORATION => $this->subscriptionService->upgradeNow(
                $payment->subscription,
                Plan::findOrFail((int) ($payment->metadata['target_plan_id'] ?? 0)),
                null
            ),
        };
    }

    /**
     * Convert a USD quote total into the charge currency at the current rate.
     * USD passes through; anything else goes through the FX service and throws
     * (422-style GatewayException) when no rate is available - never charge blind.
     */
    private function convertQuoteTotal(float $totalUsd, string $chargeCurrency, string $gatewayName): float
    {
        if ($chargeCurrency === 'USD') {
            return round($totalUsd, 2);
        }

        $converted = $this->fxService->convert($totalUsd, $chargeCurrency, 'USD');

        if ($converted === null) {
            throw new GatewayException(
                "No exchange rate available for USD -> {$chargeCurrency}. Please try again later or pay in USD.",
                $gatewayName
            );
        }

        return round($converted, 2);
    }
}
