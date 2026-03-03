<?php

declare(strict_types=1);

namespace App\Services\Billing\Gateways;

use App\Enums\Billing\PaymentType;
use App\Models\Payment;
use App\Repositories\Billing\Contracts\PaymentRepositoryInterface;
use App\Services\Billing\Contracts\SubscriptionServiceInterface;
use App\Services\Billing\Gateways\Exceptions\GatewayException;
use App\Services\Billing\Gateways\Exceptions\WebhookVerificationException;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * GatewayService
 *
 * Orchestrates all gateway-related payment operations.
 * Sits between controllers and individual gateway drivers.
 *
 * RESPONSIBILITIES:
 *  1. Create a pending Payment record before calling the gateway
 *  2. Call the gateway driver and update the Payment with the gateway reference
 *  3. Process incoming webhooks / callbacks from gateways
 *  4. Auto-approve confirmed payments and trigger subscription changes
 *     (no admin action needed for gateway payments — gateway IS the approval)
 *
 * The manual billing flow (PaymentService) is entirely separate and unchanged.
 */
class GatewayService
{
    public function __construct(
        private readonly GatewayManager                $gatewayManager,
        private readonly PaymentRepositoryInterface    $paymentRepo,
        private readonly SubscriptionServiceInterface  $subscriptionService
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Initiate a payment via a gateway
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Initiate a gateway payment for a facility subscription.
     *
     * Creates a pending Payment record, calls the driver, updates the record
     * with the gateway reference, and returns the result to the controller.
     *
     * @param  Subscription $subscription  The facility's subscription being paid
     * @param  string       $gatewayName   e.g. 'flutterwave', 'mtn_momo'
     * @param  array{
     *   amount:         float,
     *   currency:       string,
     *   payment_type:   string,
     *   phone_number:   ?string,
     *   email:          ?string,
     *   customer_name:  ?string,
     * }                 $data
     * @return array{
     *   success:       bool,
     *   payment_id:    int,
     *   gateway:       string,
     *   type:          string,
     *   redirect_url:  ?string,
     *   reference:     string,
     *   message:       string,
     * }
     * @throws GatewayException
     */
    public function initiatePayment(
        Subscription $subscription,
        string $gatewayName,
        array $data
    ): array {
        $driver = $this->gatewayManager->driver($gatewayName);

        if (! $driver->isEnabled()) {
            throw new GatewayException(
                "Gateway '{$gatewayName}' is not currently enabled. Please use manual payment or select another gateway.",
                $gatewayName
            );
        }

        // ── Step 1: Create a pending payment record ───────────────────────
        $payment = $this->paymentRepo->create([
            'subscription_id' => $subscription->id,
            'facility_id'     => $subscription->facility_id,
            'amount'          => $data['amount'],
            'currency'        => strtoupper($data['currency']),
            'method'          => 'gateway',
            'payment_type'    => $data['payment_type'],
            'status'          => 'pending',
            'gateway_name'    => $gatewayName,
            'paid_at'         => null,
            'metadata'        => $data['metadata'] ?? null,
        ]);

        // ── Step 2: Build driver payload ──────────────────────────────────
        $plan        = $subscription->plan ?? $subscription->plan()->first();
        $facility    = $subscription->facility ?? $subscription->facility()->first();
        $ourRef      = "CUSTOCARE-AI-{$payment->id}-" . now()->format('YmdHis');

        $driverPayload = [
            'amount'          => $data['amount'],
            'currency'        => strtoupper($data['currency']),
            'our_reference'   => $ourRef,
            'phone_number'    => $data['phone_number'] ?? null,
            'email'           => $data['email'] ?? null,
            'customer_name'   => $data['customer_name'] ?? null,
            'description'     => 'Custocare subscription — ' . ($plan->name ?? 'Plan'),
            'facility_name'   => $facility->facility_name ?? "Facility #{$subscription->facility_id}",
            'payment_id'      => $payment->id,
            'subscription_id' => $subscription->id,
        ];

        // ── Step 3: Call gateway driver ───────────────────────────────────
        try {
            $result = $driver->initiate($driverPayload);

            // Update payment with gateway-assigned IDs
            $this->paymentRepo->update($payment, [
                'gateway_transaction_id' => $result['gateway_txn_id'] ?? $result['gateway_ref'],
                'transaction_reference'  => $ourRef,
                'gateway_response'       => [
                    'initiation'    => $result['raw_response'] ?? [],
                    'our_reference' => $ourRef,
                ],
            ]);

            Log::info('[GatewayService] Payment initiated', [
                'payment_id'      => $payment->id,
                'gateway'         => $gatewayName,
                'type'            => $result['type'],
                'gateway_txn_id'  => $result['gateway_txn_id'],
            ]);

            return [
                'success'      => true,
                'payment_id'   => $payment->id,
                'gateway'      => $gatewayName,
                'type'         => $result['type'],
                'redirect_url' => $result['redirect_url'] ?? null,
                'reference'    => $result['gateway_ref'],
                'message'      => $result['message'],
            ];

        } catch (\Throwable $e) {
            // Mark payment as rejected if driver call fails
            $this->paymentRepo->update($payment, [
                'status'           => 'rejected',
                'rejection_reason' => "Gateway initiation failed: {$e->getMessage()}",
            ]);

            Log::error('[GatewayService] Initiation failed', [
                'payment_id' => $payment->id,
                'gateway'    => $gatewayName,
                'error'      => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Process an incoming webhook (POST from gateway)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Process a webhook notification from a gateway.
     * Verifies signature, resolves the payment, re-confirms with gateway, and auto-approves.
     *
     * @throws WebhookVerificationException  If the signature check fails.
     */
    public function processWebhook(string $gatewayName, Request $request): void
    {
        $driver = $this->gatewayManager->driver($gatewayName);

        // ── Signature check ───────────────────────────────────────────────
        if (! $driver->verifyWebhookSignature($request)) {
            Log::warning("[GatewayService] Invalid webhook signature for {$gatewayName}");
            throw new WebhookVerificationException(
                "Webhook signature verification failed for gateway '{$gatewayName}'."
            );
        }

        // ── Parse payload ─────────────────────────────────────────────────
        $webhookData = $driver->parseWebhookPayload($request);

        Log::info("[GatewayService] Webhook received from {$gatewayName}", [
            'gateway_txn_id' => $webhookData['gateway_txn_id'],
            'status'         => $webhookData['status'],
        ]);

        if (empty($webhookData['gateway_txn_id']) && empty($webhookData['our_reference'])) {
            Log::warning("[GatewayService] Webhook missing identifiers", $webhookData);
            return;
        }

        // ── Resolve the payment record ────────────────────────────────────
        $payment = $this->resolvePaymentFromWebhook($webhookData);

        if (! $payment) {
            Log::error("[GatewayService] Payment not found for webhook", $webhookData);
            return;
        }

        if (! $payment->isPending()) {
            Log::info("[GatewayService] Payment #{$payment->id} already processed — skipping.");
            return;
        }

        // ── Verify with gateway (double-confirmation) ─────────────────────
        $verification = $driver->verify($webhookData['gateway_txn_id']);

        if (! $verification['success'] || $verification['status'] !== 'successful') {
            Log::info("[GatewayService] Payment not yet successful", [
                'payment_id' => $payment->id,
                'status'     => $verification['status'],
            ]);
            // Update gateway_response for audit trail even if not successful
            $this->paymentRepo->update($payment, [
                'gateway_response' => array_merge(
                    $payment->gateway_response ?? [],
                    ['webhook' => $webhookData, 'verification' => $verification]
                ),
            ]);
            return;
        }

        // ── Auto-approve and activate subscription ────────────────────────
        $this->autoApprove($payment, $webhookData, $verification);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Process a redirect callback (GET from gateway after user completes payment)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Handle the redirect callback when a user returns from a hosted payment page.
     * Used by Flutterwave and PesaPal.
     *
     * @return array{success: bool, message: string, payment_id: ?int}
     */
    public function processCallback(string $gatewayName, Request $request): array
    {
        $driver      = $this->gatewayManager->driver($gatewayName);
        $callbackData = $driver->parseWebhookPayload($request);

        if (empty($callbackData['gateway_txn_id'])) {
            return ['success' => false, 'message' => 'Missing transaction identifier in callback.', 'payment_id' => null];
        }

        // Verify with gateway regardless of what the callback says
        $verification = $driver->verify($callbackData['gateway_txn_id']);

        if (! $verification['success'] || $verification['status'] !== 'successful') {
            return [
                'success'    => false,
                'message'    => 'Payment could not be verified: ' . ($verification['message'] ?? 'status ' . $verification['status']),
                'payment_id' => null,
                'status'     => $verification['status'],
            ];
        }

        // Find and process the payment
        $payment = Payment::where('gateway_transaction_id', $callbackData['gateway_txn_id'])
            ->orWhere('gateway_transaction_id', $callbackData['our_reference'])
            ->first();

        if (! $payment) {
            Log::error("[GatewayService] Callback — payment not found", $callbackData);
            return ['success' => false, 'message' => 'Payment record not found.', 'payment_id' => null];
        }

        if (! $payment->isPending()) {
            return ['success' => true, 'message' => 'Payment already confirmed.', 'payment_id' => $payment->id];
        }

        $this->autoApprove($payment, $callbackData, $verification);

        return [
            'success'    => true,
            'message'    => 'Payment confirmed. Subscription activated.',
            'payment_id' => $payment->id,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Auto-approve a gateway-confirmed payment and trigger the subscription transition.
     * Gateway confirmation IS the approval — no admin action required.
     */
    private function autoApprove(Payment $payment, array $webhookData, array $verification): void
    {
        DB::transaction(function () use ($payment, $webhookData, $verification) {

            $this->paymentRepo->update($payment, [
                'status'               => 'approved',
                'approved_at'          => now(),
                'paid_at'              => $payment->paid_at ?? now(),
                'gateway_transaction_id' => $verification['gateway_txn_id'] ?? $payment->gateway_transaction_id,
                'gateway_response'     => array_merge(
                    $payment->gateway_response ?? [],
                    ['webhook' => $webhookData, 'verification' => $verification]
                ),
            ]);

            $payment->refresh();

            // Trigger subscription state change — no admin Staff required
            match ($payment->payment_type) {
                PaymentType::ONBOARDING,
                PaymentType::SUBSCRIPTION => $this->subscriptionService->activateSubscription(
                    $payment->subscription, $payment, null
                ),
                PaymentType::RENEWAL => $this->subscriptionService->renewSubscription(
                    $payment->subscription, $payment, null
                ),
            };

            Log::info('[GatewayService] Payment auto-approved', [
                'payment_id'      => $payment->id,
                'gateway'         => $payment->gateway_name,
                'subscription_id' => $payment->subscription_id,
                'facility_id'     => $payment->facility_id,
            ]);
        });
    }

    /**
     * Try to find the Payment record from webhook data.
     * Tries gateway_txn_id first, then our_reference (externalId).
     */
    private function resolvePaymentFromWebhook(array $webhookData): ?Payment
    {
        if (! empty($webhookData['gateway_txn_id'])) {
            $payment = Payment::where('gateway_transaction_id', $webhookData['gateway_txn_id'])->first();
            if ($payment) return $payment;
        }

        if (! empty($webhookData['our_reference'])) {
            return Payment::where('transaction_reference', $webhookData['our_reference'])->first();
        }

        return null;
    }
}
