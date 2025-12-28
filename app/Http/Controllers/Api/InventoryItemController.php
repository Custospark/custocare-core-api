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
     * @var InventoryItemServiceInterface
     */
    protected $inventoryItemService;

    /**
     * InventoryItemController constructor.
     *
     * @param InventoryItemServiceInterface $inventoryItemService
     */
    public function __construct(InventoryItemServiceInterface $inventoryItemService)
    {
        $this->inventoryItemService = $inventoryItemService;
    }

    /**
     * Display a listing of the inventory items.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['status', 'category', 'is_controlled_substance', 'requires_prescription']);
            $perPage = $request->get('per_page', 15);
            
            $result = $this->inventoryItemService->getAllInventoryItems($filters, $perPage);
            
            if (!$result['success']) {
                return $this->errorResponse($result['message'], $result['error_code'] ?? null);
            }
            
            return $this->successResponse(
                InventoryItemResource::collection($result['data']),
                $result['message'],
                $result['data']->toArray() // Include pagination metadata
            );
        } catch (\Exception $e) {
              Log::error('Inventory items index error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->errorResponse(
                'An unexpected error occurred while retrieving inventory items.',
                'SERVER_ERROR'
            );
        }
    }

    /**
     * Store a newly created inventory item in storage.
     *
     * @param StoreInventoryItemRequest $request
     * @return JsonResponse
     */
    public function store(StoreInventoryItemRequest $request): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            
            $result = $this->inventoryItemService->createInventoryItem($validatedData);
            
            if (!$result['success']) {
                return $this->errorResponse(
                    $result['message'],
                    $result['error_code'] ?? null,
                    $result['errors'] ?? null,
                    422
                );
            }
            
            return $this->successResponse(
                new InventoryItemResource($result['data']),
                $result['message'],
                [],
                201
            );
        } catch (\Exception $e) {
              Log::error('Inventory item store error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->errorResponse(
                'An unexpected error occurred while creating the inventory item.',
                'SERVER_ERROR'
            );
        }
    }

    /**
     * Display the specified inventory item.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function show(string $uuid): JsonResponse
    {
        try {
            $result = $this->inventoryItemService->getInventoryItemByUuid($uuid);
            
            if (!$result['success']) {
                return $this->errorResponse($result['message'], $result['error_code'] ?? null, [], 404);
            }
            
            return $this->successResponse(
                new InventoryItemResource($result['data']),
                $result['message']
            );
        } catch (\Exception $e) {
              Log::error('Inventory item show error', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->errorResponse(
                'An unexpected error occurred while retrieving the inventory item.',
                'SERVER_ERROR'
            );
        }
    }

    /**
     * Update the specified inventory item in storage.
     *
     * @param UpdateInventoryItemRequest $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function update(UpdateInventoryItemRequest $request, string $uuid): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            
            $result = $this->inventoryItemService->updateInventoryItem($uuid, $validatedData);
            
            if (!$result['success']) {
                return $this->errorResponse(
                    $result['message'],
                    $result['error_code'] ?? null,
                    $result['errors'] ?? null,
                    422
                );
            }
            
            return $this->successResponse(
                new InventoryItemResource($result['data']),
                $result['message']
            );
        } catch (\Exception $e) {
              Log::error('Inventory item update error', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->errorResponse(
                'An unexpected error occurred while updating the inventory item.',
                'SERVER_ERROR'
            );
        }
    }

    /**
     * Remove the specified inventory item from storage.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function destroy(string $uuid): JsonResponse
    {
        try {
            $result = $this->inventoryItemService->deleteInventoryItem($uuid);
            
            if (!$result['success']) {
                return $this->errorResponse($result['message'], $result['error_code'] ?? null);
            }
            
            return $this->successResponse(
                null,
                $result['message'],
                [],
                204
            );
        } catch (\Exception $e) {
              Log::error('Inventory item destroy error', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->errorResponse(
                'An unexpected error occurred while deleting the inventory item.',
                'SERVER_ERROR'
            );
        }
    }

    /**
     * Restore the specified soft-deleted inventory item.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function restore(string $uuid): JsonResponse
    {
        try {
            $result = $this->inventoryItemService->restoreInventoryItem($uuid);
            
            if (!$result['success']) {
                return $this->errorResponse($result['message'], $result['error_code'] ?? null);
            }
            
            return $this->successResponse(
                new InventoryItemResource($result['data']),
                $result['message']
            );
        } catch (\Exception $e) {
              Log::error('Inventory item restore error', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->errorResponse(
                'An unexpected error occurred while restoring the inventory item.',
                'SERVER_ERROR'
            );
        }
    }

    /**
     * Get inventory items by category.
     *
     * @param Request $request
     * @param string $category
     * @return JsonResponse
     */
    public function byCategory(Request $request, string $category): JsonResponse
    {
        try {
            $filters = $request->only(['status']);
            
            $result = $this->inventoryItemService->getInventoryItemsByCategory($category, $filters);
            
            if (!$result['success']) {
                return $this->errorResponse($result['message'], $result['error_code'] ?? null);
            }
            
            return $this->successResponse(
                InventoryItemResource::collection($result['data']),
                $result['message']
            );
        } catch (\Exception $e) {
              Log::error('Inventory items by category error', [
                'category' => $category,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->errorResponse(
                'An unexpected error occurred while retrieving inventory items by category.',
                'SERVER_ERROR'
            );
        }
    }

    /**
     * Get controlled substances.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function controlledSubstances(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['status']);
            
            $result = $this->inventoryItemService->getControlledSubstances($filters);
            
            if (!$result['success']) {
                return $this->errorResponse($result['message'], $result['error_code'] ?? null);
            }
            
            return $this->successResponse(
                InventoryItemResource::collection($result['data']),
                $result['message']
            );
        } catch (\Exception $e) {
              Log::error('Controlled substances error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->errorResponse(
                'An unexpected error occurred while retrieving controlled substances.',
                'SERVER_ERROR'
            );
        }
    }

    /**
     * Search inventory items.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $searchTerm = $request->get('q', '');
            $filters = $request->only(['status', 'category']);
            $perPage = $request->get('per_page', 15);
            
            if (empty($searchTerm)) {
                return $this->errorResponse('Search term is required', 'SEARCH_TERM_REQUIRED', [], 400);
            }
            
            $result = $this->inventoryItemService->searchInventoryItems($searchTerm, $filters, $perPage);
            
            if (!$result['success']) {
                return $this->errorResponse($result['message'], $result['error_code'] ?? null);
            }
            
            return $this->successResponse(
                InventoryItemResource::collection($result['data']),
                $result['message'],
                $result['data']->toArray() // Include pagination metadata
            );
        } catch (\Exception $e) {
              Log::error('Inventory items search error', [
                'search_term' => $searchTerm,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->errorResponse(
                'An unexpected error occurred while searching inventory items.',
                'SERVER_ERROR'
            );
        }
    }

    /**
     * Return a successful JSON response.
     *
     * @param mixed $data
     * @param string $message
     * @param array $meta
     * @param int $statusCode
     * @return JsonResponse
     */
    protected function successResponse($data = null, string $message = '', array $meta = [], int $statusCode = 200): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];

        if (!empty($meta)) {
            $response['meta'] = $meta;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Return an error JSON response.
     *
     * @param string $message
     * @param string|null $errorCode
     * @param array|null $errors
     * @param int $statusCode
     * @return JsonResponse
     */
    protected function errorResponse(string $message, ?string $errorCode = null, ?array $errors = null, int $statusCode = 400): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errorCode) {
            $response['error_code'] = $errorCode;
        }

        if ($errors) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }
}