<?php

use App\Http\Controllers\Api\WalkInSessionController;
use Illuminate\Support\Facades\Route;

Route::post('/facilities/{facilityId}/walkin/session', [WalkInSessionController::class, 'createSession']);

// Upgrade at checkout
Route::post('/pharmacy/checkout/{billingCycleId}/upgrade-walkin', [WalkInSessionController::class, 'upgrade']);
