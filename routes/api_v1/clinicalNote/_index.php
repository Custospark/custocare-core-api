<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ClinicalNoteController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Clinical Notes API Routes
|--------------------------------------------------------------------------
|
| Routes for managing clinical notes (SOAP notes, progress notes, etc.)
|
*/

Route::middleware(['auth:sanctum'])->group(function () {
    
    Route::prefix('clinical-notes')->group(function () {
        
        // Standard CRUD operations
        Route::get('/', [ClinicalNoteController::class, 'index']);
        Route::post('/', [ClinicalNoteController::class, 'store']);
        Route::get('/search', [ClinicalNoteController::class, 'search']);
        Route::get('/statistics', [ClinicalNoteController::class, 'statistics']);
        
        // Patient-specific routes
        Route::get('/patient/{patientId}', [ClinicalNoteController::class, 'patientNotes']);
        Route::get('/patient/{patientId}/latest', [ClinicalNoteController::class, 'latest']);
        
        // Visit-specific routes
        Route::get('/visit/{visitId}', [ClinicalNoteController::class, 'visitNotes']);
        
        // Note-specific operations
        Route::get('/{uuid}', [ClinicalNoteController::class, 'show']);
        Route::put('/{uuid}', [ClinicalNoteController::class, 'update']);
        Route::delete('/{uuid}', [ClinicalNoteController::class, 'destroy']);
        
        // Workflow operations
        Route::post('/{uuid}/finalize', [ClinicalNoteController::class, 'finalize']);
        Route::post('/{uuid}/cancel', [ClinicalNoteController::class, 'cancel']);
        Route::post('/{uuid}/amend', [ClinicalNoteController::class, 'amend']);
        
        // History and restoration
        Route::get('/{uuid}/history', [ClinicalNoteController::class, 'history']);
        Route::post('/{uuid}/restore', [ClinicalNoteController::class, 'restore']);
    });
});