<?php

use App\Http\Controllers\Api\LearningMaterialController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->prefix('learning-materials')->group(function () {
    Route::get('/', [LearningMaterialController::class, 'index']);
    Route::get('{uuid}', [LearningMaterialController::class, 'show'])->whereUuid('uuid');
});
