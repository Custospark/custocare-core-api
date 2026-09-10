<?php

declare(strict_types=1);

namespace App\Services\Billing\Gateways\Drivers;

use App\Services\Billing\Gateways\Contracts\GatewayDriverInterface;
use App\Services\Billing\Gateways\Exceptions\GatewayException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * PesaPalDriver
 *
 * Implements the PesaPal v3 API.
 *
 * FLOW (redirect-based):
 *   1. GET OAuth2 token (cached)
 *   2. POST /api/Transactions/SubmitOrderRequest → receive redirect_url
 *   3. Frontend redirects user to redirect_url (hosted PesaPal checkout)
 *   4. User pays (card, mobile money, bank)
 *   5. PesaPal fires GET IPN → /api/billing/gateway/pesapal/ipn?OrderTrackingId=&...
 *   6. We call /api/Transactions/GetTransactionStatus to verify
 *   7. Also redirects user back to callback_url
 *
 * STATUS CODES:
 *   0 = INVALID
 *   1 = COMPLETED
 *   2 = FAILED
 *   3 = REVERSED
 */
class PesaPalDriver implements GatewayDriverInterface
{
    private string $baseUrl;
    private string $consumerKey;
    private string $consumerSecret;
    private string $ipnId;

    public function __construct()
    {
        $cfg = config('billing_gateways.pesapal');

        $env           = $cfg['environment'] ?? 'sandbox';
        $this->baseUrl = $env === 'production'
            ? $cfg['base_url_production']
            : $cfg['base_url_sandbox'];

        $this->consumerKey    = (string) ($cfg['consumer_key'] ?? '');
        $this->consumerSecret = (string) ($cfg['consumer_secret'] ?? '');
        $this->ipnId          = (string) ($cfg['ipn_id'] ?? '');
    }

    // ─────────────────────────────────────────────────────────────────────────
    public function initiate(array $payload): array
    {
        try {
            return $this->submitOrder($payload, $this->getAccessToken());
        } catch (GatewayException $e) {
            // Stale cached token (401) - refresh once and retry, then give up.
            if (! str_contains($e->getMessage(), 'HTTP 401')) {
                throw $e;
            }
            Cache::forget($this->tokenCacheKey());

            return $this->submitOrder($payload, $this->getAccessToken());
        }
    }

