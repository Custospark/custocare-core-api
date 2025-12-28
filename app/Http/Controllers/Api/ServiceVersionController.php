<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceVersion\StoreServiceVersionRequest;
use App\Http\Requests\ServiceVersion\UpdateServiceVersionRequest;
use App\Http\Resources\ServiceVersionResource;
use App\Services\Contracts\ServiceVersionServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * ServiceVersionController
 * 
 * RESTful API controller for ServiceVersion operations.
 * Thin controller that delegates business logic to services.
 * Handles HTTP requests and returns consistent JSON responses.
 */
class ServiceVersionController extends Controller
{
    /**
     * Service instance.
     *
     * @var ServiceVersionServiceInterface
     */
    protected $service;

    /**
     * Constructor with dependency injection.
     *
     * @param ServiceVersionServiceInterface $service
     */
    public function __construct(ServiceVersionServiceInterface $service)
    {
        $this->service = $service;
        
        // Apply middleware
        // $this->middleware('auth:api')->except(['index', 'show']);
        // $this->middleware('scope:service-versions-read')->only(['index', 'show']);
        // $this->middleware('scope:service-versions-write')->only(['store', 'update', 'destroy']);
        // $this->middleware('can:viewAny,App\Models\ServiceVersion')->only('index');
        // $this->middleware('can:create,App\Models\ServiceVersion')->only('store');
        // $this->middleware('can:view,serviceVersion')->only('show');
        // $this->middleware('can:update,serviceVersion')->only('update');
        // $this->middleware('can:delete,serviceVersion')->only('destroy');
    }

