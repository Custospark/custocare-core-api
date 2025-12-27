<?php

use App\Http\Controllers\Api\VisitEventController;
use Illuminate\Support\Facades\Route;

// Visit Events API Routes
Route::prefix('v1')->middleware(['api', 'auth:sanctum'])->group(function () {
    // Standard RESTful routes
    Route::apiResource('visit-events', VisitEventController::class)
        ->except(['destroy']) // Destroy is handled specially
        ->parameters(['visit-events' => 'visit_event']);
    
    // Custom routes
    Route::get('visits/{visit}/clinical-timeline', [VisitEventController::class, 'clinicalTimeline'])
        ->name('api.visits.clinical-timeline');
    
    Route::get('visits/{visit}/state-timeline', [VisitEventController::class, 'visitStateTimeline'])
        ->name('api.visits.state-timeline');
    
    Route::get('visits/{visit}/verify-chain', [VisitEventController::class, 'verifyChain'])
        ->name('api.visits.verify-chain');
    
    Route::post('visit-events/{event}/recalculate-hash', [VisitEventController::class, 'recalculateHash'])
        ->name('api.visit-events.recalculate-hash');
    
    Route::get('facilities/{facility}/event-report', [VisitEventController::class, 'facilityReport'])
        ->name('api.facilities.event-report');
    
    Route::get('facilities/{facility}/event-statistics', [VisitEventController::class, 'statistics'])
        ->name('api.facilities.event-statistics');
    
    // Special delete route with extra validation
    Route::delete('visit-events/{visit_event}', [VisitEventController::class, 'destroy'])
        ->name('api.visit-events.destroy')
        ->middleware('can:forceDelete,visit_event');
});