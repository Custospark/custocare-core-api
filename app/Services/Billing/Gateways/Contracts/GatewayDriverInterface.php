<?php

declare(strict_types=1);

namespace App\Services\Billing\Gateways\Contracts;

use Illuminate\Http\Request;

/**
 * GatewayDriverInterface
 *
 * Every payment gateway driver must implement this contract.
 * The interface normalises the differences between redirect-based gateways
 * (Flutterwave, PesaPal) and push-USSD gateways (MTN MoMo, Airtel Money).
 *
 * ─── Return shape contracts ───────────────────────────────────────────────
 *
 * initiate() → [
 *   'success'        => bool,
 *   'gateway_ref'    => string,   // reference we sent to the gateway
 *   'gateway_txn_id' => string,   // ID returned by the gateway (may = gateway_ref)
 *   'redirect_url'   => ?string,  // only for redirect-based gateways
 *   'type'           => 'redirect'|'push',
 *   'message'        => string,
 *   'raw_response'   => array,
 * ]
 *
 * verify() → [
 *   'success'        => bool,
 *   'status'         => 'successful'|'failed'|'pending',
 *   'gateway_txn_id' => string,
 *   'amount'         => float,
 *   'currency'       => string,
 *   'message'        => string,
 *   'raw_response'   => array,
 * ]
 *
 * parseWebhookPayload() → [
 *   'gateway_txn_id' => string,
 *   'our_reference'  => string,   // externalId / merchant reference we sent
 *   'status'         => 'successful'|'failed'|'pending',
 *   'amount'         => float,
 *   'currency'       => string,
 *   'raw_payload'    => array,
 * ]
 */
interface GatewayDriverInterface
{
    /**
     * Initiate a payment.
     *
     * For redirect gateways (Flutterwave, PesaPal):
     *   Returns a redirect_url the frontend must navigate to.
     *
     * For push gateways (MTN MoMo, Airtel):
     *   Sends a USSD push to the customer's phone.
     *   Returns type='push'; no redirect_url.
     *
     * @param array{
     *   amount:        float,
     *   currency:      string,
     *   our_reference: string,
     *   phone_number:  ?string,
     *   email:         ?string,
     *   customer_name: ?string,
     *   description:   string,
     *   payment_id:    int,
     * } $payload
     */
    public function initiate(array $payload): array;

    /**
     * Verify a transaction's final status with the gateway.
     * Called after receiving a webhook or redirect callback.
     *
     * @param  string $transactionId  The gateway's transaction / reference ID
     */
    public function verify(string $transactionId): array;

    /**
     * Parse and normalise the incoming webhook or callback request body/params.
     * Must work for both POST webhooks (MTN, Airtel, Flutterwave) and
     * GET IPN callbacks (PesaPal).
     */
    public function parseWebhookPayload(Request $request): array;

    /**
     * Verify that an inbound webhook request genuinely came from this gateway.
     * Return false to reject the request (results in 401).
     */
    public function verifyWebhookSignature(Request $request): bool;

    /** Unique string identifier for this driver (e.g. 'mtn_momo'). */
    public function getName(): string;

    /** True when the driver is enabled AND all required credentials are set. */
    public function isEnabled(): bool;

    /** ISO-4217 currency codes this gateway accepts. */
    public function getSupportedCurrencies(): array;

    /** True for Flutterwave / PesaPal; false for MTN MoMo / Airtel. */
    public function isRedirectBased(): bool;
}
