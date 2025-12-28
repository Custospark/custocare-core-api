<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ClinicalDocumentController;

// Clinical Documents API Routes
Route::prefix('clinical-documents')->middleware(['auth:sanctum'])->group(function () {
    // Main RESTful routes
    Route::get('/', [ClinicalDocumentController::class, 'index'])
        ->name('clinical-documents.index')
        ->middleware('can:viewAny,App\Models\ClinicalDocument');
    
    Route::post('/', [ClinicalDocumentController::class, 'store'])
        ->name('clinical-documents.store')
        ->middleware('can:create,App\Models\ClinicalDocument');
    
    Route::get('/{clinicalDocument}', [ClinicalDocumentController::class, 'show'])
        ->name('clinical-documents.show')
        ->middleware('can:view,clinicalDocument');
    
    Route::put('/{clinicalDocument}', [ClinicalDocumentController::class, 'update'])
        ->name('clinical-documents.update')
        ->middleware('can:update,clinicalDocument');
    
    Route::delete('/{clinicalDocument}', [ClinicalDocumentController::class, 'destroy'])
        ->name('clinical-documents.destroy')
        ->middleware('can:delete,clinicalDocument');
    
    // Additional routes
    Route::get('/patient/{patientId}', [ClinicalDocumentController::class, 'byPatient'])
        ->name('clinical-documents.by-patient')
        ->middleware('can:viewAny,App\Models\ClinicalDocument');
    
    Route::get('/{clinicalDocument}/download', [ClinicalDocumentController::class, 'download'])
        ->name('clinical-documents.download')
        ->middleware('can:download,clinicalDocument');
    
    Route::get('/{clinicalDocument}/verify', [ClinicalDocumentController::class, 'verifyIntegrity'])
        ->name('clinical-documents.verify')
        ->middleware('can:view,clinicalDocument');
    
    Route::patch('/{clinicalDocument}/status', [ClinicalDocumentController::class, 'updateStatus'])
        ->name('clinical-documents.update-status')
        ->middleware('can:updateStatus,clinicalDocument');
    
    Route::get('/statistics', [ClinicalDocumentController::class, 'statistics'])
        ->name('clinical-documents.statistics')
        ->middleware('can:viewAny,App\Models\ClinicalDocument');
});

// Optional: Add these routes if you need UUID-based access
Route::prefix('clinical-documents')->middleware(['auth:api'])->group(function () {
    Route::get('/uuid/{uuid}', function ($uuid) {
        $controller = app(ClinicalDocumentController::class);
        // return $controller->showByUuid($uuid);
    })->name('clinical-documents.by-uuid');
});