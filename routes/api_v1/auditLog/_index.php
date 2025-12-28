<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuditLogController;

// Audit Logs Routes
Route::prefix('audit-logs')->middleware('auth:sanctum')->name('audit-logs.')->group(function () {
    // Standard RESTful routes
    Route::get('/', [AuditLogController::class, 'index'])->name('index');
    Route::post('/', [AuditLogController::class, 'store'])->name('store');
    Route::get('/{id}', [AuditLogController::class, 'show'])->name('show')
        ->where('id', '[0-9]+');
    Route::put('/{id}', [AuditLogController::class, 'update'])->name('update')
        ->where('id', '[0-9]+');
    Route::delete('/{id}', [AuditLogController::class, 'destroy'])->name('destroy')
        ->where('id', '[0-9]+');
    
    // Entity-specific routes
    Route::get('/entity/{entity_type}/{entity_id?}', [AuditLogController::class, 'entity'])
        ->name('entity')
        ->where('entity_id', '[0-9]+');
    
    // Patient routes
    Route::get('/patient/{patientId}', [AuditLogController::class, 'patient'])
        ->name('patient')
        ->where('patientId', '[0-9]+');
    
    // HIPAA accounting
    Route::get('/patient/{patientId}/hippa-accounting', [AuditLogController::class, 'hippaAccounting'])
        ->name('hippa-accounting')
        ->where('patientId', '[0-9]+');
    
    // PHI access logs
    Route::get('/phi-access', [AuditLogController::class, 'phiAccess'])->name('phi-access');
    
    // Request ID tracing
    Route::get('/request/{requestId}', [AuditLogController::class, 'request'])->name('request');
    
    // Statistics
    Route::get('/statistics', [AuditLogController::class, 'statistics'])->name('statistics');
    
    // Export
    Route::post('/export', [AuditLogController::class, 'export'])->name('export');
    
    // Maintenance routes
    Route::post('/archive', [AuditLogController::class, 'archive'])->name('archive');
    Route::post('/purge', [AuditLogController::class, 'purge'])->name('purge');
    
    // Legal hold management
    Route::post('/{id}/legal-hold', [AuditLogController::class, 'placeLegalHold'])
        ->name('legal-hold.place')
        ->where('id', '[0-9]+');
    Route::delete('/{id}/legal-hold', [AuditLogController::class, 'releaseLegalHold'])
        ->name('legal-hold.release')
        ->where('id', '[0-9]+');
});

// Add middleware group for audit log routes
Route::middleware(['api', 'auth:api'])->group(function () {
    // All audit log routes are already protected by middleware in controller
    // Additional global middleware can be added here if needed
});