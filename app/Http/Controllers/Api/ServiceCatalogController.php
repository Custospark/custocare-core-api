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
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
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

            // Get service catalogs from service layer
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
     * Store a newly created resource in storage.
     *
     * @param StoreServiceCatalogRequest $request
     * @return JsonResponse
     */
    public function store(StoreServiceCatalogRequest $request): JsonResponse
    {
        try {
            // Get validated data from request
            $validatedData = $request->validated();

            // Create service catalog through service layer
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
     * Display the specified resource.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function show(string $uuid): JsonResponse
    {
        try {
            // Get service catalog from service layer
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
     * Update the specified resource in storage.
     *
     * @param UpdateServiceCatalogRequest $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function update(UpdateServiceCatalogRequest $request, string $uuid): JsonResponse
    {
        try {
            // Get validated data from request
            $validatedData = $request->validated();

            // Update service catalog through service layer
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
     * Remove the specified resource from storage.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function destroy(string $uuid): JsonResponse
    {
        try {
            // Delete service catalog through service layer
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
     * Restore the specified soft-deleted resource.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function restore(string $uuid): JsonResponse
    {
        try {
            // Restore service catalog through service layer
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
     * Get effective services for a specific date.
     *
     * @param Request $request
     * @param string $date
     * @return JsonResponse
     */
    public function effectiveServices(Request $request, string $date): JsonResponse
    {
        try {
            // Extract filters from request
            $filters = $request->only([
                'service_category',
                'code_system',
                'applicable_region',
                'risk_level',
                'department_specialty'
            ]);

            $filters['effective_date'] = $date;

            // Get effective services from service layer
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
     * Get services by code system.
     *
     * @param Request $request
     * @param string $codeSystem
     * @return JsonResponse
     */
    public function byCodeSystem(Request $request, string $codeSystem): JsonResponse
    {
        try {
            // Extract filters from request
            $filters = $request->only([
                'status',
                'service_category',
                'applicable_region',
                'risk_level'
            ]);

            // Get services by code system from service layer
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
     * Get services by category.
     *
     * @param Request $request
     * @param string $category
     * @return JsonResponse
     */
    public function byCategory(Request $request, string $category): JsonResponse
    {
        try {
            // Extract filters from request
            $filters = $request->only([
                'status',
                'code_system',
                'applicable_region',
                'risk_level'
            ]);

            // Get services by category from service layer
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
     * Search services by name or code.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function search(Request $request): JsonResponse
    {
        try {
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

            // Search services from service layer
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
     * Check if a service is currently effective.
     *
     * @param Request $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function checkEffectiveness(Request $request, string $uuid): JsonResponse
    {
        try {
            $date = $request->get('date', now()->toDateString());

            // Check service effectiveness from service layer
            $result = $this->service->checkServiceEffectiveness($uuid, $date);

            if (!$result['success']) {
                return response()->json($result, 404);
            }

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Failed to check service effectiveness', [
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
     * Get service by service code.
     *
     * @param string $serviceCode
     * @return JsonResponse
     */
    public function showByCode(string $serviceCode): JsonResponse
    {
        try {
            // Get service catalog by code from service layer
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