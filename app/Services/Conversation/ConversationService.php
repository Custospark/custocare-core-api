<?php

namespace App\Services\Conversation;

use App\Models\Conversation;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Services\Contracts\ConversationServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ConversationService implements ConversationServiceInterface
{
    /**
     * @var ConversationRepositoryInterface
     */
    private ConversationRepositoryInterface $conversationRepository;

    /**
     * ConversationService constructor.
     *
     * @param ConversationRepositoryInterface $conversationRepository
     */
    public function __construct(ConversationRepositoryInterface $conversationRepository)
    {
        $this->conversationRepository = $conversationRepository;
    }

    /**
     * Get all conversations with pagination.
     *
     * @param array $filters
     * @param int $perPage
     * @return array
     */
    public function getAllConversations(array $filters = [], int $perPage = 15): array
    {
        try {
            $conversations = $this->conversationRepository->getAllPaginated($filters, $perPage);

            return [
                'success' => true,
                'data' => $conversations,
                'message' => 'Conversations retrieved successfully',
                'meta' => [
                    'total' => $conversations->total(),
                    'per_page' => $conversations->perPage(),
                    'current_page' => $conversations->currentPage(),
                    'last_page' => $conversations->lastPage(),
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve conversations', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve conversations. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'data' => []
            ];
        }
    }

    /**
     * Get conversation by ID.
     *
     * @param int $id
     * @return array
     */
    public function getConversationById(int $id): array
    {
        try {
            $conversation = $this->conversationRepository->findById($id);

            if (!$conversation) {
                return [
                    'success' => false,
                    'message' => 'Conversation not found',
                    'data' => null
                ];
            }

            return [
                'success' => true,
                'data' => $conversation,
                'message' => 'Conversation retrieved successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve conversation by ID', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve conversation. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'data' => null
            ];
        }
    }

    /**
     * Get conversation by UUID.
     *
     * @param string $uuid
     * @return array
     */
    public function getConversationByUuid(string $uuid): array
    {
        try {
            $conversation = $this->conversationRepository->findByUuid($uuid);

            if (!$conversation) {
                return [
                    'success' => false,
                    'message' => 'Conversation not found',
                    'data' => null
                ];
            }

            return [
                'success' => true,
                'data' => $conversation,
                'message' => 'Conversation retrieved successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve conversation by UUID', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve conversation. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'data' => null
            ];
        }
    }

    /**
     * Create a new conversation.
     *
     * @param array $data
     * @param int $createdByUserId
     * @return array
     */
    public function createConversation(array $data, int $createdByUserId): array
    {
        try {
            DB::beginTransaction();

            // Generate UUID if not provided
            if (empty($data['conversation_uuid'])) {
                $data['conversation_uuid'] = Conversation::generateUuid();
            }

            // Set created by user
            $data['created_by_user_id'] = $createdByUserId;

            // Validate conversation data
            $validationResult = $this->validateConversationData($data);
            if (!$validationResult['success']) {
                return $validationResult;
            }

            // Create conversation
            $conversation = $this->conversationRepository->create($data);

            // Add creator as participant if it's a group or direct conversation
            if (in_array($conversation->conversation_type, ['direct', 'group', 'care_context'])) {
                $this->conversationRepository->addParticipant($conversation, $createdByUserId, [
                    'role' => 'admin',
                    'is_admin' => true
                ]);
            }

            DB::commit();

            return [
                'success' => true,
                'data' => $conversation,
                'message' => 'Conversation created successfully'
            ];
        } catch (ValidationException $e) {
            DB::rollBack();
            
            return [
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'data' => null
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create conversation', [
                'data' => $data,
                'created_by' => $createdByUserId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to create conversation. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'data' => null
            ];
        }
    }

    /**
     * Update an existing conversation.
     *
     * @param int $id
     * @param array $data
     * @return array
     */
    public function updateConversation(int $id, array $data): array
    {
        try {
            DB::beginTransaction();

            $conversation = $this->conversationRepository->findById($id);

            if (!$conversation) {
                return [
                    'success' => false,
                    'message' => 'Conversation not found',
                    'data' => null
                ];
            }

            // Validate update data
            $validationResult = $this->validateConversationData($data, true);
            if (!$validationResult['success']) {
                return $validationResult;
            }

            // Don't allow updating conversation_type if conversation has messages
            if (isset($data['conversation_type']) && $data['conversation_type'] !== $conversation->conversation_type) {
                if ($conversation->messages()->count() > 0) {
                    return [
                        'success' => false,
                        'message' => 'Cannot change conversation type after messages have been sent',
                        'data' => null
                    ];
                }
            }

            $updated = $this->conversationRepository->update($conversation, $data);

            if (!$updated) {
                throw new \RuntimeException('Failed to update conversation');
            }

            // Refresh conversation data
            $conversation->refresh();

            DB::commit();

            return [
                'success' => true,
                'data' => $conversation,
                'message' => 'Conversation updated successfully'
            ];
        } catch (ValidationException $e) {
            DB::rollBack();
            
            return [
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'data' => null
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update conversation', [
                'id' => $id,
                'data' => $data,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to update conversation. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'data' => null
            ];
        }
    }

    /**
     * Delete a conversation.
     *
     * @param int $id
     * @return array
     */
    public function deleteConversation(int $id): array
    {
        try {
            $conversation = $this->conversationRepository->findById($id);

            if (!$conversation) {
                return [
                    'success' => false,
                    'message' => 'Conversation not found',
                    'data' => null
                ];
            }

            // Check if conversation can be deleted
            if ($conversation->isLocked()) {
                return [
                    'success' => false,
                    'message' => 'Cannot delete a locked conversation',
                    'data' => null
                ];
            }

            $deleted = $this->conversationRepository->delete($conversation);

            if (!$deleted) {
                throw new \RuntimeException('Failed to delete conversation');
            }

            return [
                'success' => true,
                'message' => 'Conversation deleted successfully',
                'data' => null
            ];
        } catch (\Exception $e) {
            Log::error('Failed to delete conversation', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to delete conversation. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'data' => null
            ];
        }
    }

    /**
     * Archive a conversation.
     *
     * @param int $id
     * @return array
     */
    public function archiveConversation(int $id): array
    {
        try {
            $conversation = $this->conversationRepository->findById($id);

            if (!$conversation) {
                return [
                    'success' => false,
                    'message' => 'Conversation not found',
                    'data' => null
                ];
            }

            if ($conversation->isArchived()) {
                return [
                    'success' => true,
                    'message' => 'Conversation is already archived',
                    'data' => $conversation
                ];
            }

            $archived = $this->conversationRepository->archive($conversation);

            if (!$archived) {
                throw new \RuntimeException('Failed to archive conversation');
            }

            $conversation->refresh();

            return [
                'success' => true,
                'data' => $conversation,
                'message' => 'Conversation archived successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to archive conversation', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to archive conversation. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'data' => null
            ];
        }
    }

    /**
     * Lock a conversation.
     *
     * @param int $id
     * @return array
     */
    public function lockConversation(int $id): array
    {
        try {
            $conversation = $this->conversationRepository->findById($id);

            if (!$conversation) {
                return [
                    'success' => false,
                    'message' => 'Conversation not found',
                    'data' => null
                ];
            }

            if ($conversation->isLocked()) {
                return [
                    'success' => true,
                    'message' => 'Conversation is already locked',
                    'data' => $conversation
                ];
            }

            $locked = $this->conversationRepository->lock($conversation);

            if (!$locked) {
                throw new \RuntimeException('Failed to lock conversation');
            }

            $conversation->refresh();

            return [
                'success' => true,
                'data' => $conversation,
                'message' => 'Conversation locked successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to lock conversation', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to lock conversation. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'data' => null
            ];
        }
    }

    /**
     * Activate a conversation.
     *
     * @param int $id
     * @return array
     */
    public function activateConversation(int $id): array
    {
        try {
            $conversation = $this->conversationRepository->findById($id);

            if (!$conversation) {
                return [
                    'success' => false,
                    'message' => 'Conversation not found',
                    'data' => null
                ];
            }

            if ($conversation->isActive()) {
                return [
                    'success' => true,
                    'message' => 'Conversation is already active',
                    'data' => $conversation
                ];
            }

            $activated = $this->conversationRepository->activate($conversation);

            if (!$activated) {
                throw new \RuntimeException('Failed to activate conversation');
            }

            $conversation->refresh();

            return [
                'success' => true,
                'data' => $conversation,
                'message' => 'Conversation activated successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to activate conversation', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to activate conversation. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'data' => null
            ];
        }
    }

    /**
     * Mark conversation as emergency.
     *
     * @param int $id
     * @param bool $emergency
     * @return array
     */
    public function markConversationAsEmergency(int $id, bool $emergency = true): array
    {
        try {
            $conversation = $this->conversationRepository->findById($id);

            if (!$conversation) {
                return [
                    'success' => false,
                    'message' => 'Conversation not found',
                    'data' => null
                ];
            }

            if ($conversation->isEmergency() === $emergency) {
                $message = $emergency ? 'Conversation is already marked as emergency' : 'Conversation is already not marked as emergency';
                return [
                    'success' => true,
                    'message' => $message,
                    'data' => $conversation
                ];
            }

            $marked = $this->conversationRepository->markAsEmergency($conversation, $emergency);

            if (!$marked) {
                throw new \RuntimeException('Failed to mark conversation as emergency');
            }

            $conversation->refresh();

            $message = $emergency ? 'Conversation marked as emergency' : 'Emergency status removed from conversation';

            return [
                'success' => true,
                'data' => $conversation,
                'message' => $message
            ];
        } catch (\Exception $e) {
            Log::error('Failed to mark conversation as emergency', [
                'id' => $id,
                'emergency' => $emergency,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to update emergency status. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'data' => null
            ];
        }
    }

    /**
     * Update PHI status of conversation.
     *
     * @param int $id
     * @param bool $containsPHI
     * @return array
     */
    public function updateConversationPHIStatus(int $id, bool $containsPHI): array
    {
        try {
            $conversation = $this->conversationRepository->findById($id);

            if (!$conversation) {
                return [
                    'success' => false,
                    'message' => 'Conversation not found',
                    'data' => null
                ];
            }

            if ($conversation->hasPHI() === $containsPHI) {
                $message = $containsPHI ? 'Conversation already contains PHI' : 'Conversation already does not contain PHI';
                return [
                    'success' => true,
                    'message' => $message,
                    'data' => $conversation
                ];
            }

            $updated = $this->conversationRepository->updatePHIStatus($conversation, $containsPHI);

            if (!$updated) {
                throw new \RuntimeException('Failed to update PHI status');
            }

            $conversation->refresh();

            $message = $containsPHI ? 'PHI status updated: Contains PHI' : 'PHI status updated: Does not contain PHI';

            return [
                'success' => true,
                'data' => $conversation,
                'message' => $message
            ];
        } catch (\Exception $e) {
            Log::error('Failed to update PHI status', [
                'id' => $id,
                'containsPHI' => $containsPHI,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to update PHI status. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'data' => null
            ];
        }
    }

    /**
     * Add participant to conversation.
     *
     * @param int $conversationId
     * @param int $userId
     * @param array $participantData
     * @return array
     */
    public function addParticipant(int $conversationId, int $userId, array $participantData = []): array
    {
        try {
            DB::beginTransaction();

            $conversation = $this->conversationRepository->findById($conversationId);

            if (!$conversation) {
                return [
                    'success' => false,
                    'message' => 'Conversation not found',
                    'data' => null
                ];
            }

            // Check if conversation is locked
            if ($conversation->isLocked()) {
                return [
                    'success' => false,
                    'message' => 'Cannot add participants to a locked conversation',
                    'data' => null
                ];
            }

            // Check if user is already a participant
            $existingParticipant = $conversation->participants()->where('user_id', $userId)->first();
            if ($existingParticipant) {
                return [
                    'success' => false,
                    'message' => 'User is already a participant in this conversation',
                    'data' => null
                ];
            }

            $added = $this->conversationRepository->addParticipant($conversation, $userId, $participantData);

            if (!$added) {
                throw new \RuntimeException('Failed to add participant');
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Participant added successfully',
                'data' => $conversation
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to add participant to conversation', [
                'conversation_id' => $conversationId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to add participant. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'data' => null
            ];
        }
    }

    /**
     * Remove participant from conversation.
     *
     * @param int $conversationId
     * @param int $userId
     * @return array
     */
    public function removeParticipant(int $conversationId, int $userId): array
    {
        try {
            DB::beginTransaction();

            $conversation = $this->conversationRepository->findById($conversationId);

            if (!$conversation) {
                return [
                    'success' => false,
                    'message' => 'Conversation not found',
                    'data' => null
                ];
            }

            // Check if conversation is locked
            if ($conversation->isLocked()) {
                return [
                    'success' => false,
                    'message' => 'Cannot remove participants from a locked conversation',
                    'data' => null
                ];
            }

            // Check if user is a participant
            $existingParticipant = $conversation->participants()->where('user_id', $userId)->first();
            if (!$existingParticipant) {
                return [
                    'success' => false,
                    'message' => 'User is not a participant in this conversation',
                    'data' => null
                ];
            }

            $removed = $this->conversationRepository->removeParticipant($conversation, $userId);

            if (!$removed) {
                throw new \RuntimeException('Failed to remove participant');
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Participant removed successfully',
                'data' => $conversation
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to remove participant from conversation', [
                'conversation_id' => $conversationId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to remove participant. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'data' => null
            ];
        }
    }

    /**
     * Get conversation participants.
     *
     * @param int $conversationId
     * @return array
     */
    public function getConversationParticipants(int $conversationId): array
    {
        try {
            $conversation = $this->conversationRepository->findById($conversationId);

            if (!$conversation) {
                return [
                    'success' => false,
                    'message' => 'Conversation not found',
                    'data' => null
                ];
            }

            $participants = $this->conversationRepository->getParticipants($conversation);

            return [
                'success' => true,
                'data' => $participants,
                'message' => 'Participants retrieved successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get conversation participants', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to retrieve participants. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'data' => null
            ];
        }
    }

    /**
     * Validate conversation data.
     *
     * @param array $data
     * @param bool $isUpdate
     * @return array
     */
    public function validateConversationData(array $data, bool $isUpdate = false): array
    {
        try {
            $rules = [
                'facility_id' => ['required', 'integer', 'exists:facilities,id'],
                'conversation_type' => ['required', 'string', 'in:direct,group,broadcast,system,care_context'],
                'visit_id' => ['nullable', 'integer', 'exists:visits,id'],
                'appointment_id' => ['nullable', 'integer', 'exists:appointments,id'],
                'department_code' => ['nullable', 'string', 'max:50'],
                'title' => ['nullable', 'string', 'max:255'],
                'contains_phi' => ['boolean'],
                'is_emergency' => ['boolean'],
                'status' => ['string', 'in:active,archived,locked'],
                'created_by_user_id' => ['nullable', 'integer', 'exists:users,id'],
            ];

            if (!$isUpdate) {
                $rules['conversation_uuid'] = ['nullable', 'string', 'uuid', 'unique:conversations,conversation_uuid'];
            } else {
                $rules['conversation_uuid'] = ['nullable', 'string', 'uuid'];
            }

            $validator = Validator::make($data, $rules);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            return [
                'success' => true,
                'validated_data' => $validator->validated()
            ];
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to validate conversation data', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Validation failed. Please check your input.',
                'errors' => ['general' => ['Validation failed']]
            ];
        }
    }
}