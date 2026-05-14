<?php
use App\Http\Controllers\Api\AmbulanceCrewMemberController;
use Illuminate\Support\Facades\Route;

Route::prefix('ambulance-crew')
    ->name('ambulance-crew.')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        Route::post('/', [AmbulanceCrewMemberController::class, 'store'])->name('store');
        Route::get('/ambulance/{ambulanceId}', [AmbulanceCrewMemberController::class, 'byAmbulance'])->name('by-ambulance');
        Route::get('/staff/{staffId}', [AmbulanceCrewMemberController::class, 'byStaff'])->name('by-staff');

        Route::prefix('{crewMember}')->group(function () {
            Route::put('/', [AmbulanceCrewMemberController::class, 'update'])->name('update');
            Route::patch('/', [AmbulanceCrewMemberController::class, 'update'])->name('patch');
            Route::delete('/', [AmbulanceCrewMemberController::class, 'destroy'])->name('destroy');
        });
    });
