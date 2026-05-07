<?php

use App\Http\Controllers\Api\FacilityTaskController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/facility-tasks/my', [FacilityTaskController::class, 'myTasks']);
    Route::get('/facility-tasks/history', [FacilityTaskController::class, 'history']);
    Route::get('/facility-tasks', [FacilityTaskController::class, 'index']);
    Route::post('/facility-tasks', [FacilityTaskController::class, 'store']);
    Route::get('/facility-tasks/{task}', [FacilityTaskController::class, 'show']);
    Route::patch('/facility-tasks/{task}', [FacilityTaskController::class, 'update']);
});
