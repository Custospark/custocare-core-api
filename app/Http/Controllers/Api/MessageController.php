<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Message\StoreMessageRequest;
use App\Http\Requests\Message\UpdateMessageRequest;
use App\Http\Resources\MessageResource;
use App\Services\Contracts\MessageServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MessageController extends Controller
{
    /**
     * The message service instance.
     */
    private MessageServiceInterface $messageService;

    /**
     * Create a new controller instance.
     */
    public function __construct(MessageServiceInterface $messageService)
    {
        $this->messageService = $messageService;
    }

    /**
     * Display a listing of messages.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);
            $messages = $this->messageService->getMessages($perPage);
            
            return response()->json([
                'success' => true,
                'data' => MessageResource::collection($messages),
                'meta' => [
                    'current_page' => $messages->currentPage(),
                    'last_page' => $messages->lastPage(),
                    'per_page' => $messages->perPage(),
                    'total' => $messages->total(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('MessageController: Failed to retrieve messages', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve messages. Please try again later.',
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display messages for a specific conversation.
     */
    public function conversationMessages(Request $request, int $conversationId): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 20);
            $messages = $this->messageService->getConversationMessages($conversationId, $perPage);
            
            return response()->json([
                'success' => true,
                'data' => MessageResource::collection($messages),
                'meta' => [
                    'current_page' => $messages->currentPage(),
                    'last_page' => $messages->lastPage(),
                    'per_page' => $messages->perPage(),
                    'total' => $messages->total(),
                    'conversation_id' => $conversationId,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('MessageController: Failed to retrieve conversation messages', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve conversation messages. Please try again later.',
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Store a newly created message.
     */
    public function store(StoreMessageRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $message = $this->messageService->createMessage($validated);
            
            return response()->json([
                'success' => true,
                'message' => 'Message created successfully.',
                'data' => new MessageResource($message),
            ], JsonResponse::HTTP_CREATED);
        } catch (\RuntimeException $e) {
            Log::error('MessageController: Failed to create message', [
                'data' => $request->except(['content']), // Exclude sensitive content
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], JsonResponse::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            Log::error('MessageController: Unexpected error creating message', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while creating the message.',
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified message.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $message = $this->messageService->getMessage($id);
            
            if (!$message) {
                return response()->json([
                    'success' => false,
                    'message' => 'Message not found.',
                ], JsonResponse::HTTP_NOT_FOUND);
            }
            
            return response()->json([
                'success' => true,
                'data' => new MessageResource($message),
            ]);
        } catch (\Exception $e) {
            Log::error('MessageController: Failed to retrieve message', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve message. Please try again later.',
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified message by UUID.
     */
    public function showByUuid(string $uuid): JsonResponse
    {
        try {
            $message = $this->messageService->getMessageByUuid($uuid);
            
            if (!$message) {
                return response()->json([
                    'success' => false,
                    'message' => 'Message not found.',
                ], JsonResponse::HTTP_NOT_FOUND);
            }
            
            return response()->json([
                'success' => true,
                'data' => new MessageResource($message),
            ]);
        } catch (\Exception $e) {
            Log::error('MessageController: Failed to retrieve message by UUID', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve message. Please try again later.',
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update the specified message.
     */
    public function update(UpdateMessageRequest $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validated();
            $message = $this->messageService->updateMessage($id, $validated);
            
            if (!$message) {
                return response()->json([
                    'success' => false,
                    'message' => 'Message not found or could not be updated.',
                ], JsonResponse::HTTP_NOT_FOUND);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Message updated successfully.',
                'data' => new MessageResource($message),
            ]);
        } catch (\RuntimeException $e) {
            Log::error('MessageController: Failed to update message', [
                'id' => $id,
                'data' => $request->except(['content']), // Exclude sensitive content
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], JsonResponse::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            Log::error('MessageController: Unexpected error updating message', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while updating the message.',
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Remove the specified message.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $deleted = $this->messageService->deleteMessage($id);
            
            if (!$deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Message not found or could not be deleted.',
                ], JsonResponse::HTTP_NOT_FOUND);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Message deleted successfully.',
            ]);
        } catch (\RuntimeException $e) {
            Log::error('MessageController: Failed to delete message', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], JsonResponse::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            Log::error('MessageController: Unexpected error deleting message', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while deleting the message.',
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Restore a soft-deleted message.
     */
    public function restore(int $id): JsonResponse
    {
        try {
            $restored = $this->messageService->restoreMessage($id);
            
            if (!$restored) {
                return response()->json([
                    'success' => false,
                    'message' => 'Message not found or could not be restored.',
                ], JsonResponse::HTTP_NOT_FOUND);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Message restored successfully.',
            ]);
        } catch (\RuntimeException $e) {
            Log::error('MessageController: Failed to restore message', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], JsonResponse::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            Log::error('MessageController: Unexpected error restoring message', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while restoring the message.',
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Mark a message as delivered.
     */
    public function markAsDelivered(int $id): JsonResponse
    {
        try {
            $updated = $this->messageService->markAsDelivered($id);
            
            if (!$updated) {
                return response()->json([
                    'success' => false,
                    'message' => 'Message not found or could not be marked as delivered.',
                ], JsonResponse::HTTP_NOT_FOUND);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Message marked as delivered.',
            ]);
        } catch (\Exception $e) {
            Log::error('MessageController: Failed to mark message as delivered', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update message status. Please try again later.',
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Mark a message as sent.
     */
    public function markAsSent(int $id): JsonResponse
    {
        try {
            $updated = $this->messageService->markAsSent($id);
            
            if (!$updated) {
                return response()->json([
                    'success' => false,
                    'message' => 'Message not found or could not be marked as sent.',
                ], JsonResponse::HTTP_NOT_FOUND);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Message marked as sent.',
            ]);
        } catch (\Exception $e) {
            Log::error('MessageController: Failed to mark message as sent', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update message status. Please try again later.',
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Acknowledge a message.
     */
    public function acknowledge(int $id): JsonResponse
    {
        try {
            $acknowledged = $this->messageService->acknowledgeMessage($id);
            
            if (!$acknowledged) {
                return response()->json([
                    'success' => false,
                    'message' => 'Message not found or does not require acknowledgement.',
                ], JsonResponse::HTTP_NOT_FOUND);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Message acknowledged successfully.',
            ]);
        } catch (\Exception $e) {
            Log::error('MessageController: Failed to acknowledge message', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to acknowledge message. Please try again later.',
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get clinical messages.
     */
    public function clinicalMessages(Request $request): JsonResponse
    {
        try {
            $conversationId = $request->get('conversation_id');
            $messages = $this->messageService->getClinicalMessages($conversationId);
            
            return response()->json([
                'success' => true,
                'data' => MessageResource::collection($messages),
                'meta' => [
                    'count' => $messages->count(),
                    'conversation_id' => $conversationId,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('MessageController: Failed to retrieve clinical messages', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve clinical messages. Please try again later.',
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}