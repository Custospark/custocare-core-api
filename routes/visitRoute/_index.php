<?php

use App\Http\Controllers\Api\VisitRouteController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes for Visit Routes
|--------------------------------------------------------------------------
|
| These routes handle patient routing between departments within a facility.
| They track the complete journey of a patient through different departments.
|
*/

Route::middleware(['api'])->group(function () {
    Route::middleware(['auth:api', 'auth:sanctum'])->group(function () {
        // List all visit routes with filters
        Route::get('visit-routes', [VisitRouteController::class, 'index']);
        
        // Get specific visit route
        Route::get('visit-routes/{id}', [VisitRouteController::class, 'show'])
            ->whereNumber('id')
            ->name('visit-routes.show');
        
        // Get routes for a specific visit
        Route::get('visits/{visitId}/routes', [VisitRouteController::class, 'getVisitRoutes'])
            ->whereNumber('visitId')
            ->name('visits.routes.index');
        
        // Get active routes for a facility
        Route::get('facilities/{facilityId}/active-routes', [VisitRouteController::class, 'getActiveRoutes'])
            ->whereNumber('facilityId')
            ->name('facilities.active-routes');
        
        // Get department throughput statistics
        Route::get('departments/{departmentId}/throughput', [VisitRouteController::class, 'getThroughput'])
            ->whereNumber('departmentId')
            ->name('departments.throughput');
    });
    
    Route::middleware(['auth:api', 'scope:visit-routes-write'])->group(function () {
        // Create new visit route
        Route::post('visit-routes', [VisitRouteController::class, 'store'])
            ->name('visit-routes.store');
        
        // Update existing visit route
        Route::put('visit-routes/{id}', [VisitRouteController::class, 'update'])
            ->whereNumber('id')
            ->name('visit-routes.update');
        
        // Delete visit route
        Route::delete('visit-routes/{id}', [VisitRouteController::class, 'destroy'])
            ->whereNumber('id')
            ->name('visit-routes.destroy');
        
        // Acknowledge handoff
        Route::post('visit-routes/{id}/acknowledge-handoff', [VisitRouteController::class, 'acknowledgeHandoff'])
            ->whereNumber('id')
            ->name('visit-routes.acknowledge-handoff');
        
        // Mark as arrived
        Route::post('visit-routes/{id}/mark-arrived', [VisitRouteController::class, 'markAsArrived'])
            ->whereNumber('id')
            ->name('visit-routes.mark-arrived');
        
        // Mark as departed
        Route::post('visit-routes/{id}/mark-departed', [VisitRouteController::class, 'markAsDeparted'])
            ->whereNumber('id')
            ->name('visit-routes.mark-departed');
    });
});