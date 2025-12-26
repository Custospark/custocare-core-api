<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\PatientController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| API routes for the application. Routes are grouped for public access
| and authenticated access with proper middleware and permissions.
|
*/

// ----------------------
// Public routes (no auth)
// ----------------------
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// --------------------------
// Authenticated routes (sanctum)
// --------------------------
Route::middleware(['auth:sanctum'])->group(function () {

    // Auth-related endpoints
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // ----------------------
    // User management routes
    // ----------------------
    Route::apiResource('users', UserController::class)->except(['store']);

    // Additional user operations with authorization
    Route::prefix('users/{user}')->group(function () {
        Route::post('/verify-identity', [UserController::class, 'verifyIdentity'])
            ->middleware('can:verifyIdentity,App\Models\User');

        Route::post('/update-password', [UserController::class, 'updatePassword'])
            ->middleware('can:updatePassword,user');

        Route::post('/enable-mfa', [UserController::class, 'enableMfa'])
            ->middleware('can:manageMfa,user');

        Route::post('/disable-mfa', [UserController::class, 'disableMfa'])
            ->middleware('can:manageMfa,user');

        Route::post('/restore', [UserController::class, 'restore'])
            ->middleware('can:restore,user')
            ->withTrashed();
    });

    // Admin-only user creation
    Route::post('/users', [UserController::class, 'store'])
        ->middleware('can:create,App\Models\User');

    // ----------------------
    // Patient routes
    // ----------------------
    Route::prefix('patients')->name('patients.')->group(function () {

        // General patient operations
        Route::get('/', [PatientController::class, 'index'])->name('index');
        Route::post('/', [PatientController::class, 'store'])->name('store');
        Route::get('/search', [PatientController::class, 'search'])->name('search');
        Route::get('/statistics', [PatientController::class, 'statistics'])->name('statistics');
        Route::get('/blood-type/{bloodType}', [PatientController::class, 'byBloodType'])->name('by-blood-type');
        Route::get('/requiring-isolation', [PatientController::class, 'requiringIsolation'])->name('requiring-isolation');

        // Individual patient operations
        Route::prefix('{patient}')->group(function () {
            Route::get('/', [PatientController::class, 'show'])->name('show');
            Route::put('/', [PatientController::class, 'update'])->name('update');
            Route::patch('/', [PatientController::class, 'update']); // partial updates
            Route::delete('/', [PatientController::class, 'destroy'])->name('destroy');

            // Special patient operations
            Route::post('/restore', [PatientController::class, 'restore'])->name('restore');
            Route::post('/status', [PatientController::class, 'updateStatus'])->name('update-status');
            Route::get('/export', [PatientController::class, 'export'])->name('export');
        });
    });

    // ----------------------
    // Alternative resource route for patients
    // ----------------------
    Route::apiResource('patients', PatientController::class)->except(['update']);
    Route::post('patients/{patient}', [PatientController::class, 'update']); // For partial updates

});
