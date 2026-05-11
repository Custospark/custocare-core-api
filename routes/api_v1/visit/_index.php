<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\VisitController;
// Visit Routes
Route::prefix('visits')->middleware(['auth:sanctum'])->group(function () {
    // Basic CRUD operations
    Route::get('/', [VisitController::class, 'index']);
    Route::post('/', [VisitController::class, 'store']);
    Route::get('/{visit}', [VisitController::class, 'show'])->where('visit', '[a-f0-9-]{36}');
    Route::put('/{visit}', [VisitController::class, 'update'])->where('visit', '[a-f0-9-]{36}');
    Route::delete('/{visit}', [VisitController::class, 'destroy'])->where('visit', '[a-f0-9-]{36}');
    
    //Handling patient forwarding or assigning staff to visit.
    Route::post('/assign-staff',[VisitController::class, 'assignStaffToVisit']);
    Route::get('/staff/forwarding', [VisitController::class, 'getStaffForPatientForwarding']);
    
    // Restore soft-deleted visit
    Route::post('/{visit}/restore', [VisitController::class, 'restore'])->where('visit', '[a-f0-9-]{36}');
    Route::get('/my-queue', [VisitController::class, 'myQueue']);
    Route::get('/my-completed-work', [VisitController::class, 'myCompletedWork']);

    
    // Specialized operations
    Route::post('/{visit}/phase', [VisitController::class, 'updatePhase'])->where('visit', '[a-f0-9-]{36}');
    Route::post('/{visit}/status', [VisitController::class, 'updateStatus'])->where('visit', '[a-f0-9-]{36}');
    Route::post('/{visit}/discharge', [VisitController::class, 'discharge'])->where('visit', '[a-f0-9-]{36}');
    Route::post('/{visit}/clinical-care/start', [VisitController::class, 'startClinicalCare'])->where('visit', '[a-f0-9-]{36}');
    Route::post('/{visit}/clinical-care/end', [VisitController::class, 'endClinicalCare'])->where('visit', '[a-f0-9-]{36}');
    Route::post('/{visit}/cancel', [VisitController::class, 'cancel'])->where('visit', '[a-f0-9-]{36}');
    Route::post('/{visit}/register', [VisitController::class, 'register'])->where('visit', '[a-f0-9-]{36}');
    Route::get('/{visit}/ward-bed-options', [VisitController::class, 'wardBedOptions'])->where('visit', '[a-f0-9-]{36}');
    Route::post('/{visit}/ward-bed-assignment', [VisitController::class, 'assignWardBed'])->where('visit', '[a-f0-9-]{36}');
    Route::post('/{visit}/ward-bed-release', [VisitController::class, 'releaseWardBed'])->where('visit', '[a-f0-9-]{36}');
    
    // Filtered routes
    Route::get('/facility/{facilityId}', [VisitController::class, 'byFacility'])->where('facilityId', '[0-9]+');
    Route::get('/patient/{patientId}', [VisitController::class, 'byPatient'])->where('patientId', '[0-9]+');
    
    // Reports and analytics
    Route::get('/reports/long-waiting', [VisitController::class, 'longWaiting']);
    Route::get('/reports/statistics', [VisitController::class, 'statistics']);
});