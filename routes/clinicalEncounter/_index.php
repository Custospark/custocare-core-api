<?php

use App\Http\Controllers\Api\ClinicalEncounterController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes for Clinical Encounters
|--------------------------------------------------------------------------
|
| These routes handle all clinical encounter operations including
| SOAP documentation, signing, amendments, and billing integration.
|
*/

Route::middleware(['api','auth:suntum'])->group(function () {
    
    // Clinical Encounters Resource Routes
    Route::prefix('clinical-encounters')->middleware(['auth:api'])->group(function () {
        // Standard RESTful routes
        Route::get('/', [ClinicalEncounterController::class, 'index']);
        Route::post('/', [ClinicalEncounterController::class, 'store']);
        Route::get('/{clinicalEncounter:encounter_uuid}', [ClinicalEncounterController::class, 'show']);
        Route::put('/{clinicalEncounter:encounter_uuid}', [ClinicalEncounterController::class, 'update']);
        Route::delete('/{clinicalEncounter:encounter_uuid}', [ClinicalEncounterController::class, 'destroy']);
        
        // Special operations
        Route::post('/{clinicalEncounter:encounter_uuid}/restore', [ClinicalEncounterController::class, 'restore']);
        Route::post('/{clinicalEncounter:encounter_uuid}/sign', [ClinicalEncounterController::class, 'sign']);
        Route::post('/{clinicalEncounter:encounter_uuid}/amend', [ClinicalEncounterController::class, 'amend']);
        
        // Utility endpoints
        Route::get('/{clinicalEncounter:encounter_uuid}/validate-completeness', 
            [ClinicalEncounterController::class, 'validateCompleteness']);
        Route::get('/{clinicalEncounter:encounter_uuid}/billing-information', 
            [ClinicalEncounterController::class, 'billingInformation']);
        
        // Reporting endpoints
        Route::get('/requiring-attention', [ClinicalEncounterController::class, 'requiringAttention']);
        Route::get('/incomplete-documentation', [ClinicalEncounterController::class, 'incompleteDocumentation']);
    });
    
    // Alternative routes for integration
    Route::prefix('encounters')->middleware(['auth:api'])->group(function () {
        Route::get('/patient/{patientId}', function ($patientId) {
            // This would be handled by a separate controller or the main controller
            // with additional logic for patient-specific encounters
            return app(ClinicalEncounterController::class)->index(
                request()->merge(['patient_id' => $patientId])
            );
        })->whereNumber('patientId');
        
        Route::get('/provider/{providerId}', function ($providerId) {
            // Provider-specific encounters
            return app(ClinicalEncounterController::class)->index(
                request()->merge(['primary_provider_staff_id' => $providerId])
            );
        })->whereNumber('providerId');
        
        Route::get('/visit/{visitId}', function ($visitId) {
            // Visit-specific encounters
            return app(ClinicalEncounterController::class)->index(
                request()->merge(['visit_id' => $visitId])
            );
        })->whereNumber('visitId');
    });
});