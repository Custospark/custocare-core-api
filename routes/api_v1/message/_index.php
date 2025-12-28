<?php

use App\Http\Controllers\Api\MessageController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {
    // Message routes
    Route::prefix('messages')->name('messages.')->group(function () {
        // Basic CRUD operations
        Route::get('/', [MessageController::class, 'index'])->name('index');
        Route::post('/', [MessageController::class, 'store'])->name('store');
        Route::get('/{id}', [MessageController::class, 'show'])->name('show');
        Route::put('/{id}', [MessageController::class, 'update'])->name('update');
        Route::delete('/{id}', [MessageController::class, 'destroy'])->name('destroy');
        
        // Additional operations
        Route::post('/{id}/restore', [MessageController::class, 'restore'])->name('restore');
        Route::post('/{id}/mark-delivered', [MessageController::class, 'markAsDelivered'])->name('mark.delivered');
        Route::post('/{id}/mark-sent', [MessageController::class, 'markAsSent'])->name('mark.sent');
        Route::post('/{id}/acknowledge', [MessageController::class, 'acknowledge'])->name('acknowledge');
        
        // Special routes
        Route::get('/uuid/{uuid}', [MessageController::class, 'showByUuid'])->name('show.by.uuid');
        Route::get('/clinical', [MessageController::class, 'clinicalMessages'])->name('clinical');
    });

    // Conversation-specific message routes
    Route::prefix('conversations/{conversationId}/messages')->name('conversations.messages.')->group(function () {
        Route::get('/', [MessageController::class, 'conversationMessages'])->name('index');
    });
});