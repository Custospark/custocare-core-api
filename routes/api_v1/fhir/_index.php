<?php

use App\Http\Controllers\Api\Fhir\FhirPatientController;
use Illuminate\Support\Facades\Route;

/*
|──────────────────────────────────────────────────────────────────────────────
| FHIR R4 stubs (authenticated) - exchange readiness, narrow by design.
|──────────────────────────────────────────────────────────────────────────────
*/
Route::prefix('fhir')->name('fhir.')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/Patient/{id}', [FhirPatientController::class, 'show'])
        ->name('patient.show');
});
