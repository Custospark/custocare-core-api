<?php

use App\Http\Controllers\Api\BillingController;
use Illuminate\Support\Facades\Route;

// Billing routes
Route::prefix('billing')->middleware(['auth:sanctum'])->group(function () {
    // Finalize and persist billing data
    Route::post('/finalize', [BillingController::class, 'finalize']);
    
    // Retrieve billing data for a visit
    Route::get('/visit/{visitId}', [BillingController::class, 'getByVisit']);
});
