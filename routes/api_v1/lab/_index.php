<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Lab\LabTemplateController;
use App\Http\Controllers\Api\Lab\LabTestController;
use App\Http\Controllers\Api\Lab\LabTemplateFieldController;
use App\Http\Controllers\Api\Lab\LabRequestController;
use App\Http\Controllers\Api\Lab\LabRequestItemController;
use App\Http\Controllers\Api\Lab\LabResultController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Lab Module
|--------------------------------------------------------------------------
|
| All routes are under 'api/lab' prefix and require authentication
|
*/

Route::middleware(['auth:sanctum'])->prefix('lab')->group(function () {
    
    // ─────────────────────────────────────────────────────────────────────
    // 1. LAB TEMPLATE ROUTES
    // ─────────────────────────────────────────────────────────────────────
    Route::prefix('templates')->group(function () {
        // Basic CRUD
        Route::get('/', [LabTemplateController::class, 'index']);
        Route::post('/', [LabTemplateController::class, 'store']);
        Route::get('/active', [LabTemplateController::class, 'active']);
        Route::get('/shared', [LabTemplateController::class, 'shared']);
        Route::get('/{uuid}', [LabTemplateController::class, 'show']);
        Route::put('/{uuid}', [LabTemplateController::class, 'update']);
        Route::delete('/{uuid}', [LabTemplateController::class, 'destroy']);
        
        // Status management
        Route::post('/{uuid}/activate', [LabTemplateController::class, 'activate']);
        Route::post('/{uuid}/deactivate', [LabTemplateController::class, 'deactivate']);
        
        // Relationships
        Route::get('/{uuid}/with-relations', [LabTemplateController::class, 'withRelations']);
        
        // Copy functionality
        Route::post('/{uuid}/copy-to-facility', [LabTemplateController::class, 'copyToFacility']);
    });
    
    // ─────────────────────────────────────────────────────────────────────
    // 2. LAB TEST ROUTES
    // ─────────────────────────────────────────────────────────────────────
    Route::prefix('tests')->group(function () {
        // Basic CRUD
        Route::get('/', [LabTestController::class, 'index']);
        Route::post('/', [LabTestController::class, 'store']);
        Route::get('/fasting', [LabTestController::class, 'fasting']);
        Route::get('/popular', [LabTestController::class, 'popular']);
        Route::get('/category/{category}', [LabTestController::class, 'byCategory']);
        Route::get('/{uuid}', [LabTestController::class, 'show']);
        Route::put('/{uuid}', [LabTestController::class, 'update']);
        Route::delete('/{uuid}', [LabTestController::class, 'destroy']);
        
        // Status management
        Route::post('/{uuid}/activate', [LabTestController::class, 'activate']);
        Route::post('/{uuid}/deactivate', [LabTestController::class, 'deactivate']);
        
        // Relationships
        Route::get('/template/{templateUuid}', [LabTestController::class, 'byTemplate']);
        
        // Statistics
        Route::get('/{uuid}/statistics', [LabTestController::class, 'statistics']);
    });
    
    // ─────────────────────────────────────────────────────────────────────
    // 3. LAB TEMPLATE FIELD ROUTES
    // ─────────────────────────────────────────────────────────────────────
    Route::prefix('template-fields')->group(function () {
        // Basic CRUD
        Route::get('/', [LabTemplateFieldController::class, 'index']);
        Route::post('/', [LabTemplateFieldController::class, 'store']);
        Route::get('/{uuid}', [LabTemplateFieldController::class, 'show']);
        Route::put('/{uuid}', [LabTemplateFieldController::class, 'update']);
        Route::delete('/{uuid}', [LabTemplateFieldController::class, 'destroy']);
        
        // Status management
        Route::post('/{uuid}/activate', [LabTemplateFieldController::class, 'activate']);
        Route::post('/{uuid}/deactivate', [LabTemplateFieldController::class, 'deactivate']);
        
        // Relationships
        Route::get('/template/{templateUuid}', [LabTemplateFieldController::class, 'byTemplate']);
        Route::get('/template/{templateUuid}/active', [LabTemplateFieldController::class, 'activeByTemplate']);
        Route::get('/template/{templateUuid}/required', [LabTemplateFieldController::class, 'requiredByTemplate']);
        Route::get('/template/{templateUuid}/critical', [LabTemplateFieldController::class, 'criticalByTemplate']);
        
        // Bulk operations
        Route::post('/template/{templateUuid}/bulk', [LabTemplateFieldController::class, 'bulkStore']);
        Route::put('/bulk/orders', [LabTemplateFieldController::class, 'bulkUpdateOrders']);
        
        // Duplicate functionality
        Route::post('/duplicate', [LabTemplateFieldController::class, 'duplicate']);
        
        // Validation
        Route::post('/{uuid}/validate', [LabTemplateFieldController::class, 'validateValue']);
    });
    
    // ─────────────────────────────────────────────────────────────────────
    // 4. LAB REQUEST ROUTES
    // ─────────────────────────────────────────────────────────────────────
    Route::prefix('requests')->group(function () {
        // Basic CRUD
        Route::get('/', [LabRequestController::class, 'index']);
        Route::post('/', [LabRequestController::class, 'store']);
        Route::get('/pending', [LabRequestController::class, 'pending']);
        Route::get('/requiring-attention', [LabRequestController::class, 'requiringAttention']);
        Route::get('/statistics', [LabRequestController::class, 'statistics']);
        Route::get('/facility/{facilityId}', [LabRequestController::class, 'byFacility']);
        Route::get('/patient/{patientId}', [LabRequestController::class, 'byPatient']);
        Route::get('/visit/{visitId}', [LabRequestController::class, 'byVisit']);
        Route::get('/{uuid}', [LabRequestController::class, 'show']);
        Route::put('/{uuid}', [LabRequestController::class, 'update']);
        Route::delete('/{uuid}', [LabRequestController::class, 'destroy']);
        
        // Status management
        Route::put('/{uuid}/status', [LabRequestController::class, 'updateStatus']);
        Route::post('/{uuid}/cancel', [LabRequestController::class, 'cancel']);
        
        // Relationships
        Route::get('/{uuid}/with-items', [LabRequestController::class, 'withItems']);
        Route::get('/{uuid}/with-full-details', [LabRequestController::class, 'withFullDetails']);
        
        // Item operations
        Route::post('/with-items', [LabRequestController::class, 'storeWithItems']);
        Route::post('/{uuid}/add-items', [LabRequestController::class, 'addItems']);
    });
    
    // ─────────────────────────────────────────────────────────────────────
    // 5. LAB REQUEST ITEM ROUTES
    // ─────────────────────────────────────────────────────────────────────
    Route::prefix('request-items')->group(function () {
        // Basic CRUD
        Route::get('/', [LabRequestItemController::class, 'index']);
        Route::post('/', [LabRequestItemController::class, 'store']);
        Route::get('/pending', [LabRequestItemController::class, 'pending']);
        Route::get('/abnormal-results', [LabRequestItemController::class, 'abnormalResults']);
        Route::get('/awaiting-verification', [LabRequestItemController::class, 'awaitingVerification']);
        Route::get('/{uuid}', [LabRequestItemController::class, 'show']);
        Route::put('/{uuid}', [LabRequestItemController::class, 'update']);
        Route::delete('/{uuid}', [LabRequestItemController::class, 'destroy']);
        
        // Workflow management
        Route::put('/{uuid}/status', [LabRequestItemController::class, 'updateStatus']);
        Route::post('/{uuid}/collect-sample', [LabRequestItemController::class, 'markSampleCollected']);
        Route::post('/{uuid}/start', [LabRequestItemController::class, 'markInProgress']);
        Route::post('/{uuid}/complete', [LabRequestItemController::class, 'markCompleted']);
        Route::post('/{uuid}/verify', [LabRequestItemController::class, 'markVerified']);
        Route::post('/{uuid}/cancel', [LabRequestItemController::class, 'cancel']);
        
        // Relationships
        Route::get('/request/{requestUuid}', [LabRequestItemController::class, 'byLabRequest']);
        Route::get('/test/{testUuid}', [LabRequestItemController::class, 'byLabTest']);
        Route::get('/{uuid}/with-results', [LabRequestItemController::class, 'withResults']);
        Route::get('/{uuid}/with-full-details', [LabRequestItemController::class, 'withFullDetails']);
        
        // Statistics
        Route::get('/test/{testUuid}/turnaround-time', [LabRequestItemController::class, 'turnaroundTime']);
        
        // Bulk operations
        Route::post('/bulk/status', [LabRequestItemController::class, 'bulkUpdateStatus']);
    });
    
    // ─────────────────────────────────────────────────────────────────────
    // 6. LAB RESULT ROUTES
    // ─────────────────────────────────────────────────────────────────────
    Route::prefix('results')->group(function () {
        // Basic CRUD
        Route::get('/', [LabResultController::class, 'index']);
        Route::post('/', [LabResultController::class, 'store']);
        Route::get('/abnormal', [LabResultController::class, 'abnormal']);
        Route::get('/critical', [LabResultController::class, 'critical']);
        Route::get('/critical/requiring-attention', [LabResultController::class, 'criticalRequiringAttention']);
        Route::get('/unverified', [LabResultController::class, 'unverified']);
        Route::get('/statistics', [LabResultController::class, 'statistics']);
        Route::get('/export', [LabResultController::class, 'export']);
        Route::get('/flag/{flag}', [LabResultController::class, 'byFlag']);
        Route::get('/patient/{patientId}', [LabResultController::class, 'byPatient']);
        Route::get('/{uuid}', [LabResultController::class, 'show']);
        Route::put('/{uuid}', [LabResultController::class, 'update']);
        Route::delete('/{uuid}', [LabResultController::class, 'destroy']);
        
        // Verification
        Route::post('/{uuid}/verify', [LabResultController::class, 'verify']);
        
        // Relationships
        Route::get('/item/{itemUuid}', [LabResultController::class, 'byLabRequestItem']);
        Route::get('/field/{fieldUuid}', [LabResultController::class, 'byTemplateField']);
        Route::get('/{uuid}/with-relations', [LabResultController::class, 'withRelations']);
        
        // Analysis
        Route::get('/test/{testUuid}/trends', [LabResultController::class, 'trends']);
        
        // Bulk operations
        Route::post('/item/{itemUuid}/bulk', [LabResultController::class, 'bulkStore']);
        
        // Result management
        Route::post('/{uuid}/critical-alert-sent', [LabResultController::class, 'markCriticalAlertSent']);
        Route::post('/{uuid}/recalculate-flag', [LabResultController::class, 'recalculateFlag']);
    });
});