<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes (no authentication required)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Authenticated routes
Route::middleware(['auth:sanctum'])->group(function () {
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // User management routes
    Route::apiResource('users', UserController::class)->except(['store']);
    
    // Additional user routes
    Route::post('/users/{user}/verify-identity', [UserController::class, 'verifyIdentity'])
        ->middleware('can:verifyIdentity,App\Models\User');
    
    Route::post('/users/{user}/update-password', [UserController::class, 'updatePassword'])
        ->middleware('can:updatePassword,user');
    
    Route::post('/users/{user}/enable-mfa', [UserController::class, 'enableMfa'])
        ->middleware('can:manageMfa,user');
    
    Route::post('/users/{user}/disable-mfa', [UserController::class, 'disableMfa'])
        ->middleware('can:manageMfa,user');
    
    Route::post('/users/{user}/restore', [UserController::class, 'restore'])
        ->middleware('can:restore,user')
        ->withTrashed();
    
    // Admin only routes
    Route::post('/users', [UserController::class, 'store'])
        ->middleware('can:create,App\Models\User');
});