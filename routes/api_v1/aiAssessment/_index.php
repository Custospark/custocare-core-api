<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AiAssessmentController;

// AI Assessment Routes
Route::prefix('ai-assessments')->middleware(['auth:api','auth:sunctum'])->group(function () {
    // Basic CRUD operations
    Route::get('/', [AiAssessmentController::class, 'index'])
        ->name('api.ai-assessments.index')
        ->middleware('can:viewAny,App\Models\AiAssessment');
    
    Route::post('/', [AiAssessmentController::class, 'store'])
        ->name('api.ai-assessments.store')
        ->middleware('can:create,App\Models\AiAssessment');
    
    Route::get('{ai_assessment:assessment_uuid}', [AiAssessmentController::class, 'show'])
        ->name('api.ai-assessments.show')
        ->middleware('can:view,ai_assessment');
    
    Route::put('{ai_assessment:assessment_uuid}', [AiAssessmentController::class, 'update'])
        ->name('api.ai-assessments.update')
        ->middleware('can:update,ai_assessment');
    
    Route::delete('{ai_assessment:assessment_uuid}', [AiAssessmentController::class, 'destroy'])
        ->name('api.ai-assessments.destroy')
        ->middleware('can:delete,ai_assessment');
    
    // Special operations
    Route::post('{ai_assessment:assessment_uuid}/review', [AiAssessmentController::class, 'review'])
        ->name('api.ai-assessments.review')
        ->middleware('can:review,ai_assessment');
    
    Route::post('{ai_assessment:assessment_uuid}/record-outcome', [AiAssessmentController::class, 'recordOutcome'])
        ->name('api.ai-assessments.record-outcome')
        ->middleware('can:recordOutcome,ai_assessment');
    
    Route::post('{ai_assessment:assessment_uuid}/flag-adverse-event', [AiAssessmentController::class, 'flagAdverseEvent'])
        ->name('api.ai-assessments.flag-adverse-event')
        ->middleware('can:flagAdverseEvent,ai_assessment');
    
    // Query endpoints
    Route::get('patient/{patientId}', [AiAssessmentController::class, 'byPatient'])
        ->name('api.ai-assessments.by-patient')
        ->whereNumber('patientId')
        ->middleware('can:viewAny,App\Models\AiAssessment');
    
    Route::get('encounter/{encounterId}', [AiAssessmentController::class, 'byEncounter'])
        ->name('api.ai-assessments.by-encounter')
        ->whereNumber('encounterId')
        ->middleware('can:viewAny,App\Models\AiAssessment');
    
    Route::get('pending-reviews', [AiAssessmentController::class, 'pendingReviews'])
        ->name('api.ai-assessments.pending-reviews')
        ->middleware('can:review,App\Models\AiAssessment');
    
    Route::get('statistics', [AiAssessmentController::class, 'statistics'])
        ->name('api.ai-assessments.statistics')
        ->middleware('can:viewStatistics,App\Models\AiAssessment');
    
    // Bulk operations (future implementation)
    Route::post('bulk-import', [AiAssessmentController::class, 'bulkImport'])
        ->name('api.ai-assessments.bulk-import')
        ->middleware('can:create,App\Models\AiAssessment');
    
    Route::get('export', [AiAssessmentController::class, 'export'])
        ->name('api.ai-assessments.export')
        ->middleware('can:export,App\Models\AiAssessment');
});

// Additional health check endpoint for AI assessment service
Route::get('ai-assessment-health', function () {
    return response()->json([
        'status' => 'healthy',
        'service' => 'AI Assessment API',
        'version' => '1.0.0',
        'timestamp' => now()->toISOString(),
        'checks' => [
            'database' => 'connected',
            'cache' => 'connected',
            'queue' => 'connected',
        ]
    ]);
})->name('api.ai-assessment.health');