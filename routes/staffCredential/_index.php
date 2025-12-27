<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\StaffCredentialController;

/*
|--------------------------------------------------------------------------
| API Routes — Staff Credentials
|--------------------------------------------------------------------------
| Handles creation, lifecycle management, verification, and reporting
| for staff credentials.
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum'])
    ->prefix('credentials')
    ->as('api.credentials.')
    ->group(function () {

        /*
        |------------------------------------------------------------------
        | Credential Resource Routes (CRUD)
        |------------------------------------------------------------------
        */
        Route::apiResource('/', StaffCredentialController::class)
            ->parameters(['' => 'credential'])
            ->except(['create', 'edit']);

        /*
        |------------------------------------------------------------------
        | Credential Actions (State Transitions)
        |------------------------------------------------------------------
        */
        Route::post('{credential}/verify', [StaffCredentialController::class, 'verify'])
            ->name('verify');

        Route::post('{credential}/supersede', [StaffCredentialController::class, 'supersede'])
            ->name('supersede');

        /*
        |------------------------------------------------------------------
        | Credential Queries / Reports
        |------------------------------------------------------------------
        */
        Route::get('expiring', [StaffCredentialController::class, 'expiring'])
            ->name('expiring');

        Route::get('expired', [StaffCredentialController::class, 'expired'])
            ->name('expired');

        Route::get('statistics', [StaffCredentialController::class, 'statistics'])
            ->name('statistics');

        Route::get('staff/{staff}', [StaffCredentialController::class, 'staffCredentials'])
            ->name('staff');

        /*
        |------------------------------------------------------------------
        | Credential Documents
        |------------------------------------------------------------------
        */
        Route::get('{credential}/document', [StaffCredentialController::class, 'downloadDocument'])
            ->name('document');
    });
