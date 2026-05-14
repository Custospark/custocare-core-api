<?php
use App\Http\Controllers\Api\AmbulanceController;
use Illuminate\Support\Facades\Route;

Route::prefix('ambulances')
    ->name('ambulances.')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        Route::get('/', [AmbulanceController::class, 'index'])->name('index');
        Route::post('/', [AmbulanceController::class, 'store'])->name('store');
        Route::get('/available', [AmbulanceController::class, 'available'])->name('available');
        Route::get('/facility/{facilityId}', [AmbulanceController::class, 'byFacility'])->name('by-facility');

        Route::prefix('{ambulance}')->group(function () {
            Route::get('/', [AmbulanceController::class, 'show'])->name('show');
            Route::put('/', [AmbulanceController::class, 'update'])->name('update');
            Route::patch('/', [AmbulanceController::class, 'update'])->name('patch');
            Route::delete('/', [AmbulanceController::class, 'destroy'])->name('destroy');
        });
    });
