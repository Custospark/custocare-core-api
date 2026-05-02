<?php

declare(strict_types=1);

use App\Http\Controllers\Api\VitalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Vitals API Routes
|--------------------------------------------------------------------------
|
| Routes for managing patient vital signs measurements
|
*/

Route::middleware(['auth:sanctum'])->group(function () {
    
    Route::prefix('vitals')->group(function () {
        
        // Standard CRUD operations
        Route::get('/', [VitalController::class, 'index']);
        Route::post('/', [VitalController::class, 'store']);
        
        // Specialized listing routes
        Route::get('/abnormal', [VitalController::class, 'abnormal']);
        Route::get('/critical', [VitalController::class, 'critical']);
        Route::get('/statistics', [VitalController::class, 'statistics']);
        
        // Patient-specific routes
        Route::get('/patient/{patientId}', [VitalController::class, 'patientVitals']);
        Route::get('/patient/{patientId}/trend', [VitalController::class, 'trend']);
        
        // Visit-specific routes
        Route::get('/visit/{visitId}', [VitalController::class, 'visitVitals']);
        
        // Vital record operations
        Route::get('/{id}', [VitalController::class, 'show']);
        Route::put('/{id}', [VitalController::class, 'update']);
        Route::delete('/{id}', [VitalController::class, 'destroy']);
    });
});