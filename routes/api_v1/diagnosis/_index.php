<?php

declare(strict_types=1);

use App\Http\Controllers\Api\DiagnosisController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Diagnoses API Routes
|--------------------------------------------------------------------------
|
| Routes for managing patient diagnoses (ICD codes, clinical status, etc.)
|
*/

Route::middleware(['auth:sanctum'])->group(function () {
    
    Route::prefix('diagnoses')->group(function () {
        
        // Standard CRUD operations
        Route::get('/', [DiagnosisController::class, 'index']);
        Route::post('/', [DiagnosisController::class, 'store']);
        Route::get('/search', [DiagnosisController::class, 'search']);
        Route::get('/suggest-icd', [DiagnosisController::class, 'suggestIcd']);
        Route::get('/most-common', [DiagnosisController::class, 'mostCommon']);
        
        // Patient-specific routes
        Route::get('/patient/{patientId}', [DiagnosisController::class, 'patientDiagnoses']);
        Route::get('/patient/{patientId}/primary', [DiagnosisController::class, 'primaryDiagnoses']);
        Route::get('/patient/{patientId}/statistics', [DiagnosisController::class, 'statistics']);
        
        // Visit-specific routes
        Route::get('/visit/{visitId}', [DiagnosisController::class, 'visitDiagnoses']);
        
        // Diagnosis-specific operations
        Route::get('/{id}', [DiagnosisController::class, 'show']);
        Route::put('/{id}', [DiagnosisController::class, 'update']);
        Route::delete('/{id}', [DiagnosisController::class, 'destroy']);
        
        // Workflow operations
        Route::post('/{id}/verify', [DiagnosisController::class, 'verify']);
        Route::post('/{id}/dispute', [DiagnosisController::class, 'dispute']);
        Route::post('/{id}/resolve', [DiagnosisController::class, 'resolve']);
        Route::post('/{id}/reactivate', [DiagnosisController::class, 'reactivate']);
        
        // Restoration
        Route::post('/{id}/restore', [DiagnosisController::class, 'restore']);
    });
});