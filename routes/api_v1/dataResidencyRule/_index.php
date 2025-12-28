<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DataResidencyRuleController;

/*
|--------------------------------------------------------------------------
| API Routes for Data Residency Rules
|--------------------------------------------------------------------------
|
| These routes handle all operations related to data residency rules,
| including CRUD operations and validation endpoints.
|
*/

Route::middleware(['auth:api', 'auth:sunctum'])->group(function () {
    
    // Data Residency Rules - CRUD operations
    Route::prefix('data-residency-rules')->name('data-residency-rules.')->group(function () {
        // Get all rules with filtering and pagination
        Route::get('/', [DataResidencyRuleController::class, 'index'])
            ->name('index')
            ->middleware('can:viewAny,App\Models\DataResidencyRule');
        
        // Create a new rule
        Route::post('/', [DataResidencyRuleController::class, 'store'])
            ->name('store')
            ->middleware('can:create,App\Models\DataResidencyRule');
        
        // Get rule by ID
        Route::get('/{dataResidencyRule}', [DataResidencyRuleController::class, 'show'])
            ->name('show')
            ->where('dataResidencyRule', '[0-9]+')
            ->middleware('can:view,dataResidencyRule');
        
        // Update rule
        Route::put('/{dataResidencyRule}', [DataResidencyRuleController::class, 'update'])
            ->name('update')
            ->where('dataResidencyRule', '[0-9]+')
            ->middleware('can:update,dataResidencyRule');
        
        // Delete rule
        Route::delete('/{dataResidencyRule}', [DataResidencyRuleController::class, 'destroy'])
            ->name('destroy')
            ->where('dataResidencyRule', '[0-9]+')
            ->middleware('can:delete,dataResidencyRule');
        
        // Validate data processing
        Route::post('/validate-processing', [DataResidencyRuleController::class, 'validateProcessing'])
            ->name('validate-processing')
            ->middleware('can:validateProcessing,App\Models\DataResidencyRule');
        
        // Validate cross-border transfer
        Route::post('/validate-cross-border-transfer', [DataResidencyRuleController::class, 'validateCrossBorderTransfer'])
            ->name('validate-cross-border-transfer')
            ->middleware('can:validateCrossBorderTransfer,App\Models\DataResidencyRule');
        
        // Get applicable rules for data category and region
        Route::get('/applicable-rules', [DataResidencyRuleController::class, 'getApplicableRules'])
            ->name('applicable-rules')
            ->middleware('can:viewAny,App\Models\DataResidencyRule');
        
        // Get rules summary
        Route::get('/summary', [DataResidencyRuleController::class, 'summary'])
            ->name('summary')
            ->middleware('can:viewSummary,App\Models\DataResidencyRule');
    });
    
});