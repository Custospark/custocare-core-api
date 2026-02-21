<?php

use App\Http\Controllers\Api\RefundController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Refund & Void Routes
|--------------------------------------------------------------------------
|
| Two clean endpoints, one per operation type.
| Both are scoped under the billing-cycles resource and protected by the
| auth:sanctum middleware (adjust to match your guard setup).
|
| POST /api/billing-cycles/{billingCycleId}/void
|      → RefundController@voidTransaction
|
| POST /api/billing-cycles/{billingCycleId}/refund
|      → RefundController@refundTransaction
|        (full refund when no line_items sent,
|         partial refund when line_items present — detected automatically)
|
*/

Route::middleware(['auth:sanctum'])->group(function () {

    Route::prefix('billing-cycles/{billingCycleId}')->group(function () {

        /**
         * Void a transaction.
         *
         * Body: { reason, reason_notes?, restore_inventory? }
         */
        Route::post('void', [RefundController::class, 'voidTransaction'])
            ->name('billing-cycles.void');

        /**
         * Refund a transaction — full or partial (auto-detected).
         *
         * Full refund body:    { reason, reason_notes?, refund_methods[], restore_inventory? }
         * Partial refund body: { reason, reason_notes?, line_items[], refund_methods[], restore_inventory? }
         */
        Route::post('refund', [RefundController::class, 'refundTransaction'])
            ->name('billing-cycles.refund');
    });

});
