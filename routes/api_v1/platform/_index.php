<?php

use App\Http\Controllers\Api\Statistics\PlatformAdminController;
use Illuminate\Support\Facades\Route;

Route::prefix('platform-admin')->middleware(['auth:sanctum'])->group(function () {
    // Facilities
    Route::get('list-all-platform-facilities', [PlatformAdminController::class, 'listFacilities']);
    Route::patch('facilities/{facilityId}/status', [PlatformAdminController::class, 'updateFacilityStatus']);

    // Users
    Route::get('list-all-platform-users', [PlatformAdminController::class, 'listUsers']);
    Route::patch('users/{userId}/status', [PlatformAdminController::class, 'updateUserStatus']);

    // Patients
    Route::get('list-all-platform-patients', [PlatformAdminController::class, 'listPatients']);
});