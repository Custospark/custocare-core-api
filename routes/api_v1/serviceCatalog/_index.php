<?php

use App\Http\Controllers\Api\ServiceCatalogController;
use Illuminate\Support\Facades\Route;

/**
 * Service Catalog API Routes
 * 
 * All routes are facility-scoped and require:
 * 1. Authentication via Sanctum tokens
 * 2. X-Facility-Id header for multi-tenancy
 * 
 * Route naming convention: aservice-catalogs.{action}
 * 
 */
Route::prefix('service-catalogs')->name('service-catalogs.')->group(function () {
    
    /**
     * Authentication Middleware
     * All service catalog endpoints require authenticated access
     */
    Route::middleware(['auth:sanctum'])->group(function () {
        
        // ========================
        // COLLECTION ENDPOINTS
        // ========================
        
        /**
         * GET /service-catalogs
         * 
         * List all service catalogs for the current facility with pagination
         * Query Parameters:
         * - per_page: Number of items per page (default: 15, max: 100)
         * - status: Filter by service status
         * - service_category: Filter by category
         * - code_system: Filter by code system (CPT, HCPCS, etc.)
         * - applicable_region: Filter by region
         * - risk_level: Filter by risk level
         * - effective_date: Filter by effective date
         * - department_specialty: Filter by department
         * - requires_consent: Filter by consent requirement
         * - min_duration/max_duration: Filter by duration range
         * - min_price/max_price: Filter by price range
         * - currency_code: Filter by currency
         * 
         * Headers Required:
         * - X-Facility-Id: Facility identifier
         * - Authorization: Bearer token
         */
        Route::get('/', [ServiceCatalogController::class, 'index'])->name('index');
        
        /**
         * POST /service-catalogs
         * 
         * Create a new service catalog entry for the current facility
         * 
         * Headers Required:
         * - X-Facility-Id: Facility identifier
         * - Authorization: Bearer token
         * - Content-Type: application/json
         */
        Route::post('/', [ServiceCatalogController::class, 'store'])->name('store');
        
        // ========================
        // SEARCH & FILTER ENDPOINTS
        // ========================
        
        /**
         * GET /service-catalogs/search
         * 
         * Search service catalogs by name, code, or description
         * Query Parameters:
         * - q: Search term (min 2 characters)
         * - status: Filter by status
         * - service_category: Filter by category
         * - code_system: Filter by code system
         * - applicable_region: Filter by region
         */
        Route::get('/search', [ServiceCatalogController::class, 'search'])->name('search');
        
        /**
         * GET /service-catalogs/effective/{date}
         * 
         * Get all services effective on a specific date
         * Path Parameter:
         * - date: Date in YYYY-MM-DD format
         */
        Route::get('/effective/{date}', [ServiceCatalogController::class, 'effectiveServices'])
            ->where('date', '\d{4}-\d{2}-\d{2}')
            ->name('effective');
        
        /**
         * GET /service-catalogs/code-system/{codeSystem}
         * 
         * Get services by coding system (CPT, HCPCS, ICD-10-PCS, etc.)
         * Path Parameter:
         * - codeSystem: One of: cpt, hcpcs, icd_10_pcs, cdt, local_custom
         */
        Route::get('/code-system/{codeSystem}', [ServiceCatalogController::class, 'byCodeSystem'])
            ->where('codeSystem', 'cpt|hcpcs|icd_10_pcs|cdt|local_custom')
            ->name('by-code-system');
        
        /**
         * GET /service-catalogs/category/{category}
         * 
         * Get services by clinical category
         * Path Parameter:
         * - category: Service category (evaluation_management, diagnostic_imaging, etc.)
         */
        Route::get('/category/{category}', [ServiceCatalogController::class, 'byCategory'])
            ->name('by-category');
        
        /**
         * GET /service-catalogs/code/{serviceCode}
         * 
         * Get service catalog by facility-specific service code
         * Path Parameter:
         * - serviceCode: Facility-specific service code (max 50 chars)
         */
        Route::get('/code/{serviceCode}', [ServiceCatalogController::class, 'showByCode'])
            ->name('show-by-code');
        
        // ========================
        // INDIVIDUAL RESOURCE ENDPOINTS
        // ========================
        
        Route::prefix('{serviceCatalog}')->group(function () {
            
            /**
             * GET /service-catalogs/{uuid}
             * 
             * Retrieve a specific service catalog by UUID
             * Path Parameter:
             * - uuid: Service catalog UUID
             */
            Route::get('/', [ServiceCatalogController::class, 'show'])->name('show');
            
            /**
             * PUT/PATCH /service-catalogs/{uuid}
             * 
             * Update a service catalog (full or partial update)
             * Path Parameter:
             * - uuid: Service catalog UUID
             */
            Route::match(['put', 'patch'], '/', [ServiceCatalogController::class, 'update'])
                ->name('update');
            
            /**
             * DELETE /service-catalogs/{uuid}
             * 
             * Soft delete a service catalog
             * Path Parameter:
             * - uuid: Service catalog UUID
             */
            Route::delete('/', [ServiceCatalogController::class, 'destroy'])->name('destroy');
            
            // ========================
            // SPECIAL ACTIONS
            // ========================
            
            /**
             * POST /service-catalogs/{uuid}/restore
             * 
             * Restore a soft-deleted service catalog
             * Path Parameter:
             * - uuid: Service catalog UUID
             */
            Route::post('/restore', [ServiceCatalogController::class, 'restore'])->name('restore');
            
            /**
             * GET /service-catalogs/{uuid}/check-effectiveness
             * 
             * Check if a service is effective on a given date
             * Path Parameter:
             * - uuid: Service catalog UUID
             * Query Parameter:
             * - date: Check date (default: current date)
             */
            Route::get('/check-effectiveness', [ServiceCatalogController::class, 'checkEffectiveness'])
                ->name('check-effectiveness');
        });
    });
});