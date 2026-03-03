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
 * MtnMomoDriver
 *
 * Implements the MTN Mobile Money Uganda Collections API.
 *
 * FLOW (push-based):
 *   1. GET access token (cached for ~58 minutes)
 *   2. POST /collection/v1_0/requesttopay — USSD push sent to customer's phone
 *      Returns 202 Accepted (async — no immediate result)
 *   3. Customer approves on phone
 *   4. MTN POSTs webhook to our callback URL → processWebhook()
 *      OR we call verify() to poll for status
 *
 * PHONE FORMAT:  256770123456  (Uganda, no + prefix)
 * SANDBOX NOTE:  currency must be EUR in sandbox, UGX in production
 */
class MtnMomoDriver implements GatewayDriverInterface
{
    private string $baseUrl;
    private string $subscriptionKey;
    private string $apiUser;
    private string $apiKey;
    private string $environment;
    private string $currency;

    public function __construct()
    {
        $cfg = config('billing_gateways.mtn_momo');

        $this->environment     = $cfg['environment'] ?? 'sandbox';
        $this->baseUrl         = $this->environment === 'production'
            ? $cfg['base_url_production']
            : $cfg['base_url_sandbox'];
        $this->subscriptionKey = (string) ($cfg['subscription_key'] ?? '');
        $this->apiUser         = (string) ($cfg['api_user'] ?? '');
        $this->apiKey          = (string) ($cfg['api_key'] ?? '');
        $this->currency        = (string) ($cfg['currency'] ?? 'UGX');
    }

