<?php
use App\Http\Controllers\Api\AmbulanceTripController;
use App\Http\Controllers\Api\AmbulanceTripLogController;
use Illuminate\Support\Facades\Route;

Route::prefix('ambulance-trips')
    ->name('ambulance-trips.')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        Route::get('/', [AmbulanceTripController::class, 'index'])->name('index');
        Route::post('/', [AmbulanceTripController::class, 'store'])->name('store');

        Route::get('/active', [AmbulanceTripController::class, 'active'])->name('active');
        Route::get('/patient/{patientId}', [AmbulanceTripController::class, 'byPatient'])->name('by-patient');
        Route::get('/facility/{facilityId}', [AmbulanceTripController::class, 'byFacility'])->name('by-facility');
        Route::get('/from-facility/{facilityId}', [AmbulanceTripController::class, 'fromFacility'])->name('from-facility');
        Route::get('/to-facility/{facilityId}', [AmbulanceTripController::class, 'toFacility'])->name('to-facility');

        Route::prefix('{trip}')->group(function () {
            Route::get('/', [AmbulanceTripController::class, 'show'])->name('show');
            Route::put('/', [AmbulanceTripController::class, 'update'])->name('update');
            Route::patch('/', [AmbulanceTripController::class, 'update'])->name('patch');
            Route::delete('/', [AmbulanceTripController::class, 'destroy'])->name('destroy');

            // Status transitions
            Route::post('/dispatch', [AmbulanceTripController::class, 'dispatch'])->name('dispatch');
            Route::post('/en-route', [AmbulanceTripController::class, 'enRoute'])->name('en-route');
            Route::post('/on-scene', [AmbulanceTripController::class, 'onScene'])->name('on-scene');
            Route::post('/patient-contact', [AmbulanceTripController::class, 'patientContact'])->name('patient-contact');
            Route::post('/depart-scene', [AmbulanceTripController::class, 'departScene'])->name('depart-scene');
            Route::post('/at-destination', [AmbulanceTripController::class, 'atDestination'])->name('at-destination');
            Route::post('/complete', [AmbulanceTripController::class, 'complete'])->name('complete');
            Route::post('/cancel', [AmbulanceTripController::class, 'cancel'])->name('cancel');

            // Trip logs
            Route::get('/logs', [AmbulanceTripLogController::class, 'index'])->name('logs.index');
            Route::post('/logs', [AmbulanceTripLogController::class, 'store'])->name('logs.store');
            Route::delete('/logs/{logId}', [AmbulanceTripLogController::class, 'destroy'])->name('logs.destroy');
        });
    });
