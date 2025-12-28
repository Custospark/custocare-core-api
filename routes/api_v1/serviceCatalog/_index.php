<?php

use App\Http\Controllers\Api\ServiceCatalogController;
use Illuminate\Support\Facades\Route;

// Service Catalog Routes
Route::prefix('service-catalogs')->name('api.service-catalogs.')->group(function () {
    // Public routes (if any)
    
    // Protected routes (with auth middleware)
    Route::middleware(['auth:sunctum'])->group(function () {
        // Standard RESTful routes
        Route::get('/', [ServiceCatalogController::class, 'index'])->name('index');
        Route::post('/', [ServiceCatalogController::class, 'store'])->name('store');
        
        // Get by service code
        Route::get('/code/{serviceCode}', [ServiceCatalogController::class, 'showByCode'])->name('show-by-code');
        
        // Search routes
        Route::get('/search', [ServiceCatalogController::class, 'search'])->name('search');
        
        // Filtered routes
        Route::get('/effective/{date}', [ServiceCatalogController::class, 'effectiveServices'])->name('effective');
        Route::get('/code-system/{codeSystem}', [ServiceCatalogController::class, 'byCodeSystem'])->name('by-code-system');
        Route::get('/category/{category}', [ServiceCatalogController::class, 'byCategory'])->name('by-category');
        
        // Individual service routes
        Route::prefix('{serviceCatalog}')->group(function () {
            Route::get('/', [ServiceCatalogController::class, 'show'])->name('show');
            Route::put('/', [ServiceCatalogController::class, 'update'])->name('update');
            Route::patch('/', [ServiceCatalogController::class, 'update'])->name('update');
            Route::delete('/', [ServiceCatalogController::class, 'destroy'])->name('destroy');
            
            // Additional actions
            Route::post('/restore', [ServiceCatalogController::class, 'restore'])->name('restore');
            Route::get('/check-effectiveness', [ServiceCatalogController::class, 'checkEffectiveness'])->name('check-effectiveness');
        });
    });
});