<?php

use App\Http\Controllers\Api\ConversationParticipantController;
use Illuminate\Support\Facades\Route;

// Conversation Participants Routes
Route::prefix('conversation-participants')->name('conversation-participants.')->middleware(['auth:sanctum'])->group(function () {
    // Basic CRUD operations
    Route::get('/', [ConversationParticipantController::class, 'index'])->name('index');
    Route::post('/', [ConversationParticipantController::class, 'store'])->name('store');
    Route::get('/{conversation_participant}', [ConversationParticipantController::class, 'show'])->name('show');
    Route::put('/{conversation_participant}', [ConversationParticipantController::class, 'update'])->name('update');
    Route::delete('/{conversation_participant}', [ConversationParticipantController::class, 'destroy'])->name('destroy');
    
    // Custom actions
    Route::post('/{conversation_participant}/leave', [ConversationParticipantController::class, 'leave'])->name('leave');
    Route::post('/{conversation_participant}/mute', [ConversationParticipantController::class, 'mute'])->name('mute');
    Route::post('/{conversation_participant}/unmute', [ConversationParticipantController::class, 'unmute'])->name('unmute');
    Route::put('/{conversation_participant}/role', [ConversationParticipantController::class, 'updateRole'])->name('update-role');
});

// Conversation-specific participants routes
Route::prefix('conversations/{conversation}/participants')->name('conversations.participants.')->middleware(['auth:api'])->group(function () {
    Route::get('/', [ConversationParticipantController::class, 'index'])->name('index');
    Route::post('/', [ConversationParticipantController::class, 'store'])->name('store');
});