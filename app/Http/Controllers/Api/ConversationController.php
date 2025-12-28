<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Conversation\StoreConversationRequest;
use App\Http\Requests\Conversation\UpdateConversationRequest;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\UserResource;
use App\Services\Contracts\ConversationServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ConversationController extends Controller
{
    /**
     * @var ConversationServiceInterface
     */
    private ConversationServiceInterface $conversationService;

    /**
     * ConversationController constructor.
     *
     * @param ConversationServiceInterface $conversationService
     */
    public function __construct(ConversationServiceInterface $conversationService)
    {
        $this->conversationService = $conversationService;
        
        // Apply middleware
        // $this->middleware('auth:sanctum');
    }

    /**
     * Display a listing of conversations.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'facility_id',
                'conversation_type',
                'status',
                'contains_phi',
                'is_emergency',
                'department_code',
                'search'
            ]);

            $perPage = $request->input('per_page', 15);
            $perPage = max(1, min(100, (int) $perPage)); // Limit per_page between 1 and 100

            $result = $this->conversationService->getAllConversations($filters, $perPage);

            if (!$result['success']) {
                return response()->json($result, 500);
            }

            $conversations = $result['data'];
            $conversations->setCollection(
                $conversations->getCollection()->map(function ($conversation) {
                    return new ConversationResource($conversation);
                })
            );

            $responseData = array_merge($result, ['data' => $conversations]);

            return response()->json($responseData);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve conversations list', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'data' => []
            ], 500);
        }
    }

    /**
     * Store a newly created conversation.
     *
     * @param StoreConversationRequest $request
     * @return JsonResponse
     */
    public function store(StoreConversationRequest $request): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            $createdByUserId = Auth::id();

            $result = $this->conversationService->createConversation($validatedData, $createdByUserId);

            if (!$result['success']) {
                $statusCode = isset($result['errors']) ? 422 : 500;
                return response()->json($result, $statusCode);
            }

            // Add initial participants if provided
            if (!empty($validatedData['initial_participants'])) {
                foreach ($validatedData['initial_participants'] as $participant) {
                    $this->conversationService->addParticipant(
                        $result['data']->id,
                        $participant['user_id'],
                        $participant
                    );
                }
                $result['data']->load('participants');
            }

            return response()->json([
                'success' => true,
                'message' => 'Conversation created successfully',
                'data' => new ConversationResource($result['data'])
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create conversation', [
                'user_id' => Auth::id(),
                'data' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create conversation. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'data' => null
            ], 500);
        }
    }

    /**
     * Display the specified conversation.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function show(string $uuid): JsonResponse
    {
        try {
            $result = $this->conversationService->getConversationByUuid($uuid);

            if (!$result['success']) {
                $statusCode = $result['message'] === 'Conversation not found' ? 404 : 500;
                return response()->json($result, $statusCode);
            }

            return response()->json([
                'success' => true,
                'message' => 'Conversation retrieved successfully',
                'data' => new ConversationResource($result['data'])
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve conversation', [
                'user_id' => Auth::id(),
                'conversation_uuid' => $uuid,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve conversation. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'data' => null
            ], 500);
        }
    }

    /**
     * Update the specified conversation.
     *
     * @param UpdateConversationRequest $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function update(UpdateConversationRequest $request, string $uuid): JsonResponse
    {
        try {
            // Get conversation by UUID first
            $conversationResult = $this->conversationService->getConversationByUuid($uuid);
            
            if (!$conversationResult['success']) {
                $statusCode = $conversationResult['message'] === 'Conversation not found' ? 404 : 500;
                return response()->json($conversationResult, $statusCode);
            }

            $conversation = $conversationResult['data'];
            $validatedData = $request->validated();

            $result = $this->conversationService->updateConversation($conversation->id, $validatedData);

            if (!$result['success']) {
                $statusCode = isset($result['errors']) ? 422 : 500;
                return response()->json($result, $statusCode);
            }

            return response()->json([
                'success' => true,
                'message' => 'Conversation updated successfully',
                'data' => new ConversationResource($result['data'])
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update conversation', [
                'user_id' => Auth::id(),
                'conversation_uuid' => $uuid,
                'data' => $request->all(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update conversation. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'data' => null
            ], 500);
        }
    }

    /**
     * Remove the specified conversation.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function destroy(string $uuid): JsonResponse
    {
        try {
            // Get conversation by UUID first
            $conversationResult = $this->conversationService->getConversationByUuid($uuid);
            
            if (!$conversationResult['success']) {
                $statusCode = $conversationResult['message'] === 'Conversation not found' ? 404 : 500;
                return response()->json($conversationResult, $statusCode);
            }

            $conversation = $conversationResult['data'];

            // Check authorization
            if (!Auth::user()->can('delete', $conversation)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to delete this conversation',
                    'data' => null
                ], 403);
            }

            $result = $this->conversationService->deleteConversation($conversation->id);

            if (!$result['success']) {
                return response()->json($result, 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Conversation deleted successfully',
                'data' => null
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete conversation', [
                'user_id' => Auth::id(),
                'conversation_uuid' => $uuid,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete conversation. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'data' => null
            ], 500);
        }
    }

    /**
     * Archive a conversation.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function archive(string $uuid): JsonResponse
    {
        try {
            // Get conversation by UUID first
            $conversationResult = $this->conversationService->getConversationByUuid($uuid);
            
            if (!$conversationResult['success']) {
                $statusCode = $conversationResult['message'] === 'Conversation not found' ? 404 : 500;
                return response()->json($conversationResult, $statusCode);
            }

            $conversation = $conversationResult['data'];

            // Check authorization
            if (!Auth::user()->can('archive', $conversation)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to archive this conversation',
                    'data' => null
                ], 403);
            }

            $result = $this->conversationService->archiveConversation($conversation->id);

            if (!$result['success']) {
                return response()->json($result, 500);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => new ConversationResource($result['data'])
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to archive conversation', [
                'user_id' => Auth::id(),
                'conversation_uuid' => $uuid,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to archive conversation. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'data' => null
            ], 500);
        }
    }

    /**
     * Lock a conversation.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function lock(string $uuid): JsonResponse
    {
        try {
            // Get conversation by UUID first
            $conversationResult = $this->conversationService->getConversationByUuid($uuid);
            
            if (!$conversationResult['success']) {
                $statusCode = $conversationResult['message'] === 'Conversation not found' ? 404 : 500;
                return response()->json($conversationResult, $statusCode);
            }

            $conversation = $conversationResult['data'];

            // Check authorization
            if (!Auth::user()->can('lock', $conversation)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to lock this conversation',
                    'data' => null
                ], 403);
            }

            $result = $this->conversationService->lockConversation($conversation->id);

            if (!$result['success']) {
                return response()->json($result, 500);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => new ConversationResource($result['data'])
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to lock conversation', [
                'user_id' => Auth::id(),
                'conversation_uuid' => $uuid,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to lock conversation. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'data' => null
            ], 500);
        }
    }

    /**
     * Activate a conversation.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function activate(string $uuid): JsonResponse
    {
        try {
            // Get conversation by UUID first
            $conversationResult = $this->conversationService->getConversationByUuid($uuid);
            
            if (!$conversationResult['success']) {
                $statusCode = $conversationResult['message'] === 'Conversation not found' ? 404 : 500;
                return response()->json($conversationResult, $statusCode);
            }

            $conversation = $conversationResult['data'];

            // Check authorization
            if (!Auth::user()->can('activate', $conversation)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to activate this conversation',
                    'data' => null
                ], 403);
            }

            $result = $this->conversationService->activateConversation($conversation->id);

            if (!$result['success']) {
                return response()->json($result, 500);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => new ConversationResource($result['data'])
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to activate conversation', [
                'user_id' => Auth::id(),
                'conversation_uuid' => $uuid,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to activate conversation. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'data' => null
            ], 500);
        }
    }

    /**
     * Mark conversation as emergency.
     *
     * @param Request $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function markAsEmergency(Request $request, string $uuid): JsonResponse
    {
        try {
            $request->validate([
                'emergency' => ['required', 'boolean']
            ]);

            // Get conversation by UUID first
            $conversationResult = $this->conversationService->getConversationByUuid($uuid);
            
            if (!$conversationResult['success']) {
                $statusCode = $conversationResult['message'] === 'Conversation not found' ? 404 : 500;
                return response()->json($conversationResult, $statusCode);
            }

            $conversation = $conversationResult['data'];

            // Check authorization
            if (!Auth::user()->can('markEmergency', $conversation)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to mark this conversation as emergency',
                    'data' => null
                ], 403);
            }

            $emergency = $request->boolean('emergency');
            $result = $this->conversationService->markConversationAsEmergency($conversation->id, $emergency);

            if (!$result['success']) {
                return response()->json($result, 500);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => new ConversationResource($result['data'])
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to mark conversation as emergency', [
                'user_id' => Auth::id(),
                'conversation_uuid' => $uuid,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update emergency status. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'data' => null
            ], 500);
        }
    }

    /**
     * Update PHI status of conversation.
     *
     * @param Request $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function updatePHIStatus(Request $request, string $uuid): JsonResponse
    {
        try {
            $request->validate([
                'contains_phi' => ['required', 'boolean']
            ]);

            // Get conversation by UUID first
            $conversationResult = $this->conversationService->getConversationByUuid($uuid);
            
            if (!$conversationResult['success']) {
                $statusCode = $conversationResult['message'] === 'Conversation not found' ? 404 : 500;
                return response()->json($conversationResult, $statusCode);
            }

            $conversation = $conversationResult['data'];

            // Check authorization
            if (!Auth::user()->can('updatePHI', $conversation)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to update PHI status of this conversation',
                    'data' => null
                ], 403);
            }

            $containsPHI = $request->boolean('contains_phi');
            $result = $this->conversationService->updateConversationPHIStatus($conversation->id, $containsPHI);

            if (!$result['success']) {
                return response()->json($result, 500);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => new ConversationResource($result['data'])
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update PHI status', [
                'user_id' => Auth::id(),
                'conversation_uuid' => $uuid,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update PHI status. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'data' => null
            ], 500);
        }
    }

    /**
     * Get conversation participants.
     *
     * @param string $uuid
     * @return JsonResponse
     */
    public function participants(string $uuid): JsonResponse
    {
        try {
            // Get conversation by UUID first
            $conversationResult = $this->conversationService->getConversationByUuid($uuid);
            
            if (!$conversationResult['success']) {
                $statusCode = $conversationResult['message'] === 'Conversation not found' ? 404 : 500;
                return response()->json($conversationResult, $statusCode);
            }

            $conversation = $conversationResult['data'];

            // Check authorization - user must be participant or have permission
            if (!Auth::user()->can('viewParticipants', $conversation)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to view participants of this conversation',
                    'data' => null
                ], 403);
            }

            $result = $this->conversationService->getConversationParticipants($conversation->id);

            if (!$result['success']) {
                return response()->json($result, 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Participants retrieved successfully',
                'data' => UserResource::collection($result['data'])
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get conversation participants', [
                'user_id' => Auth::id(),
                'conversation_uuid' => $uuid,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve participants. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'data' => null
            ], 500);
        }
    }

    /**
     * Add participant to conversation.
     *
     * @param Request $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function addParticipant(Request $request, string $uuid): JsonResponse
    {
        try {
            $request->validate([
                'user_id' => ['required', 'integer', 'exists:users,id'],
                'role' => ['nullable', 'string', 'max:50'],
                'is_admin' => ['nullable', 'boolean']
            ]);

            // Get conversation by UUID first
            $conversationResult = $this->conversationService->getConversationByUuid($uuid);
            
            if (!$conversationResult['success']) {
                $statusCode = $conversationResult['message'] === 'Conversation not found' ? 404 : 500;
                return response()->json($conversationResult, $statusCode);
            }

            $conversation = $conversationResult['data'];

            // Check authorization
            if (!Auth::user()->can('addParticipant', $conversation)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to add participants to this conversation',
                    'data' => null
                ], 403);
            }

            $participantData = $request->only(['role', 'is_admin']);
            $result = $this->conversationService->addParticipant(
                $conversation->id,
                $request->input('user_id'),
                $participantData
            );

            if (!$result['success']) {
                return response()->json($result, 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Participant added successfully',
                'data' => new ConversationResource($result['data'])
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to add participant to conversation', [
                'user_id' => Auth::id(),
                'conversation_uuid' => $uuid,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to add participant. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'data' => null
            ], 500);
        }
    }

    /**
     * Remove participant from conversation.
     *
     * @param Request $request
     * @param string $uuid
     * @return JsonResponse
     */
    public function removeParticipant(Request $request, string $uuid): JsonResponse
    {
        try {
            $request->validate([
                'user_id' => ['required', 'integer', 'exists:users,id']
            ]);

            // Get conversation by UUID first
            $conversationResult = $this->conversationService->getConversationByUuid($uuid);
            
            if (!$conversationResult['success']) {
                $statusCode = $conversationResult['message'] === 'Conversation not found' ? 404 : 500;
                return response()->json($conversationResult, $statusCode);
            }

            $conversation = $conversationResult['data'];

            // Check authorization
            if (!Auth::user()->can('removeParticipant', $conversation)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to remove participants from this conversation',
                    'data' => null
                ], 403);
            }

            $result = $this->conversationService->removeParticipant(
                $conversation->id,
                $request->input('user_id')
            );

            if (!$result['success']) {
                return response()->json($result, 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Participant removed successfully',
                'data' => new ConversationResource($result['data'])
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to remove participant from conversation', [
                'user_id' => Auth::id(),
                'conversation_uuid' => $uuid,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to remove participant. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'data' => null
            ], 500);
        }
    }
}