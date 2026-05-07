<?php

declare(strict_types=1);

use App\Http\Controllers\Api\NursingDashboardController;
use App\Http\Controllers\Api\NursingWardPatientController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Nursing module
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum'])
    ->prefix('nursing')
    ->name('nursing.')
    ->group(function () {
        Route::get('/facility/{facilityId}/dashboard', [NursingDashboardController::class, 'show'])
            ->name('dashboard');
        Route::get('/ward-patients', [NursingWardPatientController::class, 'index'])
            ->name('ward-patients');
    });
