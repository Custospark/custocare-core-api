<?php
use App\Http\Controllers\Api\FacilitySpaceController;
use App\Http\Controllers\Api\StaffSpaceAssignmentController;
use App\Http\Controllers\Api\ForwardingDirectoryController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {

    // ============================================
    // Facility Spaces Management (Admin-only)
    // ============================================
    // CRUD operations for managing physical spaces within facilities
    Route::get('/facility/spaces', [FacilitySpaceController::class, 'index']);
    Route::post('/facility/spaces', [FacilitySpaceController::class, 'store']);
    Route::get('/facility/spaces/{space}', [FacilitySpaceController::class, 'show']);
    Route::patch('/facility/spaces/{space}', [FacilitySpaceController::class, 'update']);
    Route::delete('/facility/spaces/{space}', [FacilitySpaceController::class, 'destroy']);

    // ============================================
    // Staff Self-Service Space Management
    // ============================================
    // Allows staff members to manage their own space assignments
    Route::get('/staff/space', [StaffSpaceAssignmentController::class, 'myCurrentSpace']);
    Route::post('/staff/space/assign', [StaffSpaceAssignmentController::class, 'assignMySpace']);
    Route::post('/staff/space/release', [StaffSpaceAssignmentController::class, 'releaseMySpace']);

    // ============================================
    // Admin Space Assignment Management
    // ============================================
    // Administrative endpoints for managing staff space assignments
    Route::post('/admin/staff/space/assign', [StaffSpaceAssignmentController::class, 'assignSpaceByAdmin']);
    Route::post('/admin/staff/space/release', [StaffSpaceAssignmentController::class, 'releaseSpaceByAdmin']);
    
    // Get staff list for admin space assignment dropdown
    Route::get('/facility/{facilityId}/staff-for-space-assignment', 
        [StaffSpaceAssignmentController::class, 'getStaffForSpaceAssignment']);

    // ============================================
    // Space Availability & Occupancy
    // ============================================
    // Real-time occupancy tracking and space availability for staff assignment.
    Route::get('/facility/spaces-available', [StaffSpaceAssignmentController::class, 'availableSpaces']);
    Route::get('/facility/spaces-occupancy', [StaffSpaceAssignmentController::class, 'currentOccupancy']);

    // ============================================
    // Forwarding Directory (Presence + Location)
    // ============================================
    // Directory service for finding staff locations and contact info
    Route::get('/facility/forwarding-directory', [ForwardingDirectoryController::class, 'index']);
});