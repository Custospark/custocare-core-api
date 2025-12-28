<?php

use App\Http\Controllers\Api\VisitCurrentStateController;
use Illuminate\Support\Facades\Route;

// Visit Current States API Routes
Route::prefix('visit-current-states')->name('visit-current-states.')->group(function () {
    // Public endpoints (with authentication)
    Route::middleware(['auth:api','auth:sunctum'])->group(function () {
        // Standard CRUD operations
        Route::get('/', [VisitCurrentStateController::class, 'index'])->name('index');
        Route::post('/', [VisitCurrentStateController::class, 'store'])->name('store');
        Route::get('/{id}', [VisitCurrentStateController::class, 'show'])->name('show');
        Route::put('/{id}', [VisitCurrentStateController::class, 'update'])->name('update');
        Route::delete('/{id}', [VisitCurrentStateController::class, 'destroy'])->name('destroy');
        
        // Specialized endpoints
        Route::get('/visit/{visitId}', [VisitCurrentStateController::class, 'getByVisitId'])->name('by-visit');
        Route::get('/facility/{facilityId}', [VisitCurrentStateController::class, 'getByFacility'])->name('by-facility');
        Route::get('/facility/{facilityId}/critical-alerts', [VisitCurrentStateController::class, 'getCriticalAlerts'])->name('critical-alerts');
        Route::get('/facility/{facilityId}/dashboard-stats', [VisitCurrentStateController::class, 'getDashboardStats'])->name('dashboard-stats');
        
        // CDC event processing (protected with specific middleware)
        Route::middleware(['can:processEvents,App\Models\VisitCurrentState'])->post('/process-event/{visitId}', [VisitCurrentStateController::class, 'processEvent'])->name('process-event');
    });
});

// Additional dashboard routes
Route::prefix('dashboard')->middleware(['auth:api'])->group(function () {
    Route::get('/wait-times', function () {
        // This would be handled by a separate DashboardController
        return response()->json([
            'success' => true,
            'message' => 'Dashboard endpoint',
            'data' => []
        ]);
    });
});