<?php

use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\BillableItemsController;
use App\Http\Controllers\Api\BillingRevenueDashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix('billing')->middleware(['auth:sanctum'])->group(function () {
    /** Cross-facility receipts for the authenticated patient's own chart (no X-Facility-Id). */
    Route::get('/patient-portal', [BillingController::class, 'patientPortalIndex']);

    Route::post('/save', [BillingController::class, 'saveBilling']);
    
    // New endpoint that returns data in facility format
    Route::get('/visit/{visitId}/facility-format', [BillingController::class, 'getByVisitForFacility']);
    
    // Original endpoint (kept for backward compatibility)
    Route::get('/visit/{visitId}', [BillingController::class, 'getByVisit']);
    
    Route::patch('/line-item/{lineItemId}/adjust', [BillingController::class, 'adjustLineItem']);
    Route::get('/facility/{facilityId}', [BillingController::class, 'getByFacility']);
    Route::get('/facility/{facilityId}/statistics', [BillingController::class, 'getFacilityStatistics']);
    Route::get('/patient/{patientId}', [BillingController::class, 'getByPatient']);

    Route::get('/dashboard/revenue', [BillingRevenueDashboardController::class, 'index']);

});
