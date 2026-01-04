<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserContextController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;


// --------------------------
// Authenticated routes (sanctum)
// --------------------------
Route::middleware(['auth:sanctum'])->group(function () {

    // Auth-related endpoints
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/user/context', [UserContextController::class, 'resolve']);


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

   

});
