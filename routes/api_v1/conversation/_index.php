<?php

use App\Http\Controllers\Api\ConversationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes for Conversations
|--------------------------------------------------------------------------
|
| These routes are for managing conversations in the healthcare system.
| All routes are protected by Sanctum authentication.
|
*/

Route::middleware(['auth:sanctum'])->group(function () {
    
    // Conversation resource routes
    Route::apiResource('conversations', ConversationController::class)
        ->except(['edit', 'create'])
        ->parameters(['conversations' => 'conversation:conversation_uuid']);
    
    // Additional conversation actions
    Route::prefix('conversations/{conversation:conversation_uuid}')->group(function () {
        // Conversation state management
        Route::post('/archive', [ConversationController::class, 'archive'])
            ->name('api.conversations.archive');
        Route::post('/lock', [ConversationController::class, 'lock'])
            ->name('api.conversations.lock');
        Route::post('/activate', [ConversationController::class, 'activate'])
            ->name('api.conversations.activate');
        Route::post('/emergency', [ConversationController::class, 'markAsEmergency'])
            ->name('api.conversations.emergency');
        Route::post('/phi-status', [ConversationController::class, 'updatePHIStatus'])
            ->name('api.conversations.phi-status');
        
        // Participant management
        Route::get('/participants', [ConversationController::class, 'participants'])
            ->name('api.conversations.participants.index');
        Route::post('/participants', [ConversationController::class, 'addParticipant'])
            ->name('api.conversations.participants.store');
        Route::delete('/participants', [ConversationController::class, 'removeParticipant'])
            ->name('api.conversations.participants.destroy');
        
        // Message routes (to be implemented separately)
        // Route::prefix('messages')->group(function () {
        //     Route::get('/', 'MessageController@index')->name('api.conversations.messages.index');
        //     Route::post('/', 'MessageController@store')->name('api.conversations.messages.store');
        // });
    });
    
    // Search and filter endpoints
    Route::get('/conversations/search', [ConversationController::class, 'index'])
        ->name('api.conversations.search');
    
    // Facility-specific conversations
    Route::get('/facilities/{facility}/conversations', [ConversationController::class, 'index'])
        ->name('api.facilities.conversations.index');
});