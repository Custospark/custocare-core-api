<?php

declare(strict_types=1);

use App\Http\Controllers\Api\HubCommunityController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->prefix('hub-community')->group(function () {
    Route::get('posts', [HubCommunityController::class, 'index']);
    Route::post('posts', [HubCommunityController::class, 'store']);
    Route::get('posts/{uuid}', [HubCommunityController::class, 'show'])->whereUuid('uuid');
    Route::post('posts/{uuid}/comments', [HubCommunityController::class, 'storeComment'])->whereUuid('uuid');
});
