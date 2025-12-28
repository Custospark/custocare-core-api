<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MessageAttachment\StoreMessageAttachmentRequest;
use App\Http\Requests\MessageAttachment\UpdateMessageAttachmentRequest;
use App\Http\Resources\MessageAttachmentResource;
use App\Services\Contracts\MessageAttachmentServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MessageAttachmentController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @param MessageAttachmentServiceInterface $service
     */
    public function __construct(
        private readonly MessageAttachmentServiceInterface $service
    ) {
        // Apply authorization middleware
        $this->authorizeResource(\App\Models\MessageAttachment::class, 'message_attachment');
    }

    /**
     * Display a listing of message attachments.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->input('per_page', 15);
            $result = $this->service->getAllAttachments($perPage);
            
            if (!$result['success']) {
                return response()->json($result, 400);
            }
            
            return response()->json([
                'success' => true,
                'data' => MessageAttachmentResource::collection($result['data']),
                'meta' => [
                    'current_page' => $result['data']->currentPage(),
                    'last_page' => $result['data']->lastPage(),
                    'per_page' => $result['data']->perPage(),
                    'total' => $result['data']->total(),
                ],
                'message' => $result['message'],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to list message attachments', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve message attachments',
                'errors' => ['server' => 'An internal error occurred'],
            ], 500);
        }
    }

    /**
     * Store a newly created message attachment.
     *
     * @param StoreMessageAttachmentRequest $request
     * @return JsonResponse
     */
    public function store(StoreMessageAttachmentRequest $request): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            $result = $this->service->createAttachment($validatedData);
            
            if (!$result['success']) {
                return response()->json($result, 400);
            }
            
            return response()->json([
                'success' => true,
                'data' => new MessageAttachmentResource($result['data']),
                'message' => $result['message'],
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to store message attachment', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create message attachment',
                'errors' => ['server' => 'An internal error occurred'],
            ], 500);
        }
    }

    /**
     * Display the specified message attachment.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $result = $this->service->getAttachmentById($id);
            
            if (!$result['success']) {
                return response()->json($result, 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => new MessageAttachmentResource($result['data']),
                'message' => $result['message'],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to show message attachment', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve message attachment',
                'errors' => ['server' => 'An internal error occurred'],
            ], 500);
        }
    }

    /**
     * Display the specified message attachment by UUID.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function showByUuid(string $uuid): JsonResponse
    {
        try {
            $result = $this->service->getAttachmentByUuid($uuid);
            
            if (!$result['success']) {
                return response()->json($result, 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => new MessageAttachmentResource($result['data']),
                'message' => $result['message'],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to show message attachment by UUID', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve message attachment',
                'errors' => ['server' => 'An internal error occurred'],
            ], 500);
        }
    }

    /**
     * Update the specified message attachment.
     *
     * @param UpdateMessageAttachmentRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(UpdateMessageAttachmentRequest $request, int $id): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            $result = $this->service->updateAttachment($id, $validatedData);
            
            if (!$result['success']) {
                return response()->json($result, 400);
            }
            
            return response()->json([
                'success' => true,
                'data' => new MessageAttachmentResource($result['data']),
                'message' => $result['message'],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update message attachment', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update message attachment',
                'errors' => ['server' => 'An internal error occurred'],
            ], 500);
        }
    }

    /**
     * Remove the specified message attachment.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $result = $this->service->deleteAttachment($id);
            
            if (!$result['success']) {
                return response()->json($result, 400);
            }
            
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result['data'] ?? null,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete message attachment', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete message attachment',
                'errors' => ['server' => 'An internal error occurred'],
            ], 500);
        }
    }

    /**
     * Get attachments for a specific message.
     *
     * @param int $messageId
     * @return JsonResponse
     */
    public function byMessage(int $messageId): JsonResponse
    {
        try {
            $this->authorize('viewAny', \App\Models\MessageAttachment::class);
            
            $result = $this->service->getAttachmentsByMessage($messageId);
            
            if (!$result['success']) {
                return response()->json($result, 400);
            }
            
            return response()->json([
                'success' => true,
                'data' => MessageAttachmentResource::collection($result['data']),
                'meta' => $result['meta'] ?? null,
                'message' => $result['message'],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get message attachments by message', [
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve message attachments',
                'errors' => ['server' => 'An internal error occurred'],
            ], 500);
        }
    }

    /**
     * Get attachments by type.
     *
     * @param string $type
     * @param Request $request
     * @return JsonResponse
     */
    public function byType(string $type, Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', \App\Models\MessageAttachment::class);
            
            $perPage = $request->input('per_page', 15);
            $result = $this->service->getAttachmentsByType($type, $perPage);
            
            if (!$result['success']) {
                return response()->json($result, 400);
            }
            
            return response()->json([
                'success' => true,
                'data' => MessageAttachmentResource::collection($result['data']),
                'meta' => [
                    'current_page' => $result['data']->currentPage(),
                    'last_page' => $result['data']->lastPage(),
                    'per_page' => $result['data']->perPage(),
                    'total' => $result['data']->total(),
                    'type' => $type,
                ],
                'message' => $result['message'],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get message attachments by type', [
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve message attachments by type',
                'errors' => ['server' => 'An internal error occurred'],
            ], 500);
        }
    }

    /**
     * Upload and process a file attachment.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function upload(Request $request): JsonResponse
    {
        try {
            $this->authorize('create', \App\Models\MessageAttachment::class);
            
            $request->validate([
                'file' => 'required|file|max:10240', // 10MB max
                'message_id' => 'required|integer|exists:messages,id',
                'attachment_type' => 'required|string|in:' . implode(',', \App\Models\MessageAttachment::getAttachmentTypes()),
                'contains_phi' => 'boolean',
            ]);
            
            $result = $this->service->processFileUpload(
                $request->file('file'),
                $request->input('message_id'),
                $request->input('attachment_type'),
                $request->input('contains_phi', true)
            );
            
            if (!$result['success']) {
                return response()->json($result, 400);
            }
            
            return response()->json([
                'success' => true,
                'data' => new MessageAttachmentResource($result['data']),
                'message' => $result['message'],
                'file_info' => $result['file_info'] ?? null,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to upload message attachment', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload file',
                'errors' => ['server' => 'An internal error occurred'],
            ], 500);
        }
    }

    /**
     * Get attachment statistics.
     *
     * @return JsonResponse
     */
    public function statistics(): JsonResponse
    {
        try {
            $this->authorize('viewStatistics', \App\Models\MessageAttachment::class);
            
            $result = $this->service->getAttachmentStatistics();
            
            if (!$result['success']) {
                return response()->json($result, 400);
            }
            
            return response()->json([
                'success' => true,
                'data' => $result['data'],
                'message' => $result['message'],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get attachment statistics', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve attachment statistics',
                'errors' => ['server' => 'An internal error occurred'],
            ], 500);
        }
    }
}