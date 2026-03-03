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
        $accessToken = $this->getAccessToken();
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
                'first_name'    => $payload['customer_name'] ?? $payload['facility_name'],
                'last_name'     => '',
            ],
        ];

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(30)
            ->post("{$this->baseUrl}/api/Transactions/SubmitOrderRequest", $body);

        $data = $response->json() ?? [];

        if (! $response->successful() || empty($data['redirect_url'])) {
            Log::error('[PesaPal] Order submission failed', ['response' => $data, 'body' => $body]);
            throw new GatewayException(
                'PesaPal order submission failed: ' . ($data['message'] ?? "HTTP {$response->status()}"),
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
            'message'        => 'Redirecting to PesaPal payment page.',
            'raw_response'   => $data,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    public function verify(string $transactionId): array
    {
        // transactionId = order_tracking_id from PesaPal
        $accessToken = $this->getAccessToken();

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(30)
            ->get("{$this->baseUrl}/api/Transactions/GetTransactionStatus", [
                'orderTrackingId' => $transactionId,
            ]);

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
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /** Fetch and cache the PesaPal Bearer token. */
    private function getAccessToken(): string
    {
        $cacheKey = 'pesapal_token_' . config('billing_gateways.pesapal.environment');
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
                    'PesaPal token request failed: ' . ($data['message'] ?? "HTTP {$response->status()}"),
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
                'PesaPal IPN registration failed: ' . ($data['message'] ?? "HTTP {$response->status()}"),
                'pesapal',
                $data
            );
        }

        Log::info('[PesaPal] IPN registered', ['ipn_id' => $data['ipn_id'], 'url' => $ipnUrl]);

        // Suggest: store this in .env as PESAPAL_IPN_ID after first registration
        return $data['ipn_id'];
    }
}
