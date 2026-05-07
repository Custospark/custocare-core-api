<?php

declare(strict_types=1);

use App\Http\Controllers\Api\NursingDashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Nursing module (intelligence dashboard)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum'])
    ->prefix('nursing')
    ->name('nursing.dashboard.')
    ->group(function () {
        Route::get('/facility/{facilityId}/dashboard', [NursingDashboardController::class, 'show'])
            ->name('show');
    });
