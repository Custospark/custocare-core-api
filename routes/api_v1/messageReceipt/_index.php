<?php

use App\Http\Controllers\Api\MessageReceiptController;
use Illuminate\Support\Facades\Route;

// Message Receipts API Routes
Route::prefix('message-receipts')->name('message-receipts.')->middleware('auth:sanctum')->group(function () {
    // Standard RESTful routes
    Route::get('/', [MessageReceiptController::class, 'index'])
        ->name('index')
        ->middleware('auth:api', 'can:viewAny,App\Models\MessageReceipt');
    
    Route::post('/', [MessageReceiptController::class, 'store'])
        ->name('store')
        ->middleware('auth:api', 'can:create,App\Models\MessageReceipt');
    
    Route::get('/{message_receipt}', [MessageReceiptController::class, 'show'])
        ->name('show')
        ->middleware('auth:api', 'can:view,message_receipt');
    
    Route::put('/{message_receipt}', [MessageReceiptController::class, 'update'])
        ->name('update')
        ->middleware('auth:api', 'can:update,message_receipt');
    
    Route::delete('/{message_receipt}', [MessageReceiptController::class, 'destroy'])
        ->name('destroy')
        ->middleware('auth:api', 'can:delete,message_receipt');
    
    // Custom routes for specific actions
    Route::post('/{message_receipt}/mark-as-delivered', [MessageReceiptController::class, 'markAsDelivered'])
        ->name('mark-as-delivered')
        ->middleware('auth:api', 'can:markAsDelivered,message_receipt');
    
    Route::post('/{message_receipt}/mark-as-read', [MessageReceiptController::class, 'markAsRead'])
        ->name('mark-as-read')
        ->middleware('auth:api', 'can:markAsRead,message_receipt');
    
    Route::post('/{message_receipt}/mark-as-acknowledged', [MessageReceiptController::class, 'markAsAcknowledged'])
        ->name('mark-as-acknowledged')
        ->middleware('auth:api', 'can:markAsAcknowledged,message_receipt');
    
    // Bulk operations
    Route::post('/bulk/update-status', [MessageReceiptController::class, 'bulkUpdateStatus'])
        ->name('bulk.update-status')
        ->middleware('auth:api', 'can:update,App\Models\MessageReceipt');
    
    // Filter routes
    Route::get('/message/{messageId}', [MessageReceiptController::class, 'getByMessage'])
        ->name('by-message')
        ->middleware('auth:api', 'can:viewByMessage,App\Models\MessageReceipt,messageId');
    
    Route::get('/recipient/{recipientType}/{recipientId}', [MessageReceiptController::class, 'getByRecipient'])
        ->name('by-recipient')
        ->middleware('auth:api', 'can:viewByRecipient,App\Models\MessageReceipt,recipientType,recipientId');
    
    // Stats routes
    Route::get('/recipient/{recipientType}/{recipientId}/unread-count', [MessageReceiptController::class, 'getUnreadCount'])
        ->name('unread-count')
        ->middleware('auth:api', 'can:viewByRecipient,App\Models\MessageReceipt,recipientType,recipientId');
});