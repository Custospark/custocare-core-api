<?php
// routes/api.php

use App\Http\Controllers\Api\BillableItemsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {
    // Single endpoint to get all billable items and services
    Route::get('/billing/billable-items', [BillableItemsController::class, 'getBillableItemsAndServices']);
});