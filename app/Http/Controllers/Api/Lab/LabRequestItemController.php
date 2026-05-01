<?php

namespace App\Http\Controllers\Api\Lab;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lab\StoreLabRequestItemRequest;
use App\Http\Requests\Lab\UpdateLabRequestItemRequest;
use App\Http\Requests\Lab\BulkUpdateLabRequestItemsStatusRequest;
use App\Http\Resources\Lab\LabRequestItemResource;
use App\Http\Resources\Lab\LabRequestItemCollection;
use App\Models\Staff;
use App\Services\Lab\Contracts\LabRequestItemServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class LabRequestItemController extends Controller
{
    /**
     * @var LabRequestItemServiceInterface
     */
    protected LabRequestItemServiceInterface $itemService;

    /**
     * Constructor.
     *
     * @param LabRequestItemServiceInterface $itemService
     */
    public function __construct(LabRequestItemServiceInterface $itemService)
    {
        $this->itemService = $itemService;
    }

    /**
     * Display a listing of lab request items.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'lab_request_id', 'lab_test_id', 'status', 'result_flag',
                'has_abnormal_results', 'date_from', 'date_to',
                'order_by', 'order_direction', 'per_page'
            ]);
            
            $perPage = $request->get('per_page', 20);
            $result = $this->itemService->getAllItems($filters, $perPage);
            
            if (!$result['success']) {
                return response()->json($result, JsonResponse::HTTP_BAD_REQUEST);
            }
            
            $items = new LabRequestItemCollection($result['data']['items']);
            $result['data']['items'] = $items;
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve lab request items', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve lab request items',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Store a newly created lab request item.
     *
     * @param StoreLabRequestItemRequest $request
     * @return JsonResponse
     */
    public function store(StoreLabRequestItemRequest $request): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            $result = $this->itemService->createItem($validatedData);
            
            $statusCode = $result['success'] 
                ? JsonResponse::HTTP_CREATED 
                : JsonResponse::HTTP_BAD_REQUEST;
            
            if ($result['success']) {
                $result['data']['item'] = new LabRequestItemResource($result['data']['item']);
            }
            
            return response()->json($result, $statusCode);
        } catch (\Exception $e) {
            Log::error('Failed to create lab request item', [
                'data' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create lab request item',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified lab request item.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function show(string $uuid): JsonResponse
    {
        try {
            $result = $this->itemService->getItemByUuid($uuid);
            
            if (!$result['success']) {
                $statusCode = $result['message'] === 'Lab request item not found' 
                    ? JsonResponse::HTTP_NOT_FOUND 
                    : JsonResponse::HTTP_BAD_REQUEST;
                
                return response()->json($result, $statusCode);
            }
            
            $result['data']['item'] = new LabRequestItemResource($result['data']['item']);
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve lab request item', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve lab request item',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update the specified lab request item.
     *
     * @param UpdateLabRequestItemRequest $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function update(UpdateLabRequestItemRequest $request, string $uuid): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            $result = $this->itemService->updateItem($uuid, $validatedData);
            
            $statusCode = $result['success'] 
                ? JsonResponse::HTTP_OK 
                : ($result['message'] === 'Lab request item not found' 
                    ? JsonResponse::HTTP_NOT_FOUND 
                    : JsonResponse::HTTP_BAD_REQUEST);
            
            if ($result['success']) {
                $result['data']['item'] = new LabRequestItemResource($result['data']['item']);
            }
            
            return response()->json($result, $statusCode);
        } catch (\Exception $e) {
            Log::error('Failed to update lab request item', [
                'uuid' => $uuid,
                'data' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update lab request item',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Remove the specified lab request item.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function destroy(string $uuid): JsonResponse
    {
        try {
            $result = $this->itemService->deleteItem($uuid);
            
            $statusCode = $result['success'] 
                ? JsonResponse::HTTP_OK 
                : ($result['message'] === 'Lab request item not found' 
                    ? JsonResponse::HTTP_NOT_FOUND 
                    : JsonResponse::HTTP_BAD_REQUEST);
            
            return response()->json($result, $statusCode);
        } catch (\Exception $e) {
            Log::error('Failed to delete lab request item', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete lab request item',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update item status.
     *
     * @param Request $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function updateStatus(Request $request, string $uuid): JsonResponse
    {
        try {
            $request->validate([
                'status' => 'required|string|in:pending,sample_collected,in_progress,completed,verified,cancelled'
            ]);
            
            $result = $this->itemService->updateItemStatus($uuid, $request->status);
            
            $statusCode = $result['success'] 
                ? JsonResponse::HTTP_OK 
                : JsonResponse::HTTP_BAD_REQUEST;
            
            if ($result['success']) {
                $result['data']['item'] = new LabRequestItemResource($result['data']['item']);
            }
            
            return response()->json($result, $statusCode);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'data' => []
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            Log::error('Failed to update item status', [
                'uuid' => $uuid,
                'status' => $request->status ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update item status',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Mark sample as collected.
     *
     * @param Request $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function markSampleCollected(Request $request, string $uuid): JsonResponse
    {
        try {
            $request->validate([
                'collected_by_staff_id' => 'required|exists:staff,id',
                'sample_identifier' => 'nullable|string|max:100'
            ]);
            
            $result = $this->itemService->markSampleCollected(
                $uuid,
                $request->collected_by_staff_id ?? Staff::where('user_id',Auth::id())->id,
                $request->sample_identifier
            );
            
            $statusCode = $result['success'] 
                ? JsonResponse::HTTP_OK 
                : JsonResponse::HTTP_BAD_REQUEST;
            
            if ($result['success']) {
                $result['data']['item'] = new LabRequestItemResource($result['data']['item']);
            }
            
            return response()->json($result, $statusCode);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'data' => []
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            Log::error('Failed to mark sample as collected', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark sample as collected',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Mark item as in progress.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function markInProgress(string $uuid): JsonResponse
    {
        try {
            $result = $this->itemService->markItemInProgress($uuid);
            
            $statusCode = $result['success'] 
                ? JsonResponse::HTTP_OK 
                : JsonResponse::HTTP_BAD_REQUEST;
            
            if ($result['success']) {
                $result['data']['item'] = new LabRequestItemResource($result['data']['item']);
            }
            
            return response()->json($result, $statusCode);
        } catch (\Exception $e) {
            Log::error('Failed to mark item as in progress', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark item as in progress',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Mark item as completed.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function markCompleted(string $uuid): JsonResponse
    {
        try {
            $result = $this->itemService->markItemCompleted($uuid);
            
            $statusCode = $result['success'] 
                ? JsonResponse::HTTP_OK 
                : JsonResponse::HTTP_BAD_REQUEST;
            
            if ($result['success']) {
                $result['data']['item'] = new LabRequestItemResource($result['data']['item']);
            }
            
            return response()->json($result, $statusCode);
        } catch (\Exception $e) {
            Log::error('Failed to mark item as completed', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark item as completed',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Mark item as verified.
     *
     * @param Request $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function markVerified(Request $request, string $uuid): JsonResponse
    {
        try {
            $request->validate([
                'verified_by_staff_id' => 'required|exists:staff,id'
            ]);
            
            $result = $this->itemService->markItemVerified($uuid, $request->verified_by_staff_id);
            
            $statusCode = $result['success'] 
                ? JsonResponse::HTTP_OK 
                : JsonResponse::HTTP_BAD_REQUEST;
            
            if ($result['success']) {
                $result['data']['item'] = new LabRequestItemResource($result['data']['item']);
            }
            
            return response()->json($result, $statusCode);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'data' => []
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            Log::error('Failed to mark item as verified', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark item as verified',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Cancel an item.
     *
     * @param Request $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function cancel(Request $request, string $uuid): JsonResponse
    {
        try {
            $request->validate([
                'reason' => 'required|string|max:500',
                'cancelled_by_staff_id' => 'nullable|exists:staff,id'
            ]);
            
            $result = $this->itemService->cancelItem(
                $uuid, 
                $request->reason, 
                $request->cancelled_by_staff_id
            );
            
            $statusCode = $result['success'] 
                ? JsonResponse::HTTP_OK 
                : JsonResponse::HTTP_BAD_REQUEST;
            
            if ($result['success']) {
                $result['data']['item'] = new LabRequestItemResource($result['data']['item']);
            }
            
            return response()->json($result, $statusCode);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'data' => []
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            Log::error('Failed to cancel item', [
                'uuid' => $uuid,
                'reason' => $request->reason ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel item',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get items by lab request.
     *
     * @param Request $request
     * @param string $requestUuid
     * @return JsonResponse
     */
    public function byLabRequest(Request $request, string $requestUuid): JsonResponse
    {
        try {
            $filters = $request->only(['status']);
            $result = $this->itemService->getItemsByLabRequest($requestUuid, $filters);
            
            if (!$result['success']) {
                $statusCode = $result['message'] === 'Lab request not found' 
                    ? JsonResponse::HTTP_NOT_FOUND 
                    : JsonResponse::HTTP_BAD_REQUEST;
                
                return response()->json($result, $statusCode);
            }
            
            $items = LabRequestItemResource::collection($result['data']['items']);
            $result['data']['items'] = $items;
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve items by lab request', [
                'request_uuid' => $requestUuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve items',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get items by lab test.
     *
     * @param Request $request
     * @param string $testUuid
     * @return JsonResponse
     */
    public function byLabTest(Request $request, string $testUuid): JsonResponse
    {
        try {
            $filters = $request->only(['status']);
            $result = $this->itemService->getItemsByLabTest($testUuid, $filters);
            
            if (!$result['success']) {
                $statusCode = $result['message'] === 'Lab test not found' 
                    ? JsonResponse::HTTP_NOT_FOUND 
                    : JsonResponse::HTTP_BAD_REQUEST;
                
                return response()->json($result, $statusCode);
            }
            
            $items = LabRequestItemResource::collection($result['data']['items']);
            $result['data']['items'] = $items;
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve items by lab test', [
                'test_uuid' => $testUuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve items',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get pending items.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function pending(Request $request): JsonResponse
    {
        try {
            $facilityId = $request->get('facility_id');
            $result = $this->itemService->getPendingItems($facilityId);
            
            if (!$result['success']) {
                return response()->json($result, JsonResponse::HTTP_BAD_REQUEST);
            }
            
            $items = LabRequestItemResource::collection($result['data']['items']);
            $result['data']['items'] = $items;
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve pending items', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve pending items',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get items with abnormal results.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function abnormalResults(Request $request): JsonResponse
    {
        try {
            $facilityId = $request->get('facility_id');
            $result = $this->itemService->getItemsWithAbnormalResults($facilityId);
            
            if (!$result['success']) {
                return response()->json($result, JsonResponse::HTTP_BAD_REQUEST);
            }
            
            $items = LabRequestItemResource::collection($result['data']['items']);
            $result['data']['items'] = $items;
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve items with abnormal results', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve items',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get items awaiting verification.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function awaitingVerification(Request $request): JsonResponse
    {
        try {
            $facilityId = $request->get('facility_id');
            $result = $this->itemService->getItemsAwaitingVerification($facilityId);
            
            if (!$result['success']) {
                return response()->json($result, JsonResponse::HTTP_BAD_REQUEST);
            }
            
            $items = LabRequestItemResource::collection($result['data']['items']);
            $result['data']['items'] = $items;
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve items awaiting verification', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve items',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get item with its results.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function withResults(string $uuid): JsonResponse
    {
        try {
            $result = $this->itemService->getItemWithResults($uuid);
            
            if (!$result['success']) {
                $statusCode = $result['message'] === 'Lab request item not found' 
                    ? JsonResponse::HTTP_NOT_FOUND 
                    : JsonResponse::HTTP_BAD_REQUEST;
                
                return response()->json($result, $statusCode);
            }
            
            $result['data']['item'] = new LabRequestItemResource($result['data']['item']);
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve item with results', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve item details',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get item with full details.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function withFullDetails(string $uuid): JsonResponse
    {
        try {
            $result = $this->itemService->getItemWithFullDetails($uuid);
            
            if (!$result['success']) {
                $statusCode = $result['message'] === 'Lab request item not found' 
                    ? JsonResponse::HTTP_NOT_FOUND 
                    : JsonResponse::HTTP_BAD_REQUEST;
                
                return response()->json($result, $statusCode);
            }
            
            $result['data']['item'] = new LabRequestItemResource($result['data']['item']);
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve item with full details', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve item details',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get turnaround time statistics.
     *
     * @param Request $request
     * @param string $testUuid
     * @return JsonResponse
     */
    public function turnaroundTime(Request $request, string $testUuid): JsonResponse
    {
        try {
            $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date'
            ]);
            
            $result = $this->itemService->getTurnaroundTimeStatistics(
                $testUuid,
                $request->start_date,
                $request->end_date
            );
            
            if (!$result['success']) {
                $statusCode = $result['message'] === 'Lab test not found' 
                    ? JsonResponse::HTTP_NOT_FOUND 
                    : JsonResponse::HTTP_BAD_REQUEST;
                
                return response()->json($result, $statusCode);
            }
            
            return response()->json($result);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'data' => []
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve turnaround time statistics', [
                'test_uuid' => $testUuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Bulk update items status.
     *
     * @param BulkUpdateLabRequestItemsStatusRequest $request
     * @return JsonResponse
     */
    public function bulkUpdateStatus(BulkUpdateLabRequestItemsStatusRequest $request): JsonResponse
    {
        try {
            $result = $this->itemService->bulkUpdateItemsStatus(
                $request->item_uuids,
                $request->status
            );
            
            $statusCode = $result['success'] 
                ? JsonResponse::HTTP_OK 
                : JsonResponse::HTTP_BAD_REQUEST;
            
            return response()->json($result, $statusCode);
        } catch (\Exception $e) {
            Log::error('Failed to bulk update items status', [
                'uuids' => $request->item_uuids ?? [],
                'status' => $request->status ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update items',
                'error' => 'An internal server error occurred',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}