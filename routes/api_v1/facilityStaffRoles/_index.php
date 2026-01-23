<?php

use App\Http\Controllers\Api\FacilityStaffRoleController;
use Illuminate\Support\Facades\Route;

// Facility Staff Roles API Routes
Route::prefix('facility-staff-roles')->middleware(['auth:sanctum'])->group(function () {
    // Standard RESTful routes
    Route::get('/', [FacilityStaffRoleController::class, 'index']);
    Route::get('/facility-staff-roles/search', [FacilityStaffRoleController::class, 'facilityStaffRoleSearch']);
    Route::post('/', [FacilityStaffRoleController::class, 'store']);
    Route::get('/{id}', [FacilityStaffRoleController::class, 'show']);
    Route::put('/{id}', [FacilityStaffRoleController::class, 'update']);
    Route::delete('/{id}', [FacilityStaffRoleController::class, 'destroy']);
    
    // Additional routes
    Route::get('/facility/{facilityId}', [FacilityStaffRoleController::class, 'byFacility']);
    Route::get('/staff/{staffId}', [FacilityStaffRoleController::class, 'byStaff']);
    Route::put('/{id}/status', [FacilityStaffRoleController::class, 'updateStatus']);
    Route::put('/{id}/credentialing', [FacilityStaffRoleController::class, 'updateCredentialing']);
    Route::post('/staff/{staffId}/check-schedule', [FacilityStaffRoleController::class, 'checkScheduleConflicts']);
    Route::get('/expiring/assignments', [FacilityStaffRoleController::class, 'expiringAssignments']);
});

Route::get('/facility-staff-roles/uuid/{uuid}', [FacilityStaffRoleController::class, 'showByUuid']);