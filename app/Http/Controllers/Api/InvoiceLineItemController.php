<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InvoiceLineItem\StoreInvoiceLineItemRequest;
use App\Http\Requests\InvoiceLineItem\UpdateInvoiceLineItemRequest;
use App\Http\Resources\InvoiceLineItemResource;
use App\Services\Contracts\InvoiceLineItemServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InvoiceLineItemController extends Controller
{
    /**
     * Service instance
     */
    protected InvoiceLineItemServiceInterface $service;

    /**
     * Constructor with dependency injection
     */
    public function __construct(InvoiceLineItemServiceInterface $service)
    {
        $this->service = $service;
    }

    /**
     * Display a listing of the invoice line items.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->input('per_page', 15);
            $result = $this->service->getAllInvoiceLineItems($perPage);
            
            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'error' => $result['error'] ?? null,
                ], JsonResponse::HTTP_BAD_REQUEST);
            }
            
            $lineItems = $result['data']['line_items'] ?? [];
            $pagination = $result['data']['pagination'] ?? [];
            
            return InvoiceLineItemResource::collection($lineItems)
                ->additional([
                    'success' => true,
                    'message' => $result['message'],
                    'pagination' => $pagination,
                ])
                ->response()
                ->setStatusCode(JsonResponse::HTTP_OK);
        } catch (\Exception $e) {
            Log::error('Controller error retrieving invoice line items', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve invoice line items',
                'error' => 'An internal server error occurred',
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Store a newly created invoice line item.
     */
    public function store(StoreInvoiceLineItemRequest $request): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            $result = $this->service->createInvoiceLineItem($validatedData);
            
            if (!$result['success']) {
                $statusCode = isset($result['validation_errors']) 
                    ? JsonResponse::HTTP_UNPROCESSABLE_ENTITY 
                    : JsonResponse::HTTP_BAD_REQUEST;
                
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'error' => $result['error'] ?? null,
                    'validation_errors' => $result['validation_errors'] ?? null,
                ], $statusCode);
            }
            
            $lineItem = $result['data']['line_item'] ?? null;
            
            return (new InvoiceLineItemResource($lineItem))
                ->additional([
                    'success' => true,
                    'message' => $result['message'],
                ])
                ->response()
                ->setStatusCode(JsonResponse::HTTP_CREATED);
        } catch (\Exception $e) {
            Log::error('Controller error creating invoice line item', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create invoice line item',
                'error' => 'An internal server error occurred',
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified invoice line item.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $result = $this->service->getInvoiceLineItemById($id);
            
            if (!$result['success']) {
                $statusCode = $result['message'] === 'Invoice line item not found'
                    ? JsonResponse::HTTP_NOT_FOUND
                    : JsonResponse::HTTP_BAD_REQUEST;
                
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'error' => $result['error'] ?? null,
                ], $statusCode);
            }
            
            $lineItem = $result['data']['line_item'] ?? null;
            
            return (new InvoiceLineItemResource($lineItem))
                ->additional([
                    'success' => true,
                    'message' => $result['message'],
                ])
                ->response()
                ->setStatusCode(JsonResponse::HTTP_OK);
        } catch (\Exception $e) {
            Log::error('Controller error retrieving invoice line item', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve invoice line item',
                'error' => 'An internal server error occurred',
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified invoice line item by UUID.
     */
    public function showByUuid(string $uuid): JsonResponse
    {
        try {
            $result = $this->service->getInvoiceLineItemByUuid($uuid);
            
            if (!$result['success']) {
                $statusCode = $result['message'] === 'Invoice line item not found'
                    ? JsonResponse::HTTP_NOT_FOUND
                    : JsonResponse::HTTP_BAD_REQUEST;
                
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'error' => $result['error'] ?? null,
                ], $statusCode);
            }
            
            $lineItem = $result['data']['line_item'] ?? null;
            
            return (new InvoiceLineItemResource($lineItem))
                ->additional([
                    'success' => true,
                    'message' => $result['message'],
                ])
                ->response()
                ->setStatusCode(JsonResponse::HTTP_OK);
        } catch (\Exception $e) {
            Log::error('Controller error retrieving invoice line item by UUID', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve invoice line item',
                'error' => 'An internal server error occurred',
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update the specified invoice line item.
     */
    public function update(UpdateInvoiceLineItemRequest $request, int $id): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            $result = $this->service->updateInvoiceLineItem($id, $validatedData);
            
            if (!$result['success']) {
                $statusCode = $result['message'] === 'Invoice line item not found'
                    ? JsonResponse::HTTP_NOT_FOUND
                    : (isset($result['validation_errors']) 
                        ? JsonResponse::HTTP_UNPROCESSABLE_ENTITY 
                        : JsonResponse::HTTP_BAD_REQUEST);
                
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'error' => $result['error'] ?? null,
                    'validation_errors' => $result['validation_errors'] ?? null,
                ], $statusCode);
            }
            
            $lineItem = $result['data']['line_item'] ?? null;
            
            return (new InvoiceLineItemResource($lineItem))
                ->additional([
                    'success' => true,
                    'message' => $result['message'],
                ])
                ->response()
                ->setStatusCode(JsonResponse::HTTP_OK);
        } catch (\Exception $e) {
            Log::error('Controller error updating invoice line item', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update invoice line item',
                'error' => 'An internal server error occurred',
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Remove the specified invoice line item.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $result = $this->service->deleteInvoiceLineItem($id);
            
            if (!$result['success']) {
                $statusCode = $result['message'] === 'Invoice line item not found'
                    ? JsonResponse::HTTP_NOT_FOUND
                    : JsonResponse::HTTP_BAD_REQUEST;
                
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'error' => $result['error'] ?? null,
                ], $statusCode);
            }
            
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => null,
            ], JsonResponse::HTTP_OK);
        } catch (\Exception $e) {
            Log::error('Controller error deleting invoice line item', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete invoice line item',
                'error' => 'An internal server error occurred',
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get line items by billing cycle.
     */
    public function byBillingCycle(int $billingCycleId): JsonResponse
    {
        try {
            $result = $this->service->getLineItemsByBillingCycle($billingCycleId);
            
            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'error' => $result['error'] ?? null,
                ], JsonResponse::HTTP_BAD_REQUEST);
            }
            
            $lineItems = $result['data']['line_items'] ?? [];
            
            return InvoiceLineItemResource::collection($lineItems)
                ->additional([
                    'success' => true,
                    'message' => $result['message'],
                    'count' => $result['data']['count'] ?? 0,
                ])
                ->response()
                ->setStatusCode(JsonResponse::HTTP_OK);
        } catch (\Exception $e) {
            Log::error('Controller error retrieving line items by billing cycle', [
                'billingCycleId' => $billingCycleId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve line items by billing cycle',
                'error' => 'An internal server error occurred',
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get line items by status.
     */
    public function byStatus(string $status): JsonResponse
    {
        try {
            $result = $this->service->getLineItemsByStatus($status);
            
            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'error' => $result['error'] ?? null,
                ], JsonResponse::HTTP_BAD_REQUEST);
            }
            
            $lineItems = $result['data']['line_items'] ?? [];
            
            return InvoiceLineItemResource::collection($lineItems)
                ->additional([
                    'success' => true,
                    'message' => $result['message'],
                    'count' => $result['data']['count'] ?? 0,
                    'status' => $result['data']['status'] ?? $status,
                ])
                ->response()
                ->setStatusCode(JsonResponse::HTTP_OK);
        } catch (\Exception $e) {
            Log::error('Controller error retrieving line items by status', [
                'status' => $status,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve line items by status',
                'error' => 'An internal server error occurred',
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get line items requiring review.
     */
    public function requiringReview(): JsonResponse
    {
        try {
            $result = $this->service->getLineItemsRequiringReview();
            
            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'error' => $result['error'] ?? null,
                ], JsonResponse::HTTP_BAD_REQUEST);
            }
            
            $lineItems = $result['data']['line_items'] ?? [];
            
            return InvoiceLineItemResource::collection($lineItems)
                ->additional([
                    'success' => true,
                    'message' => $result['message'],
                    'count' => $result['data']['count'] ?? 0,
                ])
                ->response()
                ->setStatusCode(JsonResponse::HTTP_OK);
        } catch (\Exception $e) {
            Log::error('Controller error retrieving line items requiring review', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve line items requiring review',
                'error' => 'An internal server error occurred',
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get line items by date range.
     */
    public function byDateRange(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
            ]);
            
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
            
            $result = $this->service->getLineItemsByDateRange($startDate, $endDate);
            
            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'error' => $result['error'] ?? null,
                ], JsonResponse::HTTP_BAD_REQUEST);
            }
            
            $lineItems = $result['data']['line_items'] ?? [];
            
            return InvoiceLineItemResource::collection($lineItems)
                ->additional([
                    'success' => true,
                    'message' => $result['message'],
                    'count' => $result['data']['count'] ?? 0,
                    'date_range' => $result['data']['date_range'] ?? [
                        'start_date' => $startDate,
                        'end_date' => $endDate
                    ],
                ])
                ->response()
                ->setStatusCode(JsonResponse::HTTP_OK);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            Log::error('Controller error retrieving line items by date range', [
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve line items by date range',
                'error' => 'An internal server error occurred',
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update line item status.
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        try {
            $request->validate([
                'status' => 'required|string|in:pending,approved,billed,paid,denied,adjusted,written_off',
                'reason' => 'nullable|string|max:1000',
            ]);
            
            $status = $request->input('status');
            $reason = $request->input('reason');
            
            $result = $this->service->updateLineItemStatus($id, $status, $reason);
            
            if (!$result['success']) {
                $statusCode = $result['message'] === 'Invoice line item not found'
                    ? JsonResponse::HTTP_NOT_FOUND
                    : JsonResponse::HTTP_BAD_REQUEST;
                
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'error' => $result['error'] ?? null,
                ], $statusCode);
            }
            
            $lineItem = $result['data']['line_item'] ?? null;
            
            return (new InvoiceLineItemResource($lineItem))
                ->additional([
                    'success' => true,
                    'message' => $result['message'],
                ])
                ->response()
                ->setStatusCode(JsonResponse::HTTP_OK);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            Log::error('Controller error updating line item status', [
                'id' => $id,
                'status' => $request->input('status'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update line item status',
                'error' => 'An internal server error occurred',
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Mark line item as reviewed.
     */
    public function markAsReviewed(Request $request, int $id): JsonResponse
    {
        try {
            $request->validate([
                'reviewer_id' => 'required|integer|exists:staff,id',
            ]);
            
            $reviewerId = $request->input('reviewer_id');
            
            $result = $this->service->markLineItemAsReviewed($id, $reviewerId);
            
            if (!$result['success']) {
                $statusCode = $result['message'] === 'Invoice line item not found'
                    ? JsonResponse::HTTP_NOT_FOUND
                    : JsonResponse::HTTP_BAD_REQUEST;
                
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'error' => $result['error'] ?? null,
                ], $statusCode);
            }
            
            $lineItem = $result['data']['line_item'] ?? null;
            
            return (new InvoiceLineItemResource($lineItem))
                ->additional([
                    'success' => true,
                    'message' => $result['message'],
                ])
                ->response()
                ->setStatusCode(JsonResponse::HTTP_OK);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            Log::error('Controller error marking line item as reviewed', [
                'id' => $id,
                'reviewer_id' => $request->input('reviewer_id'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark line item as reviewed',
                'error' => 'An internal server error occurred',
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Calculate totals for billing cycle.
     */
    public function billingCycleTotals(int $billingCycleId): JsonResponse
    {
        try {
            $result = $this->service->calculateBillingCycleTotals($billingCycleId);
            
            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'error' => $result['error'] ?? null,
                ], JsonResponse::HTTP_BAD_REQUEST);
            }
            
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result['data'],
            ], JsonResponse::HTTP_OK);
        } catch (\Exception $e) {
            Log::error('Controller error calculating billing cycle totals', [
                'billingCycleId' => $billingCycleId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate billing cycle totals',
                'error' => 'An internal server error occurred',
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Verify audit trail integrity.
     */
    public function verifyAuditTrail(int $id): JsonResponse
    {
        try {
            $result = $this->service->verifyAuditTrail($id);
            
            if (!$result['success']) {
                $statusCode = $result['message'] === 'Invoice line item not found'
                    ? JsonResponse::HTTP_NOT_FOUND
                    : JsonResponse::HTTP_BAD_REQUEST;
                
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'error' => $result['error'] ?? null,
                ], $statusCode);
            }
            
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result['data'],
            ], JsonResponse::HTTP_OK);
        } catch (\Exception $e) {
            Log::error('Controller error verifying audit trail', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify audit trail',
                'error' => 'An internal server error occurred',
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Validate line item for billing.
     */
    public function validateForBilling(int $id): JsonResponse
    {
        try {
            $result = $this->service->validateLineItemForBilling($id);
            
            if (!$result['success']) {
                $statusCode = $result['message'] === 'Invoice line item not found'
                    ? JsonResponse::HTTP_NOT_FOUND
                    : JsonResponse::HTTP_BAD_REQUEST;
                
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'error' => $result['error'] ?? null,
                ], $statusCode);
            }
            
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result['data'],
            ], JsonResponse::HTTP_OK);
        } catch (\Exception $e) {
            Log::error('Controller error validating line item for billing', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to validate line item for billing',
                'error' => 'An internal server error occurred',
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Batch update line items status.
     */
    public function batchUpdateStatus(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'integer|exists:invoice_line_items,id',
                'status' => 'required|string|in:pending,approved,billed,paid,denied,adjusted,written_off',
                'reason' => 'nullable|string|max:1000',
            ]);
            
            $ids = $request->input('ids');
            $status = $request->input('status');
            $reason = $request->input('reason');
            
            $result = $this->service->batchUpdateStatus($ids, $status, $reason);
            
            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'error' => $result['error'] ?? null,
                ], JsonResponse::HTTP_BAD_REQUEST);
            }
            
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result['data'],
                'summary' => $result['summary'],
            ], JsonResponse::HTTP_OK);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            Log::error('Controller error in batch status update', [
                'ids' => $request->input('ids'),
                'status' => $request->input('status'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to batch update status',
                'error' => 'An internal server error occurred',
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}