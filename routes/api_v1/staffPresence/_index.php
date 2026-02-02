<?php
use App\Http\Controllers\Api\StaffPresenceController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {

    // Presence for current staff
    Route::get('/staff/presence', [StaffPresenceController::class, 'myPresence']);
    Route::post('/staff/presence', [StaffPresenceController::class, 'setMyPresence']);

    // Forwarding helper: show eligible staff presence
    Route::get('/facilities/staff/eligible-for-forwarding', [StaffPresenceController::class, 'eligibleForForwarding']);

    
});
