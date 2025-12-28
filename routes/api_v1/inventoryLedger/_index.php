<?php

use App\Http\Controllers\Api\InventoryLedgerController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Inventory Ledger API Routes
|--------------------------------------------------------------------------
|
| These routes are for the inventory ledger module, providing double-entry
| accounting for all inventory transactions.
|
*/

Route::middleware(['auth:sanctum', 'api'])->prefix('inventory')->name('inventory-ledger.')->group(function () {
    
    // Standard RESTful routes
    Route::get('/ledger', [InventoryLedgerController::class, 'index'])->name('index');
    Route::post('/ledger', [InventoryLedgerController::class, 'store'])->name('store');
    Route::get('/ledger/{inventory_ledger}', [InventoryLedgerController::class, 'show'])->name('show');
    Route::put('/ledger/{inventory_ledger}', [InventoryLedgerController::class, 'update'])->name('update');
    Route::delete('/ledger/{inventory_ledger}', [InventoryLedgerController::class, 'destroy'])->name('destroy');
    
    // Specialized endpoints
    Route::post('/ledger/{inventory_ledger}/verify', [InventoryLedgerController::class, 'verify'])->name('verify');
    Route::get('/ledger/balance/current', [InventoryLedgerController::class, 'currentBalance'])->name('current-balance');
    
    // Transaction-specific endpoints
    Route::post('/ledger/purchase', [InventoryLedgerController::class, 'recordPurchase'])->name('record-purchase');
    
    // Convenience endpoints (to be implemented as needed)
    Route::post('/ledger/consumption', [InventoryLedgerController::class, 'recordConsumption'])->name('record-consumption');
    Route::post('/ledger/adjustment', [InventoryLedgerController::class, 'recordAdjustment'])->name('record-adjustment');
    Route::post('/ledger/transfer', [InventoryLedgerController::class, 'recordTransfer'])->name('record-transfer');
    
    // Reporting endpoints
    Route::get('/ledger/report/transactions', [InventoryLedgerController::class, 'transactionReport'])->name('transaction-report');
    Route::get('/ledger/report/expiry', [InventoryLedgerController::class, 'expiryReport'])->name('expiry-report');
    
    // Batch operations
    Route::post('/ledger/batch', [InventoryLedgerController::class, 'batchCreate'])->name('batch-create');
    
    // Audit and verification
    Route::get('/ledger/audit/trail', [InventoryLedgerController::class, 'auditTrail'])->name('audit-trail');
    Route::get('/ledger/verification/pending', [InventoryLedgerController::class, 'pendingVerification'])->name('pending-verification');
});

// Public endpoints (if any)
Route::middleware(['api'])->prefix('v1/inventory')->group(function () {
    // Health check for inventory ledger
    Route::get('/ledger/health', function () {
        return response()->json([
            'status' => 'healthy',
            'timestamp' => now()->toIso8601String(),
            'version' => '1.0.0',
        ]);
    });
});