    private function submitOrder(array $payload, string $accessToken): array
    {
        $merchantRef = 'CUSTO-' . $payload['payment_id'] . '-' . now()->format('YmdHis');

        // If no IPN ID is configured, register one on-the-fly
        $ipnId = $this->ipnId ?: $this->registerIpn($accessToken);

        $body = [
            'id'            => $merchantRef,
            'currency'      => strtoupper($payload['currency']),
            'amount'        => (float) $payload['amount'],
            'description'   => $payload['description'],
            'callback_url'  => config('billing_gateways.pesapal.callback_url'),
            'redirect_mode' => 'TOP_WINDOW',
            'notification_id' => $ipnId,
            'billing_address' => [
                'email_address' => $payload['email'] ?? 'noreply@custocare.health',
                'phone_number'  => $payload['phone_number'] ?? '',
                'country_code'  => 'UG',
                'first_name'    => $payload['customer_name'] ?? $payload['facility_name'] ?? 'Custocare Facility',
                'last_name'     => '',
            ],
        ];

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(30)
            ->post("{$this->baseUrl}/api/Transactions/SubmitOrderRequest", $body);

        $data = $response->json() ?? [];

        if ($response->status() === 401) {
            // Marker format matters: initiate() retries exactly this signal once.
            throw new GatewayException(
                'Payment order submission failed: HTTP 401 (stale cached token)',
                'pesapal',
                $data
            );
        }

        if (! $response->successful() || empty($data['redirect_url'])) {
            Log::error('[PesaPal] Order submission failed', ['response' => $data, 'body' => $body]);
            throw new GatewayException(
                'Payment order submission failed: ' . ($data['message'] ?? "HTTP {$response->status()}"),
                'pesapal',
                $data
            );
        }

        Log::info('[PesaPal] Order submitted', [
            'order_tracking_id' => $data['order_tracking_id'],
            'merchant_reference' => $merchantRef,
        ]);

        return [
            'success'        => true,
            'gateway_ref'    => $merchantRef,
            'gateway_txn_id' => $data['order_tracking_id'],
            'redirect_url'   => $data['redirect_url'],
            'type'           => 'redirect',
            'message'        => 'Redirecting to the secure payment page.',
            'raw_response'   => $data,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    public function verify(string $transactionId): array
    {
        // transactionId = order_tracking_id from PesaPal
        $response = $this->fetchTransactionStatus($transactionId, $this->getAccessToken());

        // Stale cached token (401) - refresh once and retry, then accept the result.
        if ($response->status() === 401) {
            Cache::forget($this->tokenCacheKey());
            $response = $this->fetchTransactionStatus($transactionId, $this->getAccessToken());
        }

        $data       = $response->json() ?? [];
        $statusCode = (int) ($data['status_code'] ?? 0);

        $status = match ($statusCode) {
            1       => 'successful',
            2, 3    => 'failed',
            default => 'pending',
        };

        return [
            'success'        => $status === 'successful',
            'status'         => $status,
            'gateway_txn_id' => $transactionId,
            'amount'         => (float) ($data['amount'] ?? 0),
            'currency'       => $data['currency'] ?? '',
            'message'        => $data['payment_status_description'] ?? $status,
            'raw_response'   => $data,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    public function parseWebhookPayload(Request $request): array
    {
        /**
         * PesaPal IPN is a GET request:
         * ?OrderTrackingId={id}&OrderMerchantReference={ref}&OrderNotificationType=IPNCHANGE
         *
         * PesaPal callback redirect is also GET:
         * ?OrderTrackingId={id}&OrderMerchantReference={ref}&OrderNotificationType=IPNCHANGE
         *
         * We unify both here. Actual status is fetched via verify().
         */
        $orderTrackingId   = $request->query('OrderTrackingId', '');
        $merchantReference = $request->query('OrderMerchantReference', '');
        $notificationType  = $request->query('OrderNotificationType', '');

        return [
            'gateway_txn_id' => $orderTrackingId,
            'our_reference'  => $merchantReference,
            'status'         => 'pending',   // Resolve via verify()
            'amount'         => 0,
            'currency'       => '',
            'raw_payload'    => $request->query(),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    public function verifyWebhookSignature(Request $request): bool
    {
        // PesaPal does not sign IPN notifications.
        // We verify the transaction status directly via GetTransactionStatus API.
        return true;
    }

    // ─────────────────────────────────────────────────────────────────────────
    public function getName(): string               { return 'pesapal'; }
    public function isRedirectBased(): bool         { return true; }
    public function getSupportedCurrencies(): array { return ['UGX', 'KES', 'TZS', 'USD']; }

    public function isEnabled(): bool
    {
        return config('billing_gateways.pesapal.enabled', false) === true
            && ! empty($this->consumerKey)
            && ! empty($this->consumerSecret);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Ops diagnostics (used by pesapal:status / pesapal:register-ipn)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Verify API connectivity by fetching a token. Returns metadata only -
     * the token itself is never exposed to console output.
     *
     * @return array{ok: bool, key_len: int, message: string}
     */
    public function checkConnection(): array
    {
        try {
            $this->getAccessToken();

            return [
                'ok' => true,
                'key_len' => strlen($this->consumerKey),
                'message' => 'Token request succeeded.',
            ];
        } catch (GatewayException $e) {
            // Diagnostics only: response keys, never credential values.
            $raw = $e->getRawResponse();
            $keys = is_array($raw) ? implode(',', array_keys($raw)) : 'none';

            return ['ok' => false, 'key_len' => 0, 'message' => $e->getMessage() . ' [keys: ' . $keys . ']'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'key_len' => 0, 'message' => $e->getMessage()];
        }
    }

    /**
     * Register the webhook IPN URL and return the ipn_id for .env persistence.
     */
    public function registerIpnId(): string
    {
        return $this->registerIpn($this->getAccessToken());
    }

    public function configuredIpnId(): string
    {
        return $this->ipnId;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /** Fetch and cache the PesaPal Bearer token. */
    private function tokenCacheKey(): string
    {
        return 'pesapal_token_' . config('billing_gateways.pesapal.environment');
    }

    private function fetchTransactionStatus(string $transactionId, string $accessToken)
    {
        return Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(30)
            ->get("{$this->baseUrl}/api/Transactions/GetTransactionStatus", [
                'orderTrackingId' => $transactionId,
            ]);
    }

    private function getAccessToken(): string
    {
        $cacheKey = $this->tokenCacheKey();
        $ttl      = (int) config('billing_gateways.pesapal.token_cache_ttl', 3300);

        return Cache::remember($cacheKey, $ttl, function () {
            $response = Http::acceptJson()
                ->contentType('application/json')
                ->timeout(15)
                ->post("{$this->baseUrl}/api/Auth/RequestToken", [
                    'consumer_key'    => $this->consumerKey,
                    'consumer_secret' => $this->consumerSecret,
                ]);

            $data = $response->json() ?? [];

            if (! $response->successful() || empty($data['token'])) {
                throw new GatewayException(
                    'Payment token request failed: ' . ($data['message'] ?? "HTTP {$response->status()}"),
                    'pesapal',
                    $data
                );
            }

            Log::debug('[PesaPal] Access token refreshed.');
            return $data['token'];
        });
    }

    /**
     * Register an IPN URL with PesaPal and return the ipn_id.
     * Called once if PESAPAL_IPN_ID is not set in .env.
     */
    private function registerIpn(string $accessToken): string
    {
        $ipnUrl = route('billing.gateway.webhook', ['gateway' => 'pesapal']);

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(15)
            ->post("{$this->baseUrl}/api/URLSetup/RegisterIPN", [
                'url'                     => $ipnUrl,
                'ipn_notification_type'   => 'GET',
            ]);

        $data = $response->json() ?? [];

        if (! $response->successful() || empty($data['ipn_id'])) {
            throw new GatewayException(
                'Payment notification registration failed: ' . ($data['message'] ?? "HTTP {$response->status()}"),
                'pesapal',
                $data
            );
        }

        Log::info('[PesaPal] IPN registered', ['ipn_id' => $data['ipn_id'], 'url' => $ipnUrl]);

        // Suggest: store this in .env as PESAPAL_IPN_ID after first registration
        return $data['ipn_id'];
    }
}