    /**
     * Display a listing of service versions.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);
            $filters = $request->only([
                'service_catalog_id',
                'facility_id',
                'is_current',
                'valid_on',
                'is_billable',
                'currency_code',
                'search',
                'valid_from_start',
                'valid_from_end'
            ]);
            
            $result = $this->service->getPaginatedServiceVersions($perPage, $filters);
            
            if (!$result['success']) {
                return response()->json($result, $result['status']);
            }
            
            // Transform data using Resource
            $transformedData = ServiceVersionResource::collection($result['data']);
            
            // Create custom response with pagination
            $response = [
                'success' => true,
                'message' => $result['message'],
                'data' => $transformedData,
                'pagination' => $result['pagination'],
                'status' => $result['status']
            ];
            
            return response()->json($response, $result['status']);
        } catch (\Exception $e) {
            Log::error('Error in ServiceVersionController@index', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve service versions',
                'data' => null,
                'status' => 500
            ], 500);
        }
    }

    /**
     * Store a newly created service version in storage.
     *
     * @param StoreServiceVersionRequest $request
     * @return JsonResponse
     */
    public function store(StoreServiceVersionRequest $request): JsonResponse
    {
        try {
            // Authorize the action
            $this->authorize('create', \App\Models\ServiceVersion::class);
            
            $data = $request->validated();
            
            // Add created_by_staff_id if not provided and user is staff
            if (!isset($data['created_by_staff_id']) && $request->user() && $request->user()->staff) {
                $data['created_by_staff_id'] = $request->user()->staff->id;
            }
            
            $result = $this->service->createServiceVersion($data);
            
            if (!$result['success']) {
                return response()->json($result, $result['status']);
            }
            
            // Transform data using Resource
            $transformedData = new ServiceVersionResource($result['data']);
            
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $transformedData,
                'status' => $result['status']
            ], $result['status']);
        } catch (\Exception $e) {
            Log::error('Error in ServiceVersionController@store', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'input' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create service version',
                'data' => null,
                'status' => 500
            ], 500);
        }
    }

    /**
     * Display the specified service version.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function show(string $uuid): JsonResponse
    {
        try {
            $result = $this->service->getServiceVersionByUuid($uuid);
            
            if (!$result['success']) {
                return response()->json($result, $result['status']);
            }
            
            // Authorize the action
            $this->authorize('view', $result['data']);
            
            // Transform data using Resource
            $transformedData = new ServiceVersionResource($result['data']);
            
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $transformedData,
                'status' => $result['status']
            ], $result['status']);
        } catch (\Exception $e) {
            Log::error('Error in ServiceVersionController@show', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve service version',
                'data' => null,
                'status' => 500
            ], 500);
        }
    }

    /**
     * Update the specified service version in storage.
     *
     * @param UpdateServiceVersionRequest $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function update(UpdateServiceVersionRequest $request, string $uuid): JsonResponse
    {
        try {
            // First get the service version
            $getResult = $this->service->getServiceVersionByUuid($uuid);
            
            if (!$getResult['success']) {
                return response()->json($getResult, $getResult['status']);
            }
            
            $serviceVersion = $getResult['data'];
            
            // Authorize the action
            $this->authorize('update', $serviceVersion);
            
            $data = $request->validated();
            $result = $this->service->updateServiceVersion($serviceVersion->id, $data);
            
            if (!$result['success']) {
                return response()->json($result, $result['status']);
            }
            
            // Transform data using Resource
            $transformedData = new ServiceVersionResource($result['data']);
            
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $transformedData,
                'status' => $result['status']
            ], $result['status']);
        } catch (\Exception $e) {
            Log::error('Error in ServiceVersionController@update', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'input' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update service version',
                'data' => null,
                'status' => 500
            ], 500);
        }
    }

    /**
     * Remove the specified service version from storage.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function destroy(string $uuid): JsonResponse
    {
        try {
            // First get the service version
            $getResult = $this->service->getServiceVersionByUuid($uuid);
            
            if (!$getResult['success']) {
                return response()->json($getResult, $getResult['status']);
            }
            
            $serviceVersion = $getResult['data'];
            
            // Authorize the action
            $this->authorize('delete', $serviceVersion);
            
            $result = $this->service->deleteServiceVersion($serviceVersion->id);
            
            return response()->json($result, $result['status']);
        } catch (\Exception $e) {
            Log::error('Error in ServiceVersionController@destroy', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete service version',
                'data' => null,
                'status' => 500
            ], 500);
        }
    }

    /**
     * Get current version for a service catalog.
     *
     * @param Request $request
     * @param int $serviceCatalogId
     * @return JsonResponse
     */
    public function getCurrentVersion(Request $request, int $serviceCatalogId): JsonResponse
    {
        try {
            $facilityId = $request->get('facility_id');
            
            $result = $this->service->getCurrentVersion($serviceCatalogId, $facilityId);
            
            if (!$result['success']) {
                return response()->json($result, $result['status']);
            }
            
            // Transform data using Resource
            $transformedData = new ServiceVersionResource($result['data']);
            
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $transformedData,
                'status' => $result['status']
            ], $result['status']);
        } catch (\Exception $e) {
            Log::error('Error in ServiceVersionController@getCurrentVersion', [
                'service_catalog_id' => $serviceCatalogId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve current version',
                'data' => null,
                'status' => 500
            ], 500);
        }
    }

    /**
     * Set a version as current.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function setAsCurrentVersion(string $uuid): JsonResponse
    {
        try {
            // First get the service version
            $getResult = $this->service->getServiceVersionByUuid($uuid);
            
            if (!$getResult['success']) {
                return response()->json($getResult, $getResult['status']);
            }
            
            $serviceVersion = $getResult['data'];
            
            // Authorize the action
            $this->authorize('update', $serviceVersion);
            
            $result = $this->service->setAsCurrentVersion($serviceVersion->id);
            
            if (!$result['success']) {
                return response()->json($result, $result['status']);
            }
            
            // Transform data using Resource
            $transformedData = new ServiceVersionResource($result['data']);
            
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $transformedData,
                'status' => $result['status']
            ], $result['status']);
        } catch (\Exception $e) {
            Log::error('Error in ServiceVersionController@setAsCurrentVersion', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to set version as current',
                'data' => null,
                'status' => 500
            ], 500);
        }
    }

    /**
     * Get versions valid on a specific date.
     *
     * @param Request $request
     * @param string $date
     * @return JsonResponse
     */
    public function getValidOnDate(Request $request, string $date): JsonResponse
    {
        try {
            $serviceCatalogId = $request->get('service_catalog_id');
            $facilityId = $request->get('facility_id');
            
            $result = $this->service->getVersionsValidOnDate($date, $serviceCatalogId, $facilityId);
            
            if (!$result['success']) {
                return response()->json($result, $result['status']);
            }
            
            // Transform data using Resource
            $transformedData = ServiceVersionResource::collection($result['data']);
            
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $transformedData,
                'count' => $result['count'],
                'status' => $result['status']
            ], $result['status']);
        } catch (\Exception $e) {
            Log::error('Error in ServiceVersionController@getValidOnDate', [
                'date' => $date,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve versions valid on date',
                'data' => null,
                'status' => 500
            ], 500);
        }
    }

    /**
     * Get price calculation for a version.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function getPriceCalculation(string $uuid): JsonResponse
    {
        try {
            // First get the service version
            $getResult = $this->service->getServiceVersionByUuid($uuid);
            
            if (!$getResult['success']) {
                return response()->json($getResult, $getResult['status']);
            }
            
            $serviceVersion = $getResult['data'];
            
            // Authorize the action (view price calculation)
            $this->authorize('view', $serviceVersion);
            
            $result = $this->service->getPriceCalculation($serviceVersion->id);
            
            return response()->json($result, $result['status']);
        } catch (\Exception $e) {
            Log::error('Error in ServiceVersionController@getPriceCalculation', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate price',
                'data' => null,
                'status' => 500
            ], 500);
        }
    }

    /**
     * Get version history for a service.
     *
     * @param Request $request
     * @param int $serviceCatalogId
     * @return JsonResponse
     */
    public function getVersionHistory(Request $request, int $serviceCatalogId): JsonResponse
    {
        try {
            $facilityId = $request->get('facility_id');
            
            $result = $this->service->getVersionHistory($serviceCatalogId, $facilityId);
            
            if (!$result['success']) {
                return response()->json($result, $result['status']);
            }
            
            // Transform data using Resource
            $transformedData = ServiceVersionResource::collection($result['data']);
            
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $transformedData,
                'count' => $result['count'],
                'status' => $result['status']
            ], $result['status']);
        } catch (\Exception $e) {
            Log::error('Error in ServiceVersionController@getVersionHistory', [
                'service_catalog_id' => $serviceCatalogId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve version history',
                'data' => null,
                'status' => 500
            ], 500);
        }
    }

    /**
     * Check billability for a version.
     *
     * @param Request $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function checkBillability(Request $request, string $uuid): JsonResponse
    {
        try {
            // First get the service version
            $getResult = $this->service->getServiceVersionByUuid($uuid);
            
            if (!$getResult['success']) {
                return response()->json($getResult, $getResult['status']);
            }
            
            $serviceVersion = $getResult['data'];
            
            // Authorize the action (view billability)
            $this->authorize('view', $serviceVersion);
            
            $conditions = $request->only([
                'has_preauthorization',
                'patient_diagnosis_codes',
                'units'
            ]);
            
            $result = $this->service->checkBillability($serviceVersion->id, $conditions);
            
            return response()->json($result, $result['status']);
        } catch (\Exception $e) {
            Log::error('Error in ServiceVersionController@checkBillability', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to check billability',
                'data' => null,
                'status' => 500
            ], 500);
        }
    }

    /**
     * Calculate insurance coverage for a version.
     *
     * @param Request $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function calculateInsuranceCoverage(Request $request, string $uuid): JsonResponse
    {
        try {
            // First get the service version
            $getResult = $this->service->getServiceVersionByUuid($uuid);
            
            if (!$getResult['success']) {
                return response()->json($getResult, $getResult['status']);
            }
            
            $serviceVersion = $getResult['data'];
            
            // Authorize the action (view insurance coverage)
            $this->authorize('view', $serviceVersion);
            
            $insuranceType = $request->get('insurance_type');
            
            if (!$insuranceType) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insurance type is required',
                    'data' => null,
                    'status' => 422
                ], 422);
            }
            
            $result = $this->service->calculateInsuranceCoverage($serviceVersion->id, $insuranceType);
            
            return response()->json($result, $result['status']);
        } catch (\Exception $e) {
            Log::error('Error in ServiceVersionController@calculateInsuranceCoverage', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate insurance coverage',
                'data' => null,
                'status' => 500
            ], 500);
        }
    }
}