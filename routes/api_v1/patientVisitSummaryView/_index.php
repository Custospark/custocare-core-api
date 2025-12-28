<?php

use App\Http\Controllers\Api\PatientVisitSummaryViewController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes for Patient Visit Summary Views
|--------------------------------------------------------------------------
|
| These routes handle patient portal & care coordination summary views.
| Refresh Strategy: Nightly batch + real-time for active visits.
|
*/

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    // Standard RESTful routes
    Route::apiResource('patient-visit-summary-views', PatientVisitSummaryViewController::class)
        ->except(['show']); // We have a custom show route
    
    // Custom routes
    Route::get('patient-visit-summary-views/patient/{patientId}', 
        [PatientVisitSummaryViewController::class, 'showByPatientId'])
        ->name('patient-visit-summary-views.show-by-patient')
        ->middleware('can:view,App\Models\PatientVisitSummaryView');
    
    Route::post('patient-visit-summary-views/{patientId}/refresh', 
        [PatientVisitSummaryViewController::class, 'refresh'])
        ->name('patient-visit-summary-views.refresh')
        ->middleware('can:refresh,App\Models\PatientVisitSummaryView');
    
    Route::post('patient-visit-summary-views/batch-refresh', 
        [PatientVisitSummaryViewController::class, 'batchRefresh'])
        ->name('patient-visit-summary-views.batch-refresh')
        ->middleware('can:batchRefresh,App\Models\PatientVisitSummaryView');
    
    Route::get('patient-visit-summary-views/upcoming-appointments', 
        [PatientVisitSummaryViewController::class, 'upcomingAppointments'])
        ->name('patient-visit-summary-views.upcoming-appointments')
        ->middleware('can:viewAny,App\Models\PatientVisitSummaryView');
    
    Route::get('patient-visit-summary-views/patient/{patientId}/health-metrics-trends', 
        [PatientVisitSummaryViewController::class, 'healthMetricsTrends'])
        ->name('patient-visit-summary-views.health-metrics-trends')
        ->middleware('can:viewHealthMetrics,App\Models\PatientVisitSummaryView');
    
    Route::get('patient-visit-summary-views/care-coordination-insights', 
        [PatientVisitSummaryViewController::class, 'careCoordinationInsights'])
        ->name('patient-visit-summary-views.care-coordination-insights')
        ->middleware('can:viewInsights,App\Models\PatientVisitSummaryView');
});

// Public routes (if any) - for example, patient portal access
Route::middleware(['auth:sanctum', 'patient.portal.access'])->group(function () {
    Route::get('portal/patient-summary/{patientId}', 
        [PatientVisitSummaryViewController::class, 'showByPatientId'])
        ->name('portal.patient-summary');
});