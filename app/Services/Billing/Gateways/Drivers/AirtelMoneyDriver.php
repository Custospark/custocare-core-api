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
 * AirtelMoneyDriver
 *
 * Implements the Airtel Africa Money Uganda API.
 *
 * FLOW (push-based):
 *   1. GET OAuth2 token (cached)
 *   2. POST /merchant/v2/payments/ — USSD push to customer's phone
 *   3. Customer approves on phone
 *   4. Airtel POSTs callback → parseWebhookPayload()
 *      OR poll via GET /standard/v1/payments/{id}
 *
 * STATUS CODES:
 *   TS  = Transaction Successful
 *   TF  = Transaction Failed
 *   TI  = Transaction in Progress (pending)
 *   TCF = Transaction Cannot be Completed (float issue)
 *
 * PHONE FORMAT:  256751234567  (Uganda, no + prefix, no leading 0)
 */
class AirtelMoneyDriver implements GatewayDriverInterface
{
    private string $baseUrl;
    private string $clientId;
    private string $clientSecret;
    private string $country;
    private string $currency;

    public function __construct()
    {
        $cfg = config('billing_gateways.airtel_money');

        $this->baseUrl       = rtrim((string) ($cfg['base_url'] ?? 'https://openapi.airtel.africa'), '/');
        $this->clientId      = (string) ($cfg['client_id'] ?? '');
        $this->clientSecret  = (string) ($cfg['client_secret'] ?? '');
        $this->country       = (string) ($cfg['country'] ?? 'UG');
        $this->currency      = (string) ($cfg['currency'] ?? 'UGX');
    }

    // ─────────────────────────────────────────────────────────────────────────
    public function initiate(array $payload): array
    {
        $accessToken = $this->getAccessToken();
        $transId     = 'AIRTEL-' . strtoupper(Str::random(6)) . '-' . $payload['payment_id'];

        // Airtel phone: strip all non-digits
        $phone = preg_replace('/[^0-9]/', '', $payload['phone_number'] ?? '');
        if (empty($phone)) {
            throw new GatewayException(
                'Phone number is required for Airtel Money payments.',
                'airtel_money'
            );
        }

        $body = [
            'reference'   => $payload['our_reference'],
            'subscriber'  => [
                'country'  => $this->country,
                'currency' => $this->currency,
                'msisdn'   => $phone,
            ],
            'transaction' => [
                'amount'   => (int) $payload['amount'],
                'country'  => $this->country,
                'currency' => $this->currency,
                'id'       => $transId,
            ],
        ];

        $response = Http::withToken($accessToken)
            ->withHeaders([
                'X-Country'  => $this->country,
                'X-Currency' => $this->currency,
            ])
            ->timeout(30)
            ->post("{$this->baseUrl}/merchant/v2/payments/", $body);

        $data = $response->json() ?? [];

        // Airtel success: status.code == "200" and data.transaction.status == "TS" (async) or "TI"
        $statusCode  = $data['status']['code'] ?? '';
        $txnStatus   = $data['data']['transaction']['status'] ?? '';

        if (! $response->successful() || ! in_array($statusCode, ['200', '201'])) {
            Log::error('[Airtel Money] Payment initiation failed', ['response' => $data, 'body' => $body]);
            throw new GatewayException(
                'Airtel Money request failed: ' . ($data['status']['message'] ?? "HTTP {$response->status()}"),
                'airtel_money',
                $data
            );
        }

        $airtelTxnId = $data['data']['transaction']['id'] ?? $transId;

        Log::info('[Airtel Money] USSD push sent', [
            'transaction_id' => $airtelTxnId,
            'phone'          => substr($phone, 0, 6) . '***',
            'amount'         => $payload['amount'],
        ]);

        return [
            'success'        => true,
            'gateway_ref'    => $transId,
            'gateway_txn_id' => $airtelTxnId,
            'redirect_url'   => null,
            'type'           => 'push',
            'message'        => 'Airtel Money USSD push sent. Customer must approve on their phone.',
            'raw_response'   => $data,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    public function verify(string $transactionId): array
    {
        $accessToken = $this->getAccessToken();

        $response = Http::withToken($accessToken)
            ->withHeaders([
                'X-Country'  => $this->country,
                'X-Currency' => $this->currency,
            ])
            ->timeout(30)
            ->get("{$this->baseUrl}/standard/v1/payments/{$transactionId}");

        $data      = $response->json() ?? [];
        $txStatus  = $data['data']['transaction']['status'] ?? 'TI';

        $status = match (strtoupper($txStatus)) {
            'TS'        => 'successful',
            'TF', 'TCF' => 'failed',
            default     => 'pending',
        };

        return [
            'success'        => $status === 'successful',
            'status'         => $status,
            'gateway_txn_id' => $data['data']['transaction']['airtel_money_id'] ?? $transactionId,
            'amount'         => (float) ($data['data']['transaction']['amount'] ?? 0),
            'currency'       => $this->currency,
            'message'        => $data['data']['transaction']['message'] ?? $txStatus,
            'raw_response'   => $data,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    public function parseWebhookPayload(Request $request): array
    {
        /**
         * Airtel Money callback POST body:
         * {
         *   "transaction": {
         *     "id":              "AIRTEL-XXXX-123",
         *     "message":         "Paid",
         *     "msisdn":          "256751234567",
         *     "initiator_id":    "our-reference",
         *     "airtel_money_id": "CI2024...",
         *     "status_code":     "TS"
         *   }
         * }
         */
        $payload = $request->all();
        $txn     = $payload['transaction'] ?? [];

        $status = match (strtoupper($txn['status_code'] ?? '')) {
            'TS'        => 'successful',
            'TF', 'TCF' => 'failed',
            default     => 'pending',
        };

        return [
            'gateway_txn_id' => $txn['airtel_money_id'] ?? $txn['id'] ?? '',
            'our_reference'  => $txn['initiator_id'] ?? $txn['id'] ?? '',
            'status'         => $status,
            'amount'         => 0,   // Not always in webhook; use verify() for amount
            'currency'       => $this->currency,
            'raw_payload'    => $payload,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    public function verifyWebhookSignature(Request $request): bool
    {
        // Airtel does not provide standard webhook signatures.
        // Validate the transaction via verify() API call after receiving the webhook.
        return true;
    }

    // ─────────────────────────────────────────────────────────────────────────
    public function getName(): string               { return 'airtel_money'; }
    public function isRedirectBased(): bool         { return false; }
    public function getSupportedCurrencies(): array { return ['UGX', 'KES', 'TZS', 'RWF']; }

    public function isEnabled(): bool
    {
        return config('billing_gateways.airtel_money.enabled', false) === true
            && ! empty($this->clientId)
            && ! empty($this->clientSecret);
    }

    // ─────────────────────────────────────────────────────────────────────────
    private function getAccessToken(): string
    {
        $cacheKey = 'airtel_money_token';
        $ttl      = (int) config('billing_gateways.airtel_money.token_cache_ttl', 3500);

        return Cache::remember($cacheKey, $ttl, function () {
            $response = Http::asForm()
                ->timeout(15)
                ->post("{$this->baseUrl}/auth/oauth2/token", [
                    'client_id'     => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'grant_type'    => 'client_credentials',
                ]);

            $data = $response->json() ?? [];

            if (! $response->successful() || empty($data['access_token'])) {
                throw new GatewayException(
                    'Airtel Money token request failed: ' . ($data['message'] ?? "HTTP {$response->status()}"),
                    'airtel_money',
                    $data
                );
            }

            Log::debug('[Airtel Money] Access token refreshed.');
            return $data['access_token'];
        });
    }
}
