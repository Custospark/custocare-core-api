<?php

declare(strict_types=1);

use App\Http\Controllers\Api\UserMessageContactController;
use Illuminate\Support\Facades\Route;

/**
 * Personal message contact notebook (owner-scoped).
 * Prefix: /api/message-contacts
 */
Route::middleware('auth:sanctum')->prefix('message-contacts')->group(function (): void {
    Route::get('/', [UserMessageContactController::class, 'index']);
    Route::post('/resolve', [UserMessageContactController::class, 'resolve']);
    Route::post('/', [UserMessageContactController::class, 'store']);
    Route::get('/{id}', [UserMessageContactController::class, 'show']);
    Route::put('/{id}', [UserMessageContactController::class, 'update']);
    Route::delete('/{id}', [UserMessageContactController::class, 'destroy']);
    Route::post('/{id}/touch', [UserMessageContactController::class, 'touch']);
});
