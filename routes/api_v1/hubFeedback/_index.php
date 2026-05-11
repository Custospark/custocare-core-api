<?php

declare(strict_types=1);

use App\Http\Controllers\Api\HubFeedbackRequestController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->prefix('hub-feedback')->group(function () {
    Route::get('mine', [HubFeedbackRequestController::class, 'mine']);
    Route::get('roadmap', [HubFeedbackRequestController::class, 'roadmap']);
    Route::post('/', [HubFeedbackRequestController::class, 'store']);
    Route::post('{uuid}/vote', [HubFeedbackRequestController::class, 'vote'])->whereUuid('uuid');
});
