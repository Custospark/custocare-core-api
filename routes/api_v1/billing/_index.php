<?php

use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\BillableItemsController;
use Illuminate\Support\Facades\Route;

// Billing routes
Route::prefix('billing')->middleware(['auth:sanctum'])->group(function () {
    // Finalize and persist billing data for a visit
    Route::post('/finalize', [BillingController::class, 'finalize']);
    
    // Retrieve billing data for a specific visit
    Route::get('/visit/{visitId}', [BillingController::class, 'getByVisit']);
    
    // Get all billing records for a facility with pagination, filtering, and search
    Route::get('/facility/{facilityId}', [BillingController::class, 'getByFacility']);
    
    //Get billing statistics for a facility (dashboard metrics)
    Route::get('/facility/{facilityId}/statistics', [BillingController::class, 'getFacilityStatistics']);
    
    // Get billing by patient across all visits
    Route::get('/patient/{patientId}', [BillingController::class, 'getByPatient']);
});
