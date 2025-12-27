<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\StaffController;

// Staff Routes
Route::prefix('staff')->middleware(['auth:sanctum','auth:api'])->group(function () {
    // Basic CRUD operations
    Route::get('/', [StaffController::class, 'index']);
    Route::post('/', [StaffController::class, 'store']);
    Route::get('/{staff}', [StaffController::class, 'show']);
    Route::put('/{staff}', [StaffController::class, 'update']);
    Route::delete('/{staff}', [StaffController::class, 'destroy']);
    
    // License operations
    Route::patch('/{staff}/license', [StaffController::class, 'updateLicense']);
    
    // Employment status operations
    Route::patch('/{staff}/status', [StaffController::class, 'updateStatus']);
    
    // Credential operations
    Route::get('/expiring-credentials', [StaffController::class, 'expiringCredentials']);
    
    // Validation operations
    Route::post('/{staff}/validate-action', [StaffController::class, 'validateAction']);
    
    // Additional endpoints can be added here
    Route::get('/{staff}/subordinates', function ($staffId) {
        // Implementation for getting subordinates
    });
    
    Route::get('/{staff}/supervisor-chain', function ($staffId) {
        // Implementation for getting supervisor hierarchy
    });
});