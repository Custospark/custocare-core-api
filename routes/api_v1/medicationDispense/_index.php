<?php

use App\Http\Controllers\Api\MedicationDispenseController;
use Illuminate\Support\Facades\Route;

// Medication Dispense Routes
Route::prefix('medication-dispenses')->name('medication-dispenses.')->group(function () {
    // Basic CRUD operations
    Route::get('/', [MedicationDispenseController::class, 'index'])
        ->name('index')
        ->middleware(['auth:sanctum', 'ability:view-medication-dispenses']);

    Route::post('/', [MedicationDispenseController::class, 'store'])
        ->name('store')
        ->middleware(['auth:sanctum', 'ability:create-medication-dispenses']);

    Route::prefix('{dispense}')->group(function () {
        Route::get('/', [MedicationDispenseController::class, 'show'])
            ->name('show')
            ->middleware(['auth:sanctum', 'ability:view-medication-dispense']);

        Route::put('/', [MedicationDispenseController::class, 'update'])
            ->name('update')
            ->middleware(['auth:sanctum', 'ability:update-medication-dispense']);

        Route::patch('/', [MedicationDispenseController::class, 'update'])
            ->middleware(['auth:sanctum', 'ability:update-medication-dispense']);

        Route::delete('/', [MedicationDispenseController::class, 'destroy'])
            ->name('destroy')
            ->middleware(['auth:sanctum', 'ability:delete-medication-dispense']);

        // Specialized operations
        Route::post('/verify', [MedicationDispenseController::class, 'verify'])
            ->name('verify')
            ->middleware(['auth:sanctum', 'ability:verify-medication-dispense']);

        Route::post('/mark-picked-up', [MedicationDispenseController::class, 'markAsPickedUp'])
            ->name('mark-picked-up')
            ->middleware(['auth:sanctum', 'ability:mark-medication-dispense-picked-up']);

        Route::patch('/status', [MedicationDispenseController::class, 'updateStatus'])
            ->name('update-status')
            ->middleware(['auth:sanctum', 'ability:update-medication-dispense-status']);
    });

    // Search and filter operations
    Route::get('/prescription/{prescriptionId}', [MedicationDispenseController::class, 'getByPrescription'])
        ->name('by-prescription')
        ->middleware(['auth:sanctum', 'ability:view-medication-dispenses']);

    Route::get('/patient/{patientId}', [MedicationDispenseController::class, 'getByPatient'])
        ->name('by-patient')
        ->middleware(['auth:sanctum', 'ability:view-patient-medication-dispenses']);

    // Reporting
    Route::get('/facility/{facilityId}/statistics', [MedicationDispenseController::class, 'getFacilityStatistics'])
        ->name('facility-statistics')
        ->middleware(['auth:sanctum', 'ability:view-medication-dispense-statistics']);
});