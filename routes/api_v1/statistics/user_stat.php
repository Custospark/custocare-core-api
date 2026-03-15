<?php

use App\Http\Controllers\Api\Statistics\UserStatisticsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Platform Statistics
|--------------------------------------------------------------------------
|
| Protected routes for platform-wide user statistics.
| All routes require authentication and admin permissions via Sanctum.
|
*/

Route::middleware(['auth:sanctum'])->prefix('admin/statistics')->group(function () {
    
    // Main dashboard endpoint - gets all statistics in one call
    Route::get('/dashboard', [UserStatisticsController::class, 'dashboard'])
        ->name('api.admin.statistics.dashboard');
    
    // Individual metric endpoints for targeted loading
    Route::get('/key-metrics', [UserStatisticsController::class, 'keyMetrics'])
        ->name('api.admin.statistics.key-metrics');
    
    Route::get('/verification-funnel', [UserStatisticsController::class, 'verificationFunnel'])
        ->name('api.admin.statistics.verification-funnel');
    
    Route::get('/daily-activity', [UserStatisticsController::class, 'dailyActivity'])
        ->name('api.admin.statistics.daily-activity');
    
    Route::get('/weekly-trends', [UserStatisticsController::class, 'weeklyTrends'])
        ->name('api.admin.statistics.weekly-trends');
    
    Route::get('/monthly-trends', [UserStatisticsController::class, 'monthlyTrends'])
        ->name('api.admin.statistics.monthly-trends');
    
    Route::get('/demographics', [UserStatisticsController::class, 'demographicDistribution'])
        ->name('api.admin.statistics.demographics');
    
    Route::get('/mfa-adoption', [UserStatisticsController::class, 'mfaAdoption'])
        ->name('api.admin.statistics.mfa-adoption');
    
    Route::get('/geographic', [UserStatisticsController::class, 'geographicDistribution'])
        ->name('api.admin.statistics.geographic');
    
    Route::get('/platform', [UserStatisticsController::class, 'platformBreakdown'])
        ->name('api.admin.statistics.platform');
    
    Route::get('/retention', [UserStatisticsController::class, 'userRetention'])
        ->name('api.admin.statistics.retention');
    
    Route::get('/security', [UserStatisticsController::class, 'securityMetrics'])
        ->name('api.admin.statistics.security');
    
    Route::get('/staff-performance', [UserStatisticsController::class, 'staffPerformance'])
        ->name('api.admin.statistics.staff-performance');
    
    // Export endpoint
    Route::get('/export', [UserStatisticsController::class, 'export'])
        ->name('api.admin.statistics.export');
});