    // ─────────────────────────────────────────────────────────────────────────
    public function initiate(array $payload): array
    {
        $accessToken = $this->getAccessToken();
        $referenceId = (string) Str::uuid();
        $callbackUrl = config('billing_gateways.mtn_momo.callback_url');

        // MTN MoMo phone number: strip + and spaces
        $phone = preg_replace('/[^0-9]/', '', $payload['phone_number'] ?? '');
        if (empty($phone)) {
            throw new GatewayException(
                'Phone number is required for MTN MoMo payments.',
                'mtn_momo'
            );
        }

        $body = [
            'amount'      => (string) $payload['amount'],
            'currency'    => $this->currency,
            'externalId'  => $payload['our_reference'],
            'payer'       => [
                'partyIdType' => 'MSISDN',
                'partyId'     => $phone,
            ],
            'payerMessage' => 'Custocare subscription payment',
            'payeeNote'    => $payload['description'],
        ];

        $headers = [
            'Authorization'              => "Bearer {$accessToken}",
            'X-Reference-Id'             => $referenceId,
            'X-Target-Environment'       => $this->environment,
            'Ocp-Apim-Subscription-Key'  => $this->subscriptionKey,
            'Content-Type'               => 'application/json',
        ];

        if ($callbackUrl) {
            $headers['X-Callback-Url'] = $callbackUrl;
        }

        $response = Http::withHeaders($headers)
            ->timeout(30)
            ->post("{$this->baseUrl}/collection/v1_0/requesttopay", $body);

        // MTN MoMo returns 202 Accepted on success (no response body)
        if ($response->status() !== 202) {
            $errorBody = $response->json() ?? [];
            Log::error('[MTN MoMo] Payment initiation failed', [
                'status'      => $response->status(),
                'body'        => $errorBody,
                'reference_id' => $referenceId,
            ]);
            throw new GatewayException(
                'MTN MoMo request failed: ' . ($errorBody['message'] ?? "HTTP {$response->status()}"),
                'mtn_momo',
                $errorBody
            );
        }

        Log::info('[MTN MoMo] USSD push sent', [
            'reference_id' => $referenceId,
            'phone'        => $phone,
            'amount'       => $payload['amount'],
        ]);

        return [
            'success'        => true,
            'gateway_ref'    => $referenceId,
            'gateway_txn_id' => $referenceId,   // Poll/webhook uses this same UUID
            'redirect_url'   => null,            // Push-based: no redirect
            'type'           => 'push',
            'message'        => 'USSD push sent to ' . substr($phone, 0, 6) . '******. Customer must approve on their phone.',
            'raw_response'   => ['reference_id' => $referenceId, 'status' => 202],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    public function verify(string $transactionId): array
    {
        // transactionId is the X-Reference-Id UUID we sent in initiate()
        $accessToken = $this->getAccessToken();

        $response = Http::withHeaders([
            'Authorization'             => "Bearer {$accessToken}",
            'X-Target-Environment'      => $this->environment,
            'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
        ])->timeout(30)
          ->get("{$this->baseUrl}/collection/v1_0/requesttopay/{$transactionId}");

        $data   = $response->json() ?? [];
        $status = match (strtoupper($data['status'] ?? '')) {
            'SUCCESSFUL' => 'successful',
            'FAILED'     => 'failed',
            default      => 'pending',
        };

        return [
            'success'        => $status === 'successful',
            'status'         => $status,
            'gateway_txn_id' => $data['financialTransactionId'] ?? $transactionId,
            'amount'         => (float) ($data['amount'] ?? 0),
            'currency'       => $data['currency'] ?? $this->currency,
            'message'        => $data['reason'] ?? $status,
            'raw_response'   => $data,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    public function parseWebhookPayload(Request $request): array
    {
        /**
         * MTN MoMo callback POST body:
         * {
         *   "financialTransactionId": "363440463",
         *   "externalId":             "our-reference",
         *   "amount":                 "500",
         *   "currency":               "UGX",
         *   "payer":                  { "partyIdType": "MSISDN", "partyId": "256770123456" },
         *   "payerMessage":           "...",
         *   "payeeNote":              "...",
         *   "status":                 "SUCCESSFUL"
         * }
         */
        $payload = $request->all();

        $status = match (strtoupper($payload['status'] ?? '')) {
            'SUCCESSFUL' => 'successful',
            'FAILED'     => 'failed',
            default      => 'pending',
        };

        return [
            'gateway_txn_id' => $payload['financialTransactionId'] ?? '',
            'our_reference'  => $payload['externalId'] ?? '',
            'status'         => $status,
            'amount'         => (float) ($payload['amount'] ?? 0),
            'currency'       => $payload['currency'] ?? $this->currency,
            'raw_payload'    => $payload,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    public function verifyWebhookSignature(Request $request): bool
    {
        // MTN MoMo does not sign callbacks.
        // For production: whitelist MTN IP ranges or use a secret in the callback URL.
        // Here we return true and rely on payload verification + our own verify() call.
        Log::debug('[MTN MoMo] Webhook received (no signature verification).');
        return true;
    }

    // ─────────────────────────────────────────────────────────────────────────
    public function getName(): string               { return 'mtn_momo'; }
    public function isRedirectBased(): bool         { return false; }
    public function getSupportedCurrencies(): array { return ['UGX', 'EUR']; }

    public function isEnabled(): bool
    {
        return config('billing_gateways.mtn_momo.enabled', false) === true
            && ! empty($this->subscriptionKey)
            && ! empty($this->apiUser)
            && ! empty($this->apiKey);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Obtain a Bearer access token, caching it for just under its TTL.
     * Basic auth: base64(apiUser:apiKey)
     */
    private function getAccessToken(): string
    {
        $cacheKey = "mtn_momo_token_{$this->environment}";
        $ttl      = (int) config('billing_gateways.mtn_momo.token_cache_ttl', 3500);

        return Cache::remember($cacheKey, $ttl, function () {
            $credentials = base64_encode("{$this->apiUser}:{$this->apiKey}");

            $response = Http::withHeaders([
                'Authorization'             => "Basic {$credentials}",
                'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
            ])->timeout(15)
              ->post("{$this->baseUrl}/collection/token/");

            $data = $response->json() ?? [];

            if (! $response->successful() || empty($data['access_token'])) {
                throw new GatewayException(
                    'MTN MoMo token request failed: ' . ($data['message'] ?? "HTTP {$response->status()}"),
                    'mtn_momo',
                    $data
                );
            }

            Log::debug('[MTN MoMo] Access token refreshed.');
            return $data['access_token'];
        });
    }
}
