<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Billing\Gateway;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\Gateway\InitiateGatewayPaymentRequest;
use App\Http\Resources\Billing\PaymentResource;
use App\Models\Facility;
use App\Models\Subscription;
use App\Services\Billing\Gateways\GatewayManager;
use App\Services\Billing\Gateways\GatewayService;
use App\Services\Billing\Gateways\Exceptions\GatewayException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GatewayPaymentController
 *
 * Handles facility-initiated payment via a specific gateway driver.
 * Requires authentication — facility must be authenticated to initiate.
 *
 * Routes:
 *   GET  /api/billing/gateways               → list available gateways
 *   POST /api/billing/gateway/{gateway}/initiate → initiate payment
 */
class GatewayPaymentController extends Controller
{
    public function __construct(
        private readonly GatewayService $gatewayService,
        private readonly GatewayManager $gatewayManager
    ) {}

    /**
     * GET /api/billing/gateways
     * Returns a list of currently enabled payment gateways.
     * Facilities use this to know which options are available.
     */
    public function available(): JsonResponse
    {
        $gateways = collect($this->gatewayManager->available())
            ->map(function (string $name) {
                $driver = $this->gatewayManager->driver($name);
                return [
                    'name'               => $name,
                    'type'               => $driver->isRedirectBased() ? 'redirect' : 'push',
                    'supported_currencies' => $driver->getSupportedCurrencies(),
                    'label'              => match ($name) {
                        'mtn_momo'     => 'MTN Mobile Money Uganda',
                        'airtel_money' => 'Airtel Money Uganda',
                        'flutterwave'  => 'Flutterwave (Cards, Mobile Money)',
                        'pesapal'      => 'PesaPal (Mobile Money, Cards)',
                        default        => ucwords(str_replace('_', ' ', $name)),
                    },
                    'instructions'       => match ($name) {
                        'mtn_momo'     => 'You will receive a USSD prompt on your MTN line.',
                        'airtel_money' => 'You will receive a USSD prompt on your Airtel line.',
                        'flutterwave'  => 'You will be redirected to a secure payment page.',
                        'pesapal'      => 'You will be redirected to PesaPal checkout.',
                        default        => 'Follow the payment instructions.',
                    },
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Available payment gateways.',
            'data'    => $gateways,
        ]);
    }

    /**
     * POST /api/billing/gateway/{gateway}/initiate
     *
     * Initiate a payment with the specified gateway.
     *
     * For REDIRECT gateways (Flutterwave, PesaPal):
     *   Response includes redirect_url → frontend navigates user to it.
     *
     * For PUSH gateways (MTN MoMo, Airtel):
     *   Response message confirms USSD push sent → user approves on phone.
     *   Poll payment status or wait for subscription to activate.
     */
    public function initiate(
        InitiateGatewayPaymentRequest $request,
        Facility $facility,
        string $gateway
    ): JsonResponse {
        try {
            $subscription = Subscription::findOrFail($request->integer('subscription_id'));

            // Scope check — ensure subscription belongs to this facility
            if ($subscription->facility_id !== $facility->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Subscription does not belong to this facility.',
                    'data'    => null,
                ], 403);
            }

            $result = $this->gatewayService->initiatePayment(
                subscription: $subscription,
                gatewayName:  strtolower($gateway),
                data:         $request->validated()
            );

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data'    => [
                    'payment_id'   => $result['payment_id'],
                    'gateway'      => $result['gateway'],
                    'type'         => $result['type'],             // 'redirect' or 'push'
                    'redirect_url' => $result['redirect_url'],     // null for push gateways
                    'reference'    => $result['reference'],
                ],
            ], 202); // 202 Accepted — payment is being processed

        } catch (GatewayException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors'  => ['gateway' => [$e->getMessage()]],
                'data'    => null,
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate payment. Please try again.',
                'errors'  => ['server' => [$e->getMessage()]],
                'data'    => null,
            ], 500);
        }
    }

    /**
     * GET /api/billing/gateway/{gateway}/status/{reference}
     * Poll the status of a gateway payment by our payment_id or gateway reference.
     * Useful for push-based gateways while waiting for USSD approval.
     */
    public function status(Request $request, Facility $facility, string $gateway, string $reference): JsonResponse
    {
        $payment = \App\Models\Payment::where('facility_id', $facility->id)
            ->where(function ($q) use ($reference) {
                $q->where('gateway_transaction_id', $reference)
                  ->orWhere('transaction_reference', $reference)
                  ->orWhere('id', is_numeric($reference) ? $reference : null);
            })
            ->first();

        if (! $payment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found.',
                'data'    => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Payment status retrieved.',
            'data'    => [
                'payment_id'   => $payment->id,
                'status'       => $payment->status->value,
                'status_label' => $payment->status->label(),
                'gateway'      => $payment->gateway_name,
                'amount'       => $payment->amount,
                'currency'     => $payment->currency,
                'approved_at'  => $payment->approved_at?->toISOString(),
            ],
        ]);
    }
}
