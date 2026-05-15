<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AmbulanceDashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Ambulance module (fleet intelligence dashboard)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum'])
    ->prefix('ambulance')
    ->name('ambulance.')
    ->group(function () {
        Route::get('/facility/{facilityId}/dashboard', [AmbulanceDashboardController::class, 'show'])
            ->name('dashboard');
    });
