<?php
use App\Http\Controllers\Api\Billing\Gateway\GatewayPaymentController;
use App\Http\Controllers\Api\Billing\Gateway\GatewayWebhookController;
use Illuminate\Support\Facades\Route;

/*
|──────────────────────────────────────────────────────────────────────────────
| [4] GATEWAY — PUBLIC endpoints (NO auth — called by gateways or open polling)
|
| ⚠ These routes must NOT be behind auth:sanctum.
|   They are called by external gateway servers, not by facility users.
|
| MTN MoMo webhook:    POST /api/billing/gateway/mtn_momo/webhook
| Airtel webhook:      POST /api/billing/gateway/airtel_money/webhook
| Flutterwave webhook: POST /api/billing/gateway/flutterwave/webhook
| Flutterwave callback:GET  /api/billing/gateway/flutterwave/callback
| PesaPal callback:    GET  /api/billing/gateway/pesapal/callback
| PesaPal IPN:         GET  /api/billing/gateway/pesapal/ipn
|──────────────────────────────────────────────────────────────────────────────
*/
Route::prefix('billing/gateway')
    ->name('billing.gateway.')
    ->group(function () {

        // ── Async webhook notifications (POST from gateway servers) ───────
        Route::post('/{gateway}/webhook', [GatewayWebhookController::class, 'webhook'])
            ->name('webhook');

        // ── Redirect-back callbacks (GET, user returning from hosted page) ─
        Route::get('/{gateway}/callback', [GatewayWebhookController::class, 'callback'])
            ->name('callback');

        // ── PesaPal IPN (GET-based IPN, dedicated route) ──────────────────
        Route::get('/pesapal/ipn', [GatewayWebhookController::class, 'ipn'])
            ->name('pesapal.ipn');
    });

/*
|──────────────────────────────────────────────────────────────────────────────
| [5] GATEWAY — AUTHENTICATED endpoints (facility-facing)
|
| Facility staff initiate payments and check status here.
|──────────────────────────────────────────────────────────────────────────────
*/
Route::middleware(['auth:sanctum'])
    ->prefix('billing')
    ->name('billing.')
    ->group(function () {

        // List available (enabled) gateways
        Route::get('/gateways', [GatewayPaymentController::class, 'available'])
            ->name('gateways.available');

        // Initiate a payment via a specific gateway
        // POST /api/billing/gateway/{gateway}/initiate
        Route::post('/gateway/{gateway}/initiate',
            [GatewayPaymentController::class, 'initiate'])
            ->name('gateway.initiate');
    });

/*
|──────────────────────────────────────────────────────────────────────────────
| [6] GATEWAY STATUS — Per-facility payment status polling
|──────────────────────────────────────────────────────────────────────────────
*/
Route::middleware(['auth:sanctum'])
    ->prefix('facilities/{facility}/payments/gateway')
    ->name('facilities.gateway.payments.')
    ->group(function () {

        // GET /api/facilities/{facility}/payments/gateway/{reference}/status
        Route::get('/{reference}/status',
            [GatewayPaymentController::class, 'status'])
            ->name('status');
    });
