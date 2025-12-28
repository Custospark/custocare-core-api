<?php

use App\Http\Controllers\Api\MessageAttachmentController;
use Illuminate\Support\Facades\Route;

// Message Attachment Routes
Route::prefix('message-attachments')->name('api.message-attachments.')->group(function () {
    // Public routes (require authentication)
    Route::middleware(['auth:sanctum'])->group(function () {
        // CRUD operations
        Route::get('/', [MessageAttachmentController::class, 'index'])->name('index');
        Route::post('/', [MessageAttachmentController::class, 'store'])->name('store');
        Route::get('/{message_attachment}', [MessageAttachmentController::class, 'show'])->name('show');
        Route::put('/{message_attachment}', [MessageAttachmentController::class, 'update'])->name('update');
        Route::delete('/{message_attachment}', [MessageAttachmentController::class, 'destroy'])->name('destroy');
        
        // Additional routes
        Route::get('/uuid/{uuid}', [MessageAttachmentController::class, 'showByUuid'])->name('show-by-uuid');
        Route::get('/message/{messageId}', [MessageAttachmentController::class, 'byMessage'])->name('by-message');
        Route::get('/type/{type}', [MessageAttachmentController::class, 'byType'])->name('by-type');
        Route::post('/upload', [MessageAttachmentController::class, 'upload'])->name('upload');
        Route::get('/statistics', [MessageAttachmentController::class, 'statistics'])->name('statistics');
        
        // File download route (to be implemented in controller)
        Route::get('/{message_attachment}/download', [MessageAttachmentController::class, 'download'])
            ->name('download')
            ->middleware('can:download,message_attachment');
    });
});

// Optional: Nested route under messages
Route::prefix('messages/{message}/attachments')->name('api.messages.attachments.')->group(function () {
    Route::middleware(['auth:api'])->group(function () {
        Route::get('/', [MessageAttachmentController::class, 'byMessage'])->name('index');
        Route::post('/', [MessageAttachmentController::class, 'store'])->name('store');
        Route::post('/upload', [MessageAttachmentController::class, 'upload'])->name('upload');
    });
});