<?php

use App\Http\Controllers\Api\WalkInSessionController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

Route::post('/facilities/{facilityId}/walkin/session', [WalkInSessionController::class, 'createSession']);

// Upgrade at checkout
Route::post('/pharmacy/checkout/{billingCycleId}/upgrade-walkin', [WalkInSessionController::class, 'upgrade']);

Route::get('/test-system-user-simple', function () {
    // Test without any transaction
    $hash = hash('sha256', 'SYSTEM-WALKIN-USER');
    
    // Just insert directly
    $id = DB::table('users')->insertGetId([
        'global_user_uuid' => Str::uuid()->toString(),
        'national_id_hash' => $hash,
        'identity_state' => 'verified',
        'first_name' => 'Test',
        'last_name' => 'User',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    
    Log::info('Direct insert result', ['id' => $id]);
    
    // Check if it exists
    $user = DB::table('users')->find($id);
    
    return response()->json([
        'inserted_id' => $id,
        'found' => $user,
        'all_users' => DB::table('users')->count(),
    ]);
});