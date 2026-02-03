<?php
use App\Http\Controllers\Api\FacilitySpaceController;
use App\Http\Controllers\Api\StaffSpaceAssignmentController;
use App\Http\Controllers\Api\ForwardingDirectoryController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {

    // Spaces (admin-managed)
    Route::get('/facility/spaces', [FacilitySpaceController::class, 'index']);
    Route::post('/facility/spaces', [FacilitySpaceController::class, 'store']);
    Route::get('/facility/spaces/{space}', [FacilitySpaceController::class, 'show']);
    Route::patch('/facility/spaces/{space}', [FacilitySpaceController::class, 'update']);
    Route::delete('/facility/spaces/{space}', [FacilitySpaceController::class, 'destroy']);

    // Staff room assignment (staff picks room)
    Route::get('/staff/space', [StaffSpaceAssignmentController::class, 'myCurrentSpace']);
    Route::post('/staff/space/assign', [StaffSpaceAssignmentController::class, 'assignMySpace']);
    Route::post('/staff/space/release', [StaffSpaceAssignmentController::class, 'releaseMySpace']);

    // Facility occupancy overview (admin/ops)
    Route::get('/facility/spaces/occupancy', [StaffSpaceAssignmentController::class, 'currentOccupancy']);

    // Forwarding Directory (presence + location)
    Route::get('/facility/forwarding-directory', [ForwardingDirectoryController::class, 'index']);
});
