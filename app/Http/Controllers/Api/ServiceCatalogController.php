<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceCatalog\StoreServiceCatalogRequest;
use App\Http\Requests\ServiceCatalog\UpdateServiceCatalogRequest;
use App\Http\Resources\ServiceCatalogResource;
use App\Services\Contracts\ServiceCatalogServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ServiceCatalogController extends Controller
{
    /**
     * The service catalog service instance.
     *
     * @var ServiceCatalogServiceInterface
     */
    protected ServiceCatalogServiceInterface $service;

    /**
     * Create a new controller instance.
     *
     * @param ServiceCatalogServiceInterface $service
     */
    public function __construct(ServiceCatalogServiceInterface $service)
    {
        $this->service = $service;
    }

    /**
     * Display a listing of the resource for current facility.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        Log::alert($request);
        try {
            // Validate facility header is present
            if (!$request->header('X-Facility-Id')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Facility ID is required in request headers (X-Facility-Id)',
                    'data' => []
                ], 400);
            }

            // Extract filters from request
            $filters = $request->only([
                'status',
                'service_category',
                'code_system',
                'applicable_region',
                'risk_level',
                'effective_date',
                'department_specialty',
                'requires_consent',
                'min_duration',
                'max_duration'
            ]);

            $perPage = $request->get('per_page', 15);
            $perPage = min(max($perPage, 1), 100); // Limit between 1 and 100

            // Get service catalogs from service layer (already facility-scoped)
            $result = $this->service->getAllServiceCatalogs($filters, $perPage);

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            // Transform the data using API resources
            $transformedData = ServiceCatalogResource::collection($result['data']['services']);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $transformedData,
                'pagination' => $result['data']['pagination']
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve service catalogs list', [
                'facility_id' => $request->header('X-Facility-Id'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again later.',
                'data' => []
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage for current facility.
     *
     * @param StoreServiceCatalogRequest $request
     * @return JsonResponse
     */
    public function store(StoreServiceCatalogRequest $request): JsonResponse
    {
         Log::info("Creation data: ");
      
        try {
            // Validate facility header is present
            if (!$request->header('X-Facility-Id')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Facility ID is required in request headers (X-Facility-Id)',
                    'data' => []
                ], 400);
            }

            // Get validated data from request
            $validatedData = $request->validated();

            // Create service catalog through service layer (already facility-scoped)
            $result = $this->service->createServiceCatalog($validatedData);

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            // Transform the created service catalog
            $transformedData = new ServiceCatalogResource($result['data']);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $transformedData
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to create service catalog', [
                'facility_id' => $request->header('X-Facility-Id'),
                'error' => $e->getMessage(),
                'data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while creating the service catalog.',
                'data' => []
            ], 500);
        }
    }

    /**
     * Display the specified resource for current facility.
     *
     * @param Request $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        try {
            // Validate facility header is present
            if (!$request->header('X-Facility-Id')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Facility ID is required in request headers (X-Facility-Id)',
                    'data' => []
                ], 400);
            }

            // Get service catalog from service layer (already facility-scoped)
            $result = $this->service->getServiceCatalogByUuid($uuid);

            if (!$result['success']) {
                return response()->json($result, 404);
            }

            // Transform the service catalog
            $transformedData = new ServiceCatalogResource($result['data']);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $transformedData
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve service catalog', [
                'facility_id' => $request->header('X-Facility-Id'),
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again later.',
                'data' => []
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage for current facility.
     *
     * @param UpdateServiceCatalogRequest $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function update(UpdateServiceCatalogRequest $request, string $uuid): JsonResponse
    {
        Log::warning($request);
        try {
            // Validate facility header is present
            if (!$request->header('X-Facility-Id')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Facility ID is required in request headers (X-Facility-Id)',
                    'data' => []
                ], 400);
            }

            // Get validated data from request
            $validatedData = $request->validated();

            // Update service catalog through service layer (already facility-scoped)
            $result = $this->service->updateServiceCatalog($uuid, $validatedData);

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            // Transform the updated service catalog
            $transformedData = new ServiceCatalogResource($result['data']);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $transformedData
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update service catalog', [
                'facility_id' => $request->header('X-Facility-Id'),
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while updating the service catalog.',
                'data' => []
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage for current facility.
     *
     * @param Request $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        try {
            // Validate facility header is present
            if (!$request->header('X-Facility-Id')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Facility ID is required in request headers (X-Facility-Id)',
                    'data' => []
                ], 400);
            }

            // Delete service catalog through service layer (already facility-scoped)
            $result = $this->service->deleteServiceCatalog($uuid);

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result['data']
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete service catalog', [
                'facility_id' => $request->header('X-Facility-Id'),
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while deleting the service catalog.',
                'data' => []
            ], 500);
        }
    }

    /**
     * Restore the specified soft-deleted resource for current facility.
     *
     * @param Request $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function restore(Request $request, string $uuid): JsonResponse
    {
        try {
            // Validate facility header is present
            if (!$request->header('X-Facility-Id')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Facility ID is required in request headers (X-Facility-Id)',
                    'data' => []
                ], 400);
            }

            // Restore service catalog through service layer (already facility-scoped)
            $result = $this->service->restoreServiceCatalog($uuid);

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            // Transform the restored service catalog
            $transformedData = new ServiceCatalogResource($result['data']);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $transformedData
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to restore service catalog', [
                'facility_id' => $request->header('X-Facility-Id'),
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while restoring the service catalog.',
                'data' => []
            ], 500);
        }
    }

    /**
     * Get effective services for a specific date for current facility.
     *
     * @param Request $request
     * @param string $date
     * @return JsonResponse
     */
    public function effectiveServices(Request $request, string $date): JsonResponse
    {
        try {
            // Validate facility header is present
            if (!$request->header('X-Facility-Id')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Facility ID is required in request headers (X-Facility-Id)',
                    'data' => []
                ], 400);
            }

            // Extract filters from request
            $filters = $request->only([
                'service_category',
                'code_system',
                'applicable_region',
                'risk_level',
                'department_specialty'
            ]);

            $filters['effective_date'] = $date;

            // Get effective services from service layer (already facility-scoped)
            $result = $this->service->getEffectiveServices($date, $filters);

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            // Transform the data using API resources
            $transformedData = ServiceCatalogResource::collection($result['data']);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $transformedData
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve effective services', [
                'facility_id' => $request->header('X-Facility-Id'),
                'date' => $date,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again later.',
                'data' => []
            ], 500);
        }
    }

    /**
     * Get services by code system for current facility.
     *
     * @param Request $request
     * @param string $codeSystem
     * @return JsonResponse
     */
    public function byCodeSystem(Request $request, string $codeSystem): JsonResponse
    {
        try {
            // Validate facility header is present
            if (!$request->header('X-Facility-Id')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Facility ID is required in request headers (X-Facility-Id)',
                    'data' => []
                ], 400);
            }

            // Extract filters from request
            $filters = $request->only([
                'status',
                'service_category',
                'applicable_region',
                'risk_level'
            ]);

            // Get services by code system from service layer (already facility-scoped)
            $result = $this->service->getByCodeSystem($codeSystem, $filters);

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            // Transform the data using API resources
            $transformedData = ServiceCatalogResource::collection($result['data']);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $transformedData
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve services by code system', [
                'facility_id' => $request->header('X-Facility-Id'),
                'code_system' => $codeSystem,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again later.',
                'data' => []
            ], 500);
        }
    }

    /**
     * Get services by category for current facility.
     *
     * @param Request $request
     * @param string $category
     * @return JsonResponse
     */
    public function byCategory(Request $request, string $category): JsonResponse
    {
        try {
            // Validate facility header is present
            if (!$request->header('X-Facility-Id')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Facility ID is required in request headers (X-Facility-Id)',
                    'data' => []
                ], 400);
            }

            // Extract filters from request
            $filters = $request->only([
                'status',
                'code_system',
                'applicable_region',
                'risk_level'
            ]);

            // Get services by category from service layer (already facility-scoped)
            $result = $this->service->getByCategory($category, $filters);

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            // Transform the data using API resources
            $transformedData = ServiceCatalogResource::collection($result['data']);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $transformedData
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve services by category', [
                'facility_id' => $request->header('X-Facility-Id'),
                'category' => $category,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again later.',
                'data' => []
            ], 500);
        }
    }

    /**
     * Search services by name or code for current facility.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function search(Request $request): JsonResponse
    {
        try {
            // Validate facility header is present
            if (!$request->header('X-Facility-Id')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Facility ID is required in request headers (X-Facility-Id)',
                    'data' => []
                ], 400);
            }

            // Validate search term
            $searchTerm = $request->get('q');
            
            if (!$searchTerm || strlen(trim($searchTerm)) < 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Search term must be at least 2 characters long.',
                    'data' => []
                ], 400);
            }

            // Extract filters from request
            $filters = $request->only([
                'status',
                'service_category',
                'code_system',
                'applicable_region'
            ]);

            // Search services from service layer (already facility-scoped)
            $result = $this->service->searchServiceCatalogs($searchTerm, $filters);

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            // Transform the data using API resources
            $transformedData = ServiceCatalogResource::collection($result['data']);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $transformedData
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to search service catalogs', [
                'facility_id' => $request->header('X-Facility-Id'),
                'search_term' => $request->get('q'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred during search.',
                'data' => []
            ], 500);
        }
    }

    /**
     * Check if a service is currently effective for current facility.
     *
     * @param Request $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function checkEffectiveness(Request $request, string $uuid): JsonResponse
    {
        try {
            // Validate facility header is present
            if (!$request->header('X-Facility-Id')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Facility ID is required in request headers (X-Facility-Id)',
                    'data' => []
                ], 400);
            }

            $date = $request->get('date', now()->toDateString());

            // Check service effectiveness from service layer (already facility-scoped)
            $result = $this->service->checkServiceEffectiveness($uuid, $date);

            if (!$result['success']) {
                return response()->json($result, 404);
            }

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Failed to check service effectiveness', [
                'facility_id' => $request->header('X-Facility-Id'),
                'uuid' => $uuid,
                'date' => $date ?? 'current date',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while checking service effectiveness.',
                'data' => []
            ], 500);
        }
    }

    /**
     * Get service by service code for current facility.
     *
     * @param Request $request
     * @param string $serviceCode
     * @return JsonResponse
     */
    public function showByCode(Request $request, string $serviceCode): JsonResponse
    {
        try {
            // Validate facility header is present
            if (!$request->header('X-Facility-Id')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Facility ID is required in request headers (X-Facility-Id)',
                    'data' => []
                ], 400);
            }

            // Get service catalog by code from service layer (already facility-scoped)
            $result = $this->service->getServiceCatalogByCode($serviceCode);

            if (!$result['success']) {
                return response()->json($result, 404);
            }

            // Transform the service catalog
            $transformedData = new ServiceCatalogResource($result['data']);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $transformedData
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve service catalog by code', [
                'facility_id' => $request->header('X-Facility-Id'),
                'service_code' => $serviceCode,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again later.',
                'data' => []
            ], 500);
        }
    }
}