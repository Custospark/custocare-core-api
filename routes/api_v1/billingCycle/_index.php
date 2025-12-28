<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\BillingCycleController;

Route::middleware(['auth:sanctum'])->group(function () {
    
    // Billing Cycles Routes
    Route::prefix('billing-cycles')->name('billing-cycles.')->group(function () {
        // Basic CRUD operations
        Route::get('/', [BillingCycleController::class, 'index'])->name('index');
        Route::post('/', [BillingCycleController::class, 'store'])->name('store');
        
        Route::prefix('{billingCycle}')->group(function () {
            Route::get('/', [BillingCycleController::class, 'show'])->name('show');
            Route::put('/', [BillingCycleController::class, 'update'])->name('update');
            Route::patch('/', [BillingCycleController::class, 'update'])->name('update.patch');
            Route::delete('/', [BillingCycleController::class, 'destroy'])->name('destroy');
            
            // Custom operations
            Route::patch('/status', [BillingCycleController::class, 'updateStatus'])->name('status.update');
            Route::post('/payments', [BillingCycleController::class, 'recordPayment'])->name('payments.record');
        });
        
        // Filtered routes
        Route::prefix('facility/{facilityId}')->group(function () {
            Route::get('/', [BillingCycleController::class, 'byFacility'])->name('by-facility');
            Route::get('/financial-summary', [BillingCycleController::class, 'financialSummary'])->name('financial-summary');
        });
        
        Route::get('/patient/{patientId}', [BillingCycleController::class, 'byPatient'])->name('by-patient');
        Route::get('/overdue', [BillingCycleController::class, 'overdue'])->name('overdue');
        Route::get('/disputed', [BillingCycleController::class, 'disputed'])->name('disputed');
    });
    
});