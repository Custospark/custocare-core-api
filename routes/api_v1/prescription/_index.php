<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PrescriptionController;

/*
|--------------------------------------------------------------------------
| Prescription API Routes
|--------------------------------------------------------------------------
|
| These routes are for managing prescriptions via RESTful API.
|
*/

Route::middleware(['auth:api','auth:suntum'])->group(function () {
    
    // Prescription Resource Routes
    Route::apiResource('prescriptions', PrescriptionController::class)
        ->except(['edit', 'create'])
        ->parameters(['prescriptions' => 'prescription:prescription_uuid']);
    
    // Additional Prescription Endpoints
    Route::prefix('prescriptions')->group(function () {
        // Refill Management
        Route::post('/{prescription:prescription_uuid}/refill', [PrescriptionController::class, 'refill'])
            ->name('prescriptions.refill');
        
        Route::get('/{prescription:prescription_uuid}/refill-eligibility', [PrescriptionController::class, 'checkRefillEligibility'])
            ->name('prescriptions.refill-eligibility');
        
        // Dispense Status Management
        Route::patch('/{prescription:prescription_uuid}/dispense-status', [PrescriptionController::class, 'updateDispenseStatus'])
            ->name('prescriptions.update-dispense-status');
        
        // Discontinue Prescription
        Route::post('/{prescription:prescription_uuid}/discontinue', [PrescriptionController::class, 'discontinue'])
            ->name('prescriptions.discontinue');
        
        // Transmit Prescription
        Route::post('/{prescription:prescription_uuid}/transmit', [PrescriptionController::class, 'transmit'])
            ->name('prescriptions.transmit');
        
        // Statistics
        Route::get('/statistics', [PrescriptionController::class, 'statistics'])
            ->name('prescriptions.statistics');
        
        // Prescriptions needing transmission
        Route::get('/needs-transmission', [PrescriptionController::class, 'needsTransmission'])
            ->name('prescriptions.needs-transmission');
        
        // Patient-specific prescriptions
        Route::get('/patient/{patient}', [PrescriptionController::class, 'patientPrescriptions'])
            ->name('prescriptions.patient');
        
        // Provider-specific prescriptions
        Route::get('/provider/{provider}', [PrescriptionController::class, 'providerPrescriptions'])
            ->name('prescriptions.provider');
    });
    
    // Batch Operations
    Route::prefix('batch/prescriptions')->group(function () {
        Route::post('/transmit', [PrescriptionController::class, 'batchTransmit'])
            ->name('prescriptions.batch.transmit');
        
        Route::post('/update-status', [PrescriptionController::class, 'batchUpdateStatus'])
            ->name('prescriptions.batch.update-status');
    });
});