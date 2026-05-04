<?php

declare(strict_types=1);

use App\Http\Controllers\Api\PharmacyDashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Pharmacy module (dispensary intelligence, etc.)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum'])
    ->prefix('pharmacy')
    ->name('pharmacy.')
    ->group(function () {
        Route::get('/facility/{facilityId}/dashboard', [PharmacyDashboardController::class, 'show'])
            ->name('dashboard');
    });
