<?php

use App\Http\Controllers\Api\Compliance\DsarController;
use App\Http\Controllers\Api\Compliance\PrivacyNoticeController;
use Illuminate\Support\Facades\Route;

/*
|──────────────────────────────────────────────────────────────────────────────
| COMPLIANCE — PUBLIC endpoints (no auth: readable before registration)
|──────────────────────────────────────────────────────────────────────────────
*/
Route::prefix('compliance')->name('compliance.')->group(function () {
    Route::get('/privacy-notice', [PrivacyNoticeController::class, 'show'])
        ->name('privacy-notice');

    // Staff-only DSAR intake (subject requests via any channel).
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('/dsar', [DsarController::class, 'index'])->name('dsar.index');
        Route::post('/dsar', [DsarController::class, 'store'])->name('dsar.store');
        Route::get('/dsar/{dsarRequest}', [DsarController::class, 'show'])->name('dsar.show');
        Route::post('/dsar/{dsarRequest}/fulfill', [DsarController::class, 'fulfill'])->name('dsar.fulfill');
        Route::post('/dsar/{dsarRequest}/reject', [DsarController::class, 'reject'])->name('dsar.reject');
        Route::post('/dsar/{dsarRequest}/notify-downstream', [DsarController::class, 'notifyDownstream'])->name('dsar.notify-downstream');
    });
});
