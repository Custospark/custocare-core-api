<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Billing\Gateway;

use App\Http\Controllers\Controller;
use App\Services\Billing\Gateways\GatewayService;
use App\Services\Billing\Gateways\Exceptions\GatewayException;
use App\Services\Billing\Gateways\Exceptions\WebhookVerificationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * GatewayWebhookController
 *
 * PUBLIC endpoints — called by external gateway servers, NOT by facility users.
 * These routes MUST NOT require auth:sanctum middleware.
 *
 * Routes:
 *   POST /api/billing/gateway/{gateway}/webhook   ← MTN, Airtel, Flutterwave async
 *   GET  /api/billing/gateway/{gateway}/callback  ← Flutterwave, PesaPal redirect-back
 *   GET  /api/billing/gateway/{gateway}/ipn       ← PesaPal IPN (GET-based)
 */
class GatewayWebhookController extends Controller
{
    public function __construct(
        private readonly GatewayService $gatewayService
    ) {}

    /**
     * POST /api/billing/gateway/{gateway}/webhook
     *
     * Handles asynchronous webhook notifications from all gateways.
     *   - Flutterwave: POST with verif-hash header
     *   - MTN MoMo:   POST with callback body
     *   - Airtel:     POST with callback body
     *   - PesaPal:    Not used (PesaPal uses GET IPN instead)
     *
     * Always returns 200 OK immediately to acknowledge receipt.
     * Processing happens inside GatewayService.
     */
    public function webhook(Request $request, string $gateway): JsonResponse
    {
        Log::info("[Webhook] Received from {$gateway}", [
            'method'  => $request->method(),
            'headers' => $request->headers->all(),
            'body'    => $request->all(),
        ]);

        try {
            $this->gatewayService->processWebhook(strtolower($gateway), $request);
        } catch (WebhookVerificationException $e) {
            // Return 401 to tell the gateway the signature was invalid
            return response()->json(['message' => 'Verification failed.'], 401);
        } catch (GatewayException | \Exception $e) {
            Log::error("[Webhook] Processing error for {$gateway}", ['error' => $e->getMessage()]);
            // Return 200 to prevent gateway from retrying — we've logged the issue
            return response()->json(['message' => 'Received.'], 200);
        }

        // Always 200 — gateways will retry if they receive anything else
        return response()->json(['message' => 'Received.'], 200);
    }

    /**
     * GET /api/billing/gateway/{gateway}/callback
     *
     * Handles the redirect-back after a user completes payment on a hosted page.
     * Used by: Flutterwave, PesaPal
     *
     * The frontend should redirect or deep-link the user based on this response.
     */
    public function callback(Request $request, string $gateway): JsonResponse
    {
        Log::info("[Callback] Redirect received from {$gateway}", ['query' => $request->query()]);

        try {
            $result = $this->gatewayService->processCallback(strtolower($gateway), $request);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'data'    => [
                    'payment_id' => $result['payment_id'] ?? null,
                    'status'     => $result['status'] ?? ($result['success'] ? 'successful' : 'failed'),
                ],
            ], $result['success'] ? 200 : 422);

        } catch (GatewayException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data'    => null,
            ], 422);
        } catch (\Exception $e) {
            Log::error("[Callback] Error for {$gateway}", ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Payment callback processing failed.',
                'data'    => null,
            ], 500);
        }
    }

    /**
     * GET /api/billing/gateway/pesapal/ipn
     *
     * PesaPal Instant Payment Notification endpoint (GET-based).
     * PesaPal calls this URL with:
     *   ?OrderTrackingId={id}&OrderMerchantReference={ref}&OrderNotificationType=IPNCHANGE
     *
     * We re-use processWebhook() since parseWebhookPayload() normalises the format.
     */
    public function ipn(Request $request): JsonResponse
    {
        Log::info('[IPN] PesaPal IPN received', ['query' => $request->query()]);

        try {
            $this->gatewayService->processWebhook('pesapal', $request);
        } catch (\Exception $e) {
            Log::error('[IPN] PesaPal processing error', ['error' => $e->getMessage()]);
        }

        // PesaPal expects a 200 OK plain response
        return response()->json(['message' => 'IPN received.'], 200);
    }
}
