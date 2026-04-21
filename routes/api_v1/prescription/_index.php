<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Billing\PrescriptionBillingController;
use App\Http\Controllers\Api\ClinicalTemplateController;
use App\Http\Controllers\Api\PrescriptionController;
use App\Http\Controllers\Api\PrescriptionItemController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Version 1
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum'])->group(function () {
    
    // ─── Prescription Routes ─────────────────────────────────────────────
    Route::prefix('prescriptions')->group(function () {
        Route::get('/', [PrescriptionController::class, 'index']);
        Route::get('/paginate', [PrescriptionController::class, 'paginate']);
        Route::post('/', [PrescriptionController::class, 'store']);
        Route::get('/patient/{patientId}', [PrescriptionController::class, 'patientPrescriptions']);
        Route::post('/{id}/apply-template', [PrescriptionController::class, 'applyTemplate']);
        Route::post('/{id}/cancel', [PrescriptionController::class, 'cancel']);
        Route::post('/{id}/dispense', [PrescriptionController::class, 'markDispensed']);
        Route::get('/{id}/billing', [PrescriptionController::class, 'getForBilling']);
        Route::get('/{id}', [PrescriptionController::class, 'show']);
        Route::put('/{id}', [PrescriptionController::class, 'update']);
        Route::delete('/{id}', [PrescriptionController::class, 'destroy']);
        
        // ─── Prescription Items Routes (nested) ───────────────────────────
        Route::get('/{prescriptionId}/items', [PrescriptionItemController::class, 'index']);
        Route::post('/{prescriptionId}/items', [PrescriptionItemController::class, 'store']);
        Route::put('/{prescriptionId}/items/bulk', [PrescriptionItemController::class, 'bulkUpdate']);
    });
    
    // ─── Prescription Item Routes (standalone) ───────────────────────────
    Route::prefix('prescription-items')->group(function () {
        Route::put('/{id}', [PrescriptionItemController::class, 'update']);
        Route::delete('/{id}', [PrescriptionItemController::class, 'destroy']);
    });
    
    // ─── Clinical Template Routes ────────────────────────────────────────
    Route::prefix('clinical-templates')->group(function () {
        Route::get('/', [ClinicalTemplateController::class, 'index']);
        Route::get('/categories', [ClinicalTemplateController::class, 'categories']);
        Route::get('/facility', [ClinicalTemplateController::class, 'facilityTemplates']);
        Route::get('/category/{category}', [ClinicalTemplateController::class, 'byCategory']);
        Route::get('/search', [ClinicalTemplateController::class, 'search']);
        Route::post('/', [ClinicalTemplateController::class, 'store']);
        Route::post('/{id}/toggle-status', [ClinicalTemplateController::class, 'toggleStatus']);
        Route::get('/{id}', [ClinicalTemplateController::class, 'show']);
        Route::put('/{id}', [ClinicalTemplateController::class, 'update']);
        Route::delete('/{id}', [ClinicalTemplateController::class, 'destroy']);
    });
    
    // ─── Prescription Billing Routes (For billing module import) ─────────
    Route::prefix('billing/prescriptions')->group(function () {
        Route::get('/patient/{patientId}', [PrescriptionBillingController::class, 'getForPatient']);
        Route::post('/multiple', [PrescriptionBillingController::class, 'getMultiple']);
        Route::get('/{prescriptionId}', [PrescriptionBillingController::class, 'getPrescription']);
    });
    
});