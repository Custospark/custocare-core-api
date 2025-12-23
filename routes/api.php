<?php

use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// User routes
Route::prefix('users')->middleware(['auth:api', 'throttle:api'])->group(function () {
    // RESTful resource routes
    Route::get('/', [UserController::class, 'index'])
        ->name('api.users.index');
    
    Route::post('/', [UserController::class, 'store'])
        ->name('api.users.store');
    
    Route::get('/{uuid}', [UserController::class, 'show'])
        ->name('api.users.show')
        ->where('uuid', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}');
    
    Route::put('/{id}', [UserController::class, 'update'])
        ->name('api.users.update')
        ->where('id', '[0-9]+');
    
    Route::delete('/{id}', [UserController::class, 'destroy'])
        ->name('api.users.destroy')
        ->where('id', '[0-9]+');
    
    // Custom action routes
    Route::post('/{id}/verify', [UserController::class, 'verify'])
        ->name('api.users.verify')
        ->where('id', '[0-9]+');
    
    Route::post('/{id}/suspend', [UserController::class, 'suspend'])
        ->name('api.users.suspend')
        ->where('id', '[0-9]+');
    
    Route::post('/{id}/restore', [UserController::class, 'restoreSuspended'])
        ->name('api.users.restore')
        ->where('id', '[0-9]+');
    
    Route::post('/{id}/archive', [UserController::class, 'archive'])
        ->name('api.users.archive')
        ->where('id', '[0-9]+');
    
    // Special collection routes
    Route::get('/pending-verification', [UserController::class, 'pendingVerification'])
        ->name('api.users.pending-verification');
    
    Route::get('/region/{region}', [UserController::class, 'byRegion'])
        ->name('api.users.by-region')
        ->where('region', '[A-Z]{2,10}');
});

// Alternative: Use resource routing with additional routes
// Route::apiResource('users', UserController::class)
//     ->except(['show'])
//     ->middleware(['auth:api', 'throttle:api']);
// 
// Route::get('users/{user:global_user_uuid}', [UserController::class, 'show'])
//     ->name('users.show')
//     ->middleware(['auth:api', 'throttle:api']);