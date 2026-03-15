<?php

// Add these routes to your existing api.php file

use App\Http\Controllers\Api\Statistics\FacilityStatisticsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->prefix('admin/statistics')->group(function () {
        
    // Facility Statistics Routes
    Route::prefix('facilities')->group(function () {
        
        // Main dashboard endpoint - gets all facility statistics in one call
        Route::get('/dashboard', [FacilityStatisticsController::class, 'dashboard'])
            ->name('api.admin.statistics.facilities.dashboard');
        
        // Individual metric endpoints
        Route::get('/key-metrics', [FacilityStatisticsController::class, 'keyMetrics'])
            ->name('api.admin.statistics.facilities.key-metrics');
        
        Route::get('/type-distribution', [FacilityStatisticsController::class, 'facilityTypeDistribution'])
            ->name('api.admin.statistics.facilities.type-distribution');
        
        Route::get('/tier-distribution', [FacilityStatisticsController::class, 'facilityTierDistribution'])
            ->name('api.admin.statistics.facilities.tier-distribution');
        
        Route::get('/nature-distribution', [FacilityStatisticsController::class, 'natureDistribution'])
            ->name('api.admin.statistics.facilities.nature-distribution');
        
        Route::get('/status-distribution', [FacilityStatisticsController::class, 'operationalStatusDistribution'])
            ->name('api.admin.statistics.facilities.status-distribution');
        
        Route::get('/geographic', [FacilityStatisticsController::class, 'geographicDistribution'])
            ->name('api.admin.statistics.facilities.geographic');
        
        Route::get('/capacity', [FacilityStatisticsController::class, 'capacityMetrics'])
            ->name('api.admin.statistics.facilities.capacity');
        
        Route::get('/services', [FacilityStatisticsController::class, 'serviceAvailability'])
            ->name('api.admin.statistics.facilities.services');
        
        Route::get('/specialties', [FacilityStatisticsController::class, 'specialtyServices'])
            ->name('api.admin.statistics.facilities.specialties');
        
        Route::get('/emergency', [FacilityStatisticsController::class, 'emergencyCapabilities'])
            ->name('api.admin.statistics.facilities.emergency');
        
        Route::get('/accreditations', [FacilityStatisticsController::class, 'accreditationStats'])
            ->name('api.admin.statistics.facilities.accreditations');
        
        Route::get('/licenses', [FacilityStatisticsController::class, 'licenseExpiryMetrics'])
            ->name('api.admin.statistics.facilities.licenses');
        
        Route::get('/performance', [FacilityStatisticsController::class, 'performanceMetrics'])
            ->name('api.admin.statistics.facilities.performance');
        
        Route::get('/data-residency', [FacilityStatisticsController::class, 'dataResidencyDistribution'])
            ->name('api.admin.statistics.facilities.data-residency');
        
        Route::get('/growth-trends', [FacilityStatisticsController::class, 'facilityGrowthTrends'])
            ->name('api.admin.statistics.facilities.growth-trends');
        
        // Export endpoint
        Route::get('/export', [FacilityStatisticsController::class, 'export'])
            ->name('api.admin.statistics.facilities.export');
    });
});