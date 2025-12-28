<?php

use App\Http\Controllers\Api\ServiceVersionController;
use Illuminate\Support\Facades\Route;

// Service Versions API Routes
Route::prefix('service-versions')->name('service-versions.')->group(function () {
    // Public routes (read-only)
    Route::get('/', [ServiceVersionController::class, 'index'])->name('index');
    Route::get('/{uuid}', [ServiceVersionController::class, 'show'])->name('show');
    
    // Additional public endpoints
    Route::get('/service-catalog/{serviceCatalogId}/current', [ServiceVersionController::class, 'getCurrentVersion'])
        ->name('current');
    Route::get('/valid-on/{date}', [ServiceVersionController::class, 'getValidOnDate'])
        ->name('valid-on-date');
    Route::get('/service-catalog/{serviceCatalogId}/history', [ServiceVersionController::class, 'getVersionHistory'])
        ->name('history');
    
    // Protected routes (require authentication)
    Route::middleware('auth:sunctum')->group(function () {
        // CRUD operations
        Route::post('/', [ServiceVersionController::class, 'store'])->name('store');
        Route::put('/{uuid}', [ServiceVersionController::class, 'update'])->name('update');
        Route::delete('/{uuid}', [ServiceVersionController::class, 'destroy'])->name('destroy');
        
        // Additional protected endpoints
        Route::post('/{uuid}/set-current', [ServiceVersionController::class, 'setAsCurrentVersion'])
            ->name('set-current');
        Route::get('/{uuid}/price-calculation', [ServiceVersionController::class, 'getPriceCalculation'])
            ->name('price-calculation');
        Route::post('/{uuid}/check-billability', [ServiceVersionController::class, 'checkBillability'])
            ->name('check-billability');
        Route::post('/{uuid}/calculate-insurance-coverage', [ServiceVersionController::class, 'calculateInsuranceCoverage'])
            ->name('calculate-insurance-coverage');
    });
});