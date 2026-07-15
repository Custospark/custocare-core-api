<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InventoryItem\StoreInventoryItemRequest;
use App\Http\Requests\InventoryItem\UpdateInventoryItemRequest;
use App\Http\Resources\InventoryItemResource;
use App\Services\Contracts\InventoryItemServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InventoryItemController extends Controller
{
    /**
     * The inventory item service instance.
     *
     * @var InventoryItemServiceInterface
     */
    protected InventoryItemServiceInterface $service;

    /**
     * Create a new controller instance.
     *
     * @param InventoryItemServiceInterface $service
     */
    public function __construct(InventoryItemServiceInterface $service)
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
                'item_category',
                'is_controlled_substance',
                'requires_prescription',
                'requires_refrigeration',
                'requires_controlled_access',
                'is_hazardous',
                'is_billable'
            ]);

            $perPage = $request->get('per_page', 15);
            $perPage = min(max($perPage, 1), 10000); // Limit between 1 and 10000

            // Get inventory items from service layer (already facility-scoped)
            $result = $this->service->getAllInventoryItems($filters, $perPage);

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            // Transform the data using API resources
            $transformedData = InventoryItemResource::collection($result['data']['items']);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $transformedData,
                'pagination' => $result['data']['pagination']
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve inventory items list', [
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
     * @param StoreInventoryItemRequest $request
     * @return JsonResponse
     */
    public function store(StoreInventoryItemRequest $request): JsonResponse
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

            // Get validated data from request
            $validatedData = $request->validated();

            // Create inventory item through service layer (already facility-scoped)
            $result = $this->service->createInventoryItem($validatedData);

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            // Transform the created inventory item
            $transformedData = new InventoryItemResource($result['data']);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $transformedData
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to create inventory item', [
                'facility_id' => $request->header('X-Facility-Id'),
                'error' => $e->getMessage(),
                'data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while creating the inventory item.',
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

            // Get inventory item from service layer (already facility-scoped)
            $result = $this->service->getInventoryItemByUuid($uuid);

            if (!$result['success']) {
                return response()->json($result, 404);
            }

            // Transform the inventory item
            $transformedData = new InventoryItemResource($result['data']);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $transformedData
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve inventory item', [
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
     * @param UpdateInventoryItemRequest $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function update(UpdateInventoryItemRequest $request, string $uuid): JsonResponse
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

            // Get validated data from request
            $validatedData = $request->validated();

            // Update inventory item through service layer (already facility-scoped)
            $result = $this->service->updateInventoryItem($uuid, $validatedData);

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            // Transform the updated inventory item
            $transformedData = new InventoryItemResource($result['data']);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $transformedData
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update inventory item', [
                'facility_id' => $request->header('X-Facility-Id'),
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while updating the inventory item.',
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

            // Delete inventory item through service layer (already facility-scoped)
            $result = $this->service->deleteInventoryItem($uuid);

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result['data']
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete inventory item', [
                'facility_id' => $request->header('X-Facility-Id'),
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while deleting the inventory item.',
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

            // Restore inventory item through service layer (already facility-scoped)
            $result = $this->service->restoreInventoryItem($uuid);

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            // Transform the restored inventory item
            $transformedData = new InventoryItemResource($result['data']);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $transformedData
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to restore inventory item', [
                'facility_id' => $request->header('X-Facility-Id'),
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while restoring the inventory item.',
                'data' => []
            ], 500);
        }
    }

    /**
     * Get inventory items by category for current facility.
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
                'is_controlled_substance',
                'requires_prescription'
            ]);

            // Get inventory items by category from service layer (already facility-scoped)
            $result = $this->service->getInventoryItemsByCategory($category, $filters);

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            // Transform the data using API resources
            $transformedData = InventoryItemResource::collection($result['data']);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $transformedData
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve inventory items by category', [
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
     * Get controlled substances for current facility.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function controlledSubstances(Request $request): JsonResponse
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
                'item_category',
                'controlled_substance_schedule'
            ]);

            // Get controlled substances from service layer (already facility-scoped)
            $result = $this->service->getControlledSubstances($filters);

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            // Transform the data using API resources
            $transformedData = InventoryItemResource::collection($result['data']);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $transformedData
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve controlled substances', [
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
     * Search inventory items by name or code for current facility.
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
                'item_category',
                'is_controlled_substance',
                'requires_prescription'
            ]);

            // Search inventory items from service layer (already facility-scoped)
            $result = $this->service->searchInventoryItems($searchTerm, $filters);

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            // Transform the data using API resources
            $transformedData = InventoryItemResource::collection($result['data']);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $transformedData
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to search inventory items', [
                'facility_id' => $request->header('X-Facility-Id'),
                'search_term' => $searchTerm,
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
     * Get inventory item by item code for current facility.
     *
     * @param Request $request
     * @param string $itemCode
     * @return JsonResponse
     */
    public function showByCode(Request $request, string $itemCode): JsonResponse
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

            // Get inventory item by code from service layer (already facility-scoped)
            $result = $this->service->getInventoryItemByCode($itemCode);

            if (!$result['success']) {
                return response()->json($result, 404);
            }

            // Transform the inventory item
            $transformedData = new InventoryItemResource($result['data']);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $transformedData
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve inventory item by code', [
                'facility_id' => $request->header('X-Facility-Id'),
                'item_code' => $itemCode,
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
     * Get special handling items for current facility.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function specialHandling(Request $request): JsonResponse
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
                'item_category',
                'is_hazardous',
                'requires_refrigeration',
                'requires_controlled_access'
            ]);

            // Get special handling items from service layer (already facility-scoped)
            $result = $this->service->getSpecialHandlingItems($filters);

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            // Transform the data using API resources
            $transformedData = InventoryItemResource::collection($result['data']);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $transformedData
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve special handling items', [
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
}