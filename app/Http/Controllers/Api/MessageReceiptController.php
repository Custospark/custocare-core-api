<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MessageReceipt\StoreMessageReceiptRequest;
use App\Http\Requests\MessageReceipt\UpdateMessageReceiptRequest;
use App\Http\Resources\MessageReceiptResource;
use App\Services\Contracts\MessageReceiptServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MessageReceiptController extends Controller
{
    /**
     * @var MessageReceiptServiceInterface
     */
    protected MessageReceiptServiceInterface $service;

    /**
     * MessageReceiptController constructor.
     *
     * @param MessageReceiptServiceInterface $service
     */
    public function __construct(MessageReceiptServiceInterface $service)
    {
        $this->service = $service;
    }

    /**
     * Display a listing of message receipts.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);
            $result = $this->service->getAllReceipts($perPage);
            
            if (!$result['success']) {
                return $this->errorResponse($result);
            }
            
            return $this->successResponse(
                MessageReceiptResource::collection($result['data']),
                $result['message'],
                $result['data']->total(),
                $result['data']->currentPage(),
                $result['data']->lastPage()
            );
        } catch (\Exception $e) {
            Log::error('Failed to retrieve message receipts list', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->serverErrorResponse('Unable to retrieve message receipts. Please try again later.');
        }
    }

    /**
     * Store a newly created message receipt.
     *
     * @param StoreMessageReceiptRequest $request
     * @return JsonResponse
     */
    public function store(StoreMessageReceiptRequest $request): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            $result = $this->service->createReceipt($validatedData);
            
            if (!$result['success']) {
                return $this->errorResponse($result);
            }
            
            return $this->createdResponse(
                new MessageReceiptResource($result['data']),
                $result['message']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create message receipt', [
                'data' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->serverErrorResponse('Unable to create message receipt. Please try again later.');
        }
    }

    /**
     * Display the specified message receipt.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $result = $this->service->getReceiptById($id);
            
            if (!$result['success']) {
                return $this->errorResponse($result, 404);
            }
            
            return $this->successResponse(
                new MessageReceiptResource($result['data']),
                $result['message']
            );
        } catch (\Exception $e) {
            Log::error('Failed to retrieve message receipt', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return $this->serverErrorResponse('Unable to retrieve message receipt. Please try again later.');
        }
    }

    /**
     * Update the specified message receipt.
     *
     * @param UpdateMessageReceiptRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(UpdateMessageReceiptRequest $request, int $id): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            $result = $this->service->updateReceipt($id, $validatedData);
            
            if (!$result['success']) {
                return $this->errorResponse($result);
            }
            
            return $this->successResponse(
                new MessageReceiptResource($result['data']),
                $result['message']
            );
        } catch (\Exception $e) {
            Log::error('Failed to update message receipt', [
                'id' => $id,
                'data' => $request->all(),
                'error' => $e->getMessage()
            ]);
            
            return $this->serverErrorResponse('Unable to update message receipt. Please try again later.');
        }
    }

    /**
     * Remove the specified message receipt.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $result = $this->service->deleteReceipt($id);
            
            if (!$result['success']) {
                return $this->errorResponse($result);
            }
            
            return $this->successResponse(
                null,
                $result['message']
            );
        } catch (\Exception $e) {
            Log::error('Failed to delete message receipt', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return $this->serverErrorResponse('Unable to delete message receipt. Please try again later.');
        }
    }

    /**
     * Get receipts for a specific message.
     *
     * @param int $messageId
     * @return JsonResponse
     */
    public function getByMessage(int $messageId): JsonResponse
    {
        try {
            $result = $this->service->getReceiptsByMessage($messageId);
            
            if (!$result['success']) {
                return $this->errorResponse($result);
            }
            
            return $this->successResponse(
                MessageReceiptResource::collection($result['data']),
                $result['message'],
                $result['count']
            );
        } catch (\Exception $e) {
            Log::error('Failed to retrieve receipts by message', [
                'message_id' => $messageId,
                'error' => $e->getMessage()
            ]);
            
            return $this->serverErrorResponse('Unable to retrieve message receipts. Please try again later.');
        }
    }

    /**
     * Get receipts for a specific recipient.
     *
     * @param string $recipientType
     * @param int $recipientId
     * @return JsonResponse
     */
    public function getByRecipient(string $recipientType, int $recipientId): JsonResponse
    {
        try {
            $result = $this->service->getReceiptsByRecipient($recipientType, $recipientId);
            
            if (!$result['success']) {
                return $this->errorResponse($result);
            }
            
            return $this->successResponse(
                MessageReceiptResource::collection($result['data']),
                $result['message'],
                $result['count']
            );
        } catch (\Exception $e) {
            Log::error('Failed to retrieve receipts by recipient', [
                'recipient_type' => $recipientType,
                'recipient_id' => $recipientId,
                'error' => $e->getMessage()
            ]);
            
            return $this->serverErrorResponse('Unable to retrieve recipient receipts. Please try again later.');
        }
    }

    /**
     * Mark a receipt as delivered.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function markAsDelivered(int $id): JsonResponse
    {
        try {
            $result = $this->service->markAsDelivered($id);
            
            if (!$result['success']) {
                return $this->errorResponse($result);
            }
            
            return $this->successResponse(
                new MessageReceiptResource($result['data']),
                $result['message']
            );
        } catch (\Exception $e) {
            Log::error('Failed to mark receipt as delivered', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return $this->serverErrorResponse('Unable to mark receipt as delivered. Please try again later.');
        }
    }

    /**
     * Mark a receipt as read.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function markAsRead(int $id): JsonResponse
    {
        try {
            $result = $this->service->markAsRead($id);
            
            if (!$result['success']) {
                return $this->errorResponse($result);
            }
            
            return $this->successResponse(
                new MessageReceiptResource($result['data']),
                $result['message']
            );
        } catch (\Exception $e) {
            Log::error('Failed to mark receipt as read', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return $this->serverErrorResponse('Unable to mark receipt as read. Please try again later.');
        }
    }

    /**
     * Mark a receipt as acknowledged.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function markAsAcknowledged(int $id): JsonResponse
    {
        try {
            $result = $this->service->markAsAcknowledged($id);
            
            if (!$result['success']) {
                return $this->errorResponse($result);
            }
            
            return $this->successResponse(
                new MessageReceiptResource($result['data']),
                $result['message']
            );
        } catch (\Exception $e) {
            Log::error('Failed to mark receipt as acknowledged', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return $this->serverErrorResponse('Unable to mark receipt as acknowledged. Please try again later.');
        }
    }

    /**
     * Bulk update receipt statuses.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function bulkUpdateStatus(Request $request): JsonResponse
    {
        $request->validate([
            'receipt_ids' => 'required|array|min:1',
            'receipt_ids.*' => 'integer|min:1',
            'status' => 'required|string|in:delivered,read,acknowledged'
        ]);
        
        try {
            $result = $this->service->bulkUpdateStatus(
                $request->input('receipt_ids'),
                $request->input('status')
            );
            
            if (!$result['success']) {
                return $this->errorResponse($result);
            }
            
            return $this->successResponse(
                $result['data'],
                $result['message']
            );
        } catch (\Exception $e) {
            Log::error('Failed to perform bulk status update', [
                'receipt_ids' => $request->input('receipt_ids'),
                'status' => $request->input('status'),
                'error' => $e->getMessage()
            ]);
            
            return $this->serverErrorResponse('Unable to perform bulk update. Please try again later.');
        }
    }

    /**
     * Get unread count for a recipient.
     *
     * @param string $recipientType
     * @param int $recipientId
     * @return JsonResponse
     */
    public function getUnreadCount(string $recipientType, int $recipientId): JsonResponse
    {
        try {
            $result = $this->service->getUnreadCount($recipientType, $recipientId);
            
            if (!$result['success']) {
                return $this->errorResponse($result);
            }
            
            return $this->successResponse(
                $result['data'],
                $result['message']
            );
        } catch (\Exception $e) {
            Log::error('Failed to get unread count', [
                'recipient_type' => $recipientType,
                'recipient_id' => $recipientId,
                'error' => $e->getMessage()
            ]);
            
            return $this->serverErrorResponse('Unable to retrieve unread count. Please try again later.');
        }
    }

    /**
     * Return a standardized success response.
     *
     * @param mixed $data
     * @param string $message
     * @param int|null $total
     * @param int|null $page
     * @param int|null $lastPage
     * @return JsonResponse
     */
    private function successResponse(
        $data = null,
        string $message = 'Operation successful.',
        ?int $total = null,
        ?int $page = null,
        ?int $lastPage = null
    ): JsonResponse {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];
        
        if ($total !== null) {
            $response['meta'] = [
                'total' => $total,
                'page' => $page,
                'last_page' => $lastPage,
            ];
        }
        
        return response()->json($response, 200);
    }

    /**
     * Return a standardized error response.
     *
     * @param array $result
     * @param int $statusCode
     * @return JsonResponse
     */
    private function errorResponse(array $result, int $statusCode = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $result['message'],
            'errors' => $result['errors'] ?? null,
            'error' => $result['error'] ?? null,
            'help' => $result['help'] ?? 'Please check your request and try again.'
        ], $statusCode);
    }

    /**
     * Return a standardized server error response.
     *
     * @param string $message
     * @return JsonResponse
     */
    private function serverErrorResponse(string $message = 'Internal server error.'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error' => config('app.debug') ? 'Server processing error' : null,
            'help' => 'Please try again later or contact support if the problem persists.'
        ], 500);
    }

    /**
     * Return a standardized created response.
     *
     * @param mixed $data
     * @param string $message
     * @return JsonResponse
     */
    private function createdResponse($data = null, string $message = 'Resource created successfully.'): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], 201);
    }
}