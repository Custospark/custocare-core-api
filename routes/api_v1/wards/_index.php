<?php
use App\Http\Controllers\Api\WardController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/wards', [WardController::class, 'index']);
    Route::post('/wards', [WardController::class, 'store']);
    Route::get('/wards/{ward}', [WardController::class, 'show']);
    Route::patch('/wards/{ward}', [WardController::class, 'update']);
    Route::delete('/wards/{ward}', [WardController::class, 'destroy']);
    Route::get('/wards/{ward}/beds', [WardController::class, 'beds']);
    Route::post('/wards/{ward}/beds', [WardController::class, 'storeBed']);
    Route::patch('/ward-beds/{bed}', [WardController::class, 'updateBed']);
});
