<?php

use App\Http\Controllers\Api\PatientConsentController;
use Illuminate\Support\Facades\Route;

// Patient Consent Routes
Route::prefix('patient-consents')->middleware(['api', 'auth:sanctum'])->group(function () {
    // Standard RESTful routes
    Route::post('/', [PatientConsentController::class, 'store']);
    Route::get('/options', [PatientConsentController::class, 'options']);
    Route::get('/statistics', [PatientConsentController::class, 'statistics']);
    Route::get('/expiring', [PatientConsentController::class, 'expiring']);
    
    // Patient-specific routes
    Route::prefix('patient/{patientId}')->group(function () {
        Route::get('/', [PatientConsentController::class, 'index']);
        Route::get('/validate/{consentType}', [PatientConsentController::class, 'validateConsent']);
    });
    
    // Consent-specific routes
    Route::prefix('{consent}')->group(function () {
        Route::get('/', [PatientConsentController::class, 'show']);
        Route::put('/', [PatientConsentController::class, 'update']);
        Route::post('/revoke', [PatientConsentController::class, 'revoke']);
    });
});