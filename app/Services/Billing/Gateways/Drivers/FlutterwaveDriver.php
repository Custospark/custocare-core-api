<?php

declare(strict_types=1);

namespace App\Services\Billing\Gateways\Drivers;

use App\Services\Billing\Gateways\Contracts\GatewayDriverInterface;
use App\Services\Billing\Gateways\Exceptions\GatewayException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * FlutterwaveDriver
 *
 * Implements the Flutterwave v3 Payments API.
 *
 * FLOW (redirect-based):
 *   1. POST /v3/payments → receive hosted payment link
 *   2. Frontend redirects user to the link
 *   3. User pays on Flutterwave page
 *   4. Flutterwave redirects back → GET /callback?transaction_id=&tx_ref=&status=
 *   5. We verify: GET /v3/transactions/{id}/verify
 *   6. Optionally: Flutterwave fires a POST webhook (async)
 *
 * WEBHOOK VERIFICATION:
 *   Flutterwave sends header: verif-hash: {your_FLW_WEBHOOK_SECRET}
 *   We compare it with config('billing_gateways.flutterwave.webhook_secret').
 */
class FlutterwaveDriver implements GatewayDriverInterface
{
    private string $baseUrl;
    private string $secretKey;

    public function __construct()
    {
        $this->baseUrl   = config('billing_gateways.flutterwave.base_url', 'https://api.flutterwave.com/v3');
        $this->secretKey = (string) config('billing_gateways.flutterwave.secret_key');
    }

    // ─────────────────────────────────────────────────────────────────────────
    public function initiate(array $payload): array
    {
        $txRef = 'FLW-' . strtoupper(Str::random(8)) . '-' . $payload['payment_id'];

        $body = [
            'tx_ref'         => $txRef,
            'amount'         => $payload['amount'],
            'currency'       => strtoupper($payload['currency']),
            'redirect_url'   => config('billing_gateways.flutterwave.redirect_url'),
            'payment_options' => 'mobilemoneyuganda,card,banktransfer',
            'customer'       => [
                'email'        => $payload['email'] ?? 'noreply@custocare.health',
                'name'         => $payload['customer_name'] ?? $payload['facility_name'],
                'phone_number' => $payload['phone_number'] ?? '',
            ],
            'customizations' => [
                'title'       => 'Custocare AI Subscription',
                'description' => $payload['description'],
                'logo'        => config('app.url') . '/logo.png',
            ],
            'meta' => [
                'custocare_payment_id'  => $payload['payment_id'],
                'custocare_sub_id'      => $payload['subscription_id'],
                'our_reference'         => $payload['our_reference'],
            ],
        ];

        $response = Http::withToken($this->secretKey)
            ->timeout(30)
            ->post("{$this->baseUrl}/payments", $body);

        $data = $response->json();

        if (! $response->successful() || ($data['status'] ?? '') !== 'success') {
            Log::error('[Flutterwave] Payment initiation failed', ['response' => $data, 'body' => $body]);
            throw new GatewayException(
                'Flutterwave payment initiation failed: ' . ($data['message'] ?? 'Unknown error'),
                'flutterwave',
                $data
            );
        }

        Log::info('[Flutterwave] Payment initiated', [
            'tx_ref'      => $txRef,
            'payment_id'  => $payload['payment_id'],
            'redirect_url' => $data['data']['link'],
        ]);

        return [
            'success'        => true,
            'gateway_ref'    => $txRef,
            'gateway_txn_id' => $txRef,     // Will be updated with real txn_id after verification
            'redirect_url'   => $data['data']['link'],
            'type'           => 'redirect',
            'message'        => 'Redirecting to Flutterwave payment page.',
            'raw_response'   => $data,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    public function verify(string $transactionId): array
    {
        // transactionId here is the numeric Flutterwave transaction ID (from callback)
        $response = Http::withToken($this->secretKey)
            ->timeout(30)
            ->get("{$this->baseUrl}/transactions/{$transactionId}/verify");

        $data = $response->json();

        if (! $response->successful() || ($data['status'] ?? '') !== 'success') {
            Log::error('[Flutterwave] Verification failed', ['transaction_id' => $transactionId, 'response' => $data]);
            return [
                'success'        => false,
                'status'         => 'failed',
                'gateway_txn_id' => $transactionId,
                'amount'         => 0,
                'currency'       => '',
                'message'        => $data['message'] ?? 'Verification failed',
                'raw_response'   => $data,
            ];
        }

        $txData  = $data['data'] ?? [];
        $status  = ($txData['status'] ?? '') === 'successful' ? 'successful' : 'failed';

        return [
            'success'        => $status === 'successful',
            'status'         => $status,
            'gateway_txn_id' => (string) ($txData['id'] ?? $transactionId),
            'tx_ref'         => $txData['tx_ref'] ?? '',
            'amount'         => (float) ($txData['amount'] ?? 0),
            'currency'       => $txData['currency'] ?? '',
            'message'        => $txData['processor_response'] ?? 'Verified',
            'raw_response'   => $data,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    public function parseWebhookPayload(Request $request): array
    {
        // ── Handles both GET redirect callback and POST webhook ───────────────
        if ($request->isMethod('GET')) {
            // Redirect callback: ?transaction_id=123&tx_ref=FLW-XXXX&status=successful
            $transactionId = $request->query('transaction_id');
            $status        = $request->query('status', 'failed');
            $txRef         = $request->query('tx_ref', '');

            return [
                'gateway_txn_id' => (string) $transactionId,
                'our_reference'  => $txRef,
                'status'         => strtolower($status) === 'successful' ? 'successful' : 'failed',
                'amount'         => 0,    // Will be populated by verify()
                'currency'       => '',
                'raw_payload'    => $request->query(),
            ];
        }

        // POST webhook: { event: "charge.completed", data: { ... } }
        $payload = $request->all();
        $data    = $payload['data'] ?? [];
        $status  = strtolower($data['status'] ?? '') === 'successful' ? 'successful' : 'failed';

        return [
            'gateway_txn_id' => (string) ($data['id'] ?? ''),
            'our_reference'  => $data['tx_ref'] ?? '',
            'status'         => $status,
            'amount'         => (float) ($data['amount'] ?? 0),
            'currency'       => $data['currency'] ?? '',
            'raw_payload'    => $payload,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    public function verifyWebhookSignature(Request $request): bool
    {
        // Flutterwave sends: verif-hash: {your_webhook_secret}
        $secret = config('billing_gateways.flutterwave.webhook_secret');

        if (empty($secret)) {
            Log::warning('[Flutterwave] Webhook secret not configured; skipping signature check.');
            return true; // Non-strict: allow if not configured (set strict=true in production)
        }

        return $request->header('verif-hash') === $secret;
    }

    // ─────────────────────────────────────────────────────────────────────────
    public function getName(): string               { return 'flutterwave'; }
    public function isRedirectBased(): bool         { return true; }
    public function getSupportedCurrencies(): array { return ['UGX', 'USD', 'KES', 'GHS', 'NGN']; }

    public function isEnabled(): bool
    {
        return config('billing_gateways.flutterwave.enabled', false) === true
            && ! empty(config('billing_gateways.flutterwave.secret_key'));
    }
}
