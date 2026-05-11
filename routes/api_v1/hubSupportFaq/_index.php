<?php

declare(strict_types=1);

use App\Http\Controllers\Api\HubSupportFaqController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->prefix('hub-support')->group(function () {
    Route::get('faqs', [HubSupportFaqController::class, 'index']);
});
