<?php

use App\Http\Controllers\Api\Platform\PlatformHubFeedbackRequestController;
use App\Http\Controllers\Api\Platform\PlatformLearningMaterialController;
use App\Http\Controllers\Api\Statistics\PlatformAdminController;
use Illuminate\Support\Facades\Route;

Route::prefix('platform-admin')->middleware(['auth:sanctum', 'admin.access'])->group(function () {
    Route::get('learning-materials/thumbnail-preview', [PlatformLearningMaterialController::class, 'previewThumbnail']);
    Route::post('learning-materials/{learningMaterial}/thumbnail', [PlatformLearningMaterialController::class, 'uploadThumbnailForMaterial']);
    Route::post('learning-materials/thumbnail', [PlatformLearningMaterialController::class, 'uploadThumbnailPending']);
    Route::get('learning-materials', [PlatformLearningMaterialController::class, 'index']);
    Route::post('learning-materials', [PlatformLearningMaterialController::class, 'store']);
    Route::put('learning-materials/{learningMaterial}', [PlatformLearningMaterialController::class, 'update']);
    Route::delete('learning-materials/{learningMaterial}', [PlatformLearningMaterialController::class, 'destroy']);

    Route::get('hub-feedback', [PlatformHubFeedbackRequestController::class, 'index']);
    Route::get('hub-feedback/{hubFeedbackRequest}', [PlatformHubFeedbackRequestController::class, 'show']);
    Route::patch('hub-feedback/{hubFeedbackRequest}', [PlatformHubFeedbackRequestController::class, 'update']);
});

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