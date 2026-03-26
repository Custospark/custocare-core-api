<?php

use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\BillableItemsController;
use Illuminate\Support\Facades\Route;

Route::prefix('billing')->middleware(['auth:sanctum'])->group(function () {
    Route::post('/save', [BillingController::class, 'saveBilling']);
    Route::get('/visit/{visitId}', [BillingController::class, 'getByVisit']);
    Route::patch('/line-item/{lineItemId}/adjust', [BillingController::class, 'adjustLineItem']);
    Route::get('/facility/{facilityId}', [BillingController::class, 'getByFacility']);
    Route::get('/facility/{facilityId}/statistics', [BillingController::class, 'getFacilityStatistics']);
    Route::get('/patient/{patientId}', [BillingController::class, 'getByPatient']);
});

