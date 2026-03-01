<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\FacilityController;

/*
|--------------------------------------------------------------------------
| API Routes for Facility Management
|--------------------------------------------------------------------------
|
| These routes handle all facility-related operations including
| CRUD operations, search, filtering, and metrics updates.
|
*/

Route::middleware(['auth:sanctum'])->group(function () {
     
   //Get Facility Identity.

    Route::get('facility/identity', [FacilityController::class, 'getFacilityDetails']);

    
    // Facility resource routes
    Route::apiResource('facilities', FacilityController::class)
        ->parameters(['facilities' => 'facility:facility_uuid'])
        ->except(['destroy']); // Exclude destroy to use soft delete
    
    // Soft delete facility
    Route::delete('facilities/{facility:facility_uuid}', [FacilityController::class, 'destroy'])
        ->name('facilities.destroy')
        ->middleware('can:delete,facility');

    
    // Force delete facility (permanent)
    Route::delete('facilities/{facility:facility_uuid}/force', [FacilityController::class, 'forceDelete'])
        ->name('facilities.force-delete')
        ->middleware('can:forceDelete,facility');
    
    // Restore soft-deleted facility
    Route::post('facilities/{facility:facility_uuid}/restore', [FacilityController::class, 'restore'])
        ->name('facilities.restore')
        ->middleware('can:restore,facility');
    
    // Search facilities
    Route::get('facilities/search/{query}', [FacilityController::class, 'search'])
        ->name('facilities.search')
        ->where('query', '.*');
    
    // Get facilities by location
    Route::get('facilities/location/{country_code}', [FacilityController::class, 'byLocation'])
        ->name('facilities.by-location');
    
    Route::get('facilities/location/{country_code}/{state_province}', [FacilityController::class, 'byLocation'])
        ->name('facilities.by-location-state');
    
    Route::get('facilities/location/{country_code}/{state_province}/{city}', [FacilityController::class, 'byLocation'])
        ->name('facilities.by-location-city');
    
    // Get facilities by type and status
    Route::get('facilities/type/{type}/status/{status}', [FacilityController::class, 'byTypeAndStatus'])
        ->name('facilities.by-type-status');
    
    // Get facilities with emergency departments
    Route::get('facilities/with-emergency-departments', [FacilityController::class, 'withEmergencyDepartments'])
        ->name('facilities.with-emergency-departments');
    
    // Update facility metrics
    Route::patch('facilities/{facility:facility_uuid}/metrics', [FacilityController::class, 'updateMetrics'])
        ->name('facilities.update-metrics')
        ->middleware('can:updateMetrics,facility');
    
    // Check facility operational status
    Route::get('facilities/{facility:facility_uuid}/operational-status', [FacilityController::class, 'operationalStatus'])
        ->name('facilities.operational-status')
        ->middleware('can:viewOperationalStatus,facility');
    
    // Update facility operational status
    Route::patch('facilities/{facility:facility_uuid}/operational-status', [FacilityController::class, 'updateOperationalStatus'])
        ->name('facilities.update-operational-status')
        ->middleware('can:updateOperationalStatus,facility');
});

Route::middleware(['auth:sanctum'])->prefix('facilities')->name('facilities.')->group(function () {

    // ── Settings (read all grouped fields) ────────────────────────────────
    Route::get('/{facility}/settings', [FacilityController::class, 'getSettings'])
        ->name('settings.show');

    // ── Settings (update individual fields from any group) ────────────────
    Route::put('/{facility}/settings', [FacilityController::class, 'updateSettings'])
        ->name('settings.update');

    // ── Logo (upload / replace facility logo) ─────────────────────────────
    Route::post('/{facility}/settings/logo', [FacilityController::class, 'uploadFacilityLogo'])
        ->name('settings.logo.upload');
});