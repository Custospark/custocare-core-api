<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConversationParticipant\StoreConversationParticipantRequest;
use App\Http\Requests\ConversationParticipant\UpdateConversationParticipantRequest;
use App\Http\Resources\ConversationParticipantResource;
use App\Services\Contracts\ConversationParticipantServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class ConversationParticipantController extends Controller
{
    /**
     * @var ConversationParticipantServiceInterface
     */
    protected ConversationParticipantServiceInterface $service;

    /**
     * Constructor.
     *
     * @param ConversationParticipantServiceInterface $service
     */
    public function __construct(ConversationParticipantServiceInterface $service)
    {
        $this->service = $service;
    }

    /**
     * Display a listing of conversation participants.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'conversation_id',
                'participant_type',
                'participant_id',
                'role',
                'is_muted',
                'active_only'
            ]);

            $participants = $this->service->getPaginatedParticipants($filters);

            return response()->json([
                'success' => true,
                'data' => ConversationParticipantResource::collection($participants),
                'meta' => [
                    'total' => $participants->total(),
                    'per_page' => $participants->perPage(),
                    'current_page' => $participants->currentPage(),
                    'last_page' => $participants->lastPage(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve conversation participants', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve conversation participants.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Store a newly created conversation participant.
     *
     * @param StoreConversationParticipantRequest $request
     * @return JsonResponse
     */
    public function store(StoreConversationParticipantRequest $request): JsonResponse
    {
        try {
            $participant = $this->service->addParticipant($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Conversation participant added successfully.',
                'data' => new ConversationParticipantResource($participant)
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to add conversation participant', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $statusCode = $e->getMessage() === 'Participant already exists in conversation' ? 409 : 500;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], $statusCode);
        }
    }

    /**
     * Display the specified conversation participant.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $participant = $this->service->getParticipantById($id);

            if (!$participant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conversation participant not found.'
                ], 404);
            }

            // Check authorization
            if (!auth::user()->can('view', $participant)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to view this conversation participant.'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => new ConversationParticipantResource($participant)
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve conversation participant', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve conversation participant.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update the specified conversation participant.
     *
     * @param UpdateConversationParticipantRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(UpdateConversationParticipantRequest $request, int $id): JsonResponse
    {
        try {
            $participant = $this->service->updateParticipant($id, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Conversation participant updated successfully.',
                'data' => new ConversationParticipantResource($participant)
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update conversation participant', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $statusCode = $e->getMessage() === 'Conversation participant not found' ? 404 : 500;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], $statusCode);
        }
    }

    /**
     * Remove the specified conversation participant.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $result = $this->service->removeParticipant($id);

            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to remove conversation participant.'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Conversation participant removed successfully.'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to remove conversation participant', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $statusCode = match ($e->getMessage()) {
                'Conversation participant not found' => 404,
                'Cannot remove conversation owner' => 403,
                default => 500,
            };

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], $statusCode);
        }
    }

    /**
     * Mark participant as left the conversation.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function leave(int $id): JsonResponse
    {
        try {
            // Check authorization
            $participant = $this->service->getParticipantById($id);
            if ($participant && !auth::user()->can('leave', $participant)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to leave this conversation.'
                ], 403);
            }

            $result = $this->service->leaveConversation($id);

            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to leave conversation.'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Successfully left the conversation.'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to mark participant as left', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $statusCode = match ($e->getMessage()) {
                'Conversation participant not found' => 404,
                'Participant has already left the conversation' => 400,
                'Conversation owner cannot leave, transfer ownership first' => 403,
                default => 500,
            };

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], $statusCode);
        }
    }

    /**
     * Mute a participant in conversation.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function mute(int $id): JsonResponse
    {
        try {
            // Check authorization
            $participant = $this->service->getParticipantById($id);
            if ($participant && !auth::user()->can('mute', $participant)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to mute this participant.'
                ], 403);
            }

            $result = $this->service->muteParticipant($id);

            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to mute participant.'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Participant muted successfully.'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to mute participant', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $statusCode = match ($e->getMessage()) {
                'Conversation participant not found' => 404,
                'Participant is already muted' => 400,
                default => 500,
            };

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], $statusCode);
        }
    }

    /**
     * Unmute a participant in conversation.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function unmute(int $id): JsonResponse
    {
        try {
            // Check authorization
            $participant = $this->service->getParticipantById($id);
            if ($participant && !auth::user()->can('mute', $participant)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to unmute this participant.'
                ], 403);
            }

            $result = $this->service->unmuteParticipant($id);

            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to unmute participant.'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Participant unmuted successfully.'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to unmute participant', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $statusCode = match ($e->getMessage()) {
                'Conversation participant not found' => 404,
                'Participant is not muted' => 400,
                default => 500,
            };

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], $statusCode);
        }
    }

    /**
     * Update participant role.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function updateRole(Request $request, int $id): JsonResponse
    {
        try {
            $request->validate([
                'role' => 'required|string|in:owner,moderator,member,read_only'
            ]);

            // Check authorization
            $participant = $this->service->getParticipantById($id);
            if ($participant && !auth::user()->can('changeRole', $participant)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to change participant role.'
                ], 403);
            }

            $result = $this->service->updateParticipantRole($id, $request->input('role'));

            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update participant role.'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Participant role updated successfully.'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update participant role', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $statusCode = match ($e->getMessage()) {
                'Conversation participant not found' => 404,
                'Invalid participant role' => 400,
                'Cannot change owner role, transfer ownership first' => 403,
                default => 500,
            };

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], $statusCode);
        }
    }
}