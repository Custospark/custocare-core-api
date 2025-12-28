<?php

use App\Http\Controllers\Api\DepartmentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Department API Routes
|--------------------------------------------------------------------------
|
| These routes are for managing departments in the healthcare facility system.
| All routes are protected by authentication and authorization middleware.
|
*/

Route::prefix('departments')->middleware(['auth:sanctum'])->name('departments.')->group(function () {
    // Public routes (if any) - typically only index/show might be public
    Route::get('/', [DepartmentController::class, 'index'])->name('index');
    Route::get('/{uuid}', [DepartmentController::class, 'show'])->name('show');
    
    // Filtered routes
    Route::get('/facility/{facilityId}', [DepartmentController::class, 'byFacility'])->name('by-facility');
    Route::get('/type/{type}', [DepartmentController::class, 'byType'])->name('by-type');

    // Protected routes (require authentication)
    Route::middleware('auth:api')->group(function () {
        // CRUD operations
        Route::post('/', [DepartmentController::class, 'store'])->name('store');
        Route::put('/{uuid}', [DepartmentController::class, 'update'])->name('update');
        Route::patch('/{uuid}', [DepartmentController::class, 'update'])->name('update.patch');
        Route::delete('/{uuid}', [DepartmentController::class, 'destroy'])->name('destroy');
        
        // Additional operations
        Route::post('/{uuid}/restore', [DepartmentController::class, 'restore'])->name('restore');
        
        // Custom actions (add more as needed)
        // Route::post('/{uuid}/assign-head', [DepartmentController::class, 'assignHead'])->name('assign-head');
        // Route::post('/{uuid}/update-capacity', [DepartmentController::class, 'updateCapacity'])->name('update-capacity');
    });
});