<?php

use App\Http\Controllers\Api\InventoryItemController;
use App\Http\Controllers\Api\InventoryItemImportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'api'])->group(function () {
    
    // Inventory Items Routes
    Route::prefix('inventory-items')->group(function () {
        // CRUD Operations
        Route::get('/', [InventoryItemController::class, 'index'])
            ->name('api.inventory-items.index');
        
        Route::post('/', [InventoryItemController::class, 'store'])
            ->name('api.inventory-items.store');
        
        Route::get('{inventory_item:item_uuid}', [InventoryItemController::class, 'show'])
            ->name('api.inventory-items.show')
            ->withTrashed();
        
        Route::put('{inventory_item:item_uuid}', [InventoryItemController::class, 'update'])
            ->name('api.inventory-items.update');
        
        Route::delete('{inventory_item:item_uuid}', [InventoryItemController::class, 'destroy'])
            ->name('api.inventory-items.destroy');
        
        // Additional Operations
        Route::post('{inventory_item:item_uuid}/restore', [InventoryItemController::class, 'restore'])
            ->name('api.inventory-items.restore');
        
        Route::get('category/{category}', [InventoryItemController::class, 'byCategory'])
            ->name('api.inventory-items.by-category');
        
        Route::get('controlled-substances', [InventoryItemController::class, 'controlledSubstances'])
            ->name('api.inventory-items.controlled-substances');
        
        Route::get('special-handling', [InventoryItemController::class, 'specialHandling'])
            ->name('api.inventory-items.special-handling');
        
        Route::get('search', [InventoryItemController::class, 'search'])
            ->name('api.inventory-items.search');
        
        Route::get('code/{item_code}', [InventoryItemController::class, 'showByCode'])
            ->name('api.inventory-items.by-code');

        // Import endpoints
        Route::get('import-template', [InventoryItemImportController::class, 'downloadTemplate'])
            ->name('api.inventory-items.import-template');
        
        Route::post('import', [InventoryItemImportController::class, 'import'])
            ->name('api.inventory-items.import');
    });
    
});