<?php
// routes/api/messages.php

use App\Http\Controllers\Api\MessageController;
use Illuminate\Support\Facades\Route;

/**
 * Message Module API Routes
 * 
 * Prefix: /api/messages
 * Middleware: auth:sanctum
 */
Route::middleware('auth:sanctum')->prefix('messages')->group(function () {
    
    // ── Listing & Stats ─────────────────────────────────
    Route::get('/',             [MessageController::class, 'index']);      // GET /api/messages
    Route::get('/stats',        [MessageController::class, 'stats']);      // GET /api/messages/stats
    
    // ── Create ──────────────────────────────────────────
    Route::post('/',            [MessageController::class, 'store']);      // POST /api/messages
    
    // ── Bulk Operations ─────────────────────────────────
    Route::post('/bulk',        [MessageController::class, 'bulk']);       // POST /api/messages/bulk
    Route::delete('/trash/empty', [MessageController::class, 'emptyTrash']); // DELETE /api/messages/trash/empty
    
    // ── Single Message Routes ───────────────────────────
    Route::get('/{id}',          [MessageController::class, 'show']);       // GET /api/messages/{id}
    Route::put('/{id}',          [MessageController::class, 'update']);     // PUT /api/messages/{id}
    Route::delete('/{id}',       [MessageController::class, 'destroy']);    // DELETE /api/messages/{id}
    
    // ── Message Actions ─────────────────────────────────
    Route::post('/{id}/send',    [MessageController::class, 'send']);       // POST /api/messages/{id}/send
    Route::post('/{id}/restore', [MessageController::class, 'restore']);    // POST /api/messages/{id}/restore
    Route::delete('/{id}/permanent', [MessageController::class, 'permanentDelete']); // DELETE /api/messages/{id}/permanent
    
    // ── State Changes ───────────────────────────────────
    Route::patch('/{id}/read',   [MessageController::class, 'markRead']);   // PATCH /api/messages/{id}/read
    Route::patch('/{id}/unread', [MessageController::class, 'markUnread']); // PATCH /api/messages/{id}/unread
    Route::patch('/{id}/star',   [MessageController::class, 'star']);       // PATCH /api/messages/{id}/star
    Route::patch('/{id}/archive', [MessageController::class, 'archive']);   // PATCH /api/messages/{id}/archive
    Route::patch('/{id}/unarchive', [MessageController::class, 'unarchive']); // PATCH /api/messages/{id}/unarchive
    
    // ── Labels ──────────────────────────────────────────
    Route::post('/{id}/labels',  [MessageController::class, 'addLabel']);   // POST /api/messages/{id}/labels
    Route::delete('/{id}/labels/{label}', [MessageController::class, 'removeLabel']); // DELETE /api/messages/{id}/labels/{label}
    
    // ── Attachments ─────────────────────────────────────
    Route::post('/{id}/attachments', [MessageController::class, 'uploadAttachment']); // POST /api/messages/{id}/attachments
    Route::delete('/attachments/{attachmentId}', [MessageController::class, 'removeAttachment']); // DELETE /api/messages/attachments/{attachmentId}
});