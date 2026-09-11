<?php

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
});
