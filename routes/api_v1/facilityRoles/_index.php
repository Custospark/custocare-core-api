<?php

use App\Http\Controllers\Api\FacilityRoleController;
use Illuminate\Support\Facades\Route;

// Facility Role Routes
Route::prefix('facility-roles')->name('facility-roles.')->middleware(['auth:sanctum'])->group(function () {
    // CRUD Operations
    Route::get('/', [FacilityRoleController::class, 'getSystemRoles'])->name('index');
    Route::post('/', [FacilityRoleController::class, 'store'])->name('store');
    Route::get('/{id}', [FacilityRoleController::class, 'show'])->name('show');
    Route::put('/{id}', [FacilityRoleController::class, 'update'])->name('update');
    Route::delete('/{id}', [FacilityRoleController::class, 'destroy'])->name('destroy');
    
    // Additional routes
    Route::get('/code/{code}', [FacilityRoleController::class, 'showByCode'])->name('show-by-code');
    Route::get('/facility/{facilityId}', [FacilityRoleController::class, 'getByFacility'])->name('by-facility');
    Route::get('/category/{category}', [FacilityRoleController::class, 'getByCategory'])->name('by-category');
    Route::patch('/{id}/toggle-status', [FacilityRoleController::class, 'toggleStatus'])->name('toggle-status');
    Route::post('/{id}/assign-permissions', [FacilityRoleController::class, 'assignPermissions'])->name('assign-permissions');
    Route::get('/{id}/permissions', [FacilityRoleController::class, 'getPermissions'])->name('permissions');
    Route::get('/system/roles', [FacilityRoleController::class, 'getSystemRoles'])->name('system-roles');
    Route::get('/custom/roles', [FacilityRoleController::class, 'getCustomRoles'])->name('custom-roles');
    Route::get('/search', [FacilityRoleController::class, 'search'])->name('search');
});