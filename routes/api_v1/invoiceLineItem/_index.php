<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\InvoiceLineItemController;

/*
|--------------------------------------------------------------------------
| Invoice Line Items Routes
|--------------------------------------------------------------------------
|
| These routes handle all operations related to invoice line items
| including CRUD operations and specialized business operations.
|
*/

Route::prefix('invoice-line-items')->name('invoice-line-items.')->middleware(['auth:sanctum', 'verified'])->group(function () {
    
    // Basic CRUD operations
    Route::get('/', [InvoiceLineItemController::class, 'index'])->name('index');
    Route::post('/', [InvoiceLineItemController::class, 'store'])->name('store');
    
    Route::prefix('{invoice_line_item}')->group(function () {
        Route::get('/', [InvoiceLineItemController::class, 'show'])->name('show');
        Route::put('/', [InvoiceLineItemController::class, 'update'])->name('update');
        Route::delete('/', [InvoiceLineItemController::class, 'destroy'])->name('destroy');
        
        // Specialized operations
        Route::patch('/status', [InvoiceLineItemController::class, 'updateStatus'])->name('update-status');
        Route::post('/review', [InvoiceLineItemController::class, 'markAsReviewed'])->name('mark-reviewed');
        Route::get('/audit-trail', [InvoiceLineItemController::class, 'verifyAuditTrail'])->name('audit-trail');
        Route::get('/validate-billing', [InvoiceLineItemController::class, 'validateForBilling'])->name('validate-billing');
    });
    
    // Find by UUID
    Route::get('/uuid/{uuid}', [InvoiceLineItemController::class, 'showByUuid'])->name('show-by-uuid');
    
    // Filtered listings
    Route::get('/billing-cycle/{billingCycleId}', [InvoiceLineItemController::class, 'byBillingCycle'])->name('by-billing-cycle');
    Route::get('/status/{status}', [InvoiceLineItemController::class, 'byStatus'])->name('by-status');
    Route::get('/requiring-review', [InvoiceLineItemController::class, 'requiringReview'])->name('requiring-review');
    Route::get('/date-range', [InvoiceLineItemController::class, 'byDateRange'])->name('by-date-range');
    
    // Bulk operations
    Route::post('/batch-status', [InvoiceLineItemController::class, 'batchUpdateStatus'])->name('batch-status');
    
    // Reporting and calculations
    Route::get('/billing-cycle/{billingCycleId}/totals', [InvoiceLineItemController::class, 'billingCycleTotals'])->name('billing-cycle-totals');
});

// Optional: Include these in a billing-specific route group if you have billing module
Route::prefix('billing')->name('billing.')->middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::prefix('invoice-line-items')->name('invoice-line-items.')->group(function () {
        // Business-specific billing operations can be added here
        Route::post('/batch-approve', [InvoiceLineItemController::class, 'batchUpdateStatus'])
            ->name('batch-approve')
            ->middleware('can:batchApprove,' . \App\Models\InvoiceLineItem::class);
    });
});