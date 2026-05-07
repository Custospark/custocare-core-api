<?php

use App\Http\Controllers\Api\FacilityShiftHandoverController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/facility-shift-handovers', [FacilityShiftHandoverController::class, 'index']);
    Route::post('/facility-shift-handovers', [FacilityShiftHandoverController::class, 'store']);
    Route::get('/facility-shift-handovers/{handover}', [FacilityShiftHandoverController::class, 'show']);
    Route::patch('/facility-shift-handovers/{handover}', [FacilityShiftHandoverController::class, 'update']);
});
