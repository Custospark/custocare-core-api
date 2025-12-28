<?php

namespace App\Services\ConversationParticipant;

use App\Models\ConversationParticipant;
use App\Repositories\Contracts\ConversationParticipantRepositoryInterface;
use App\Services\Contracts\ConversationParticipantServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConversationParticipantService implements ConversationParticipantServiceInterface
{
    /**
     * @var ConversationParticipantRepositoryInterface
     */
    protected ConversationParticipantRepositoryInterface $repository;

    /**
     * Constructor.
     *
     * @param ConversationParticipantRepositoryInterface $repository
     */
    public function __construct(ConversationParticipantRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * {@inheritdoc}
     */
    public function getAllParticipants(array $filters = []): Collection
    {
        try {
            return $this->repository->all($filters);
        } catch (\Exception $e) {
            Log::error('Failed to get all conversation participants', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return new Collection();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getPaginatedParticipants(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        try {
            return $this->repository->paginate($filters, $perPage);
        } catch (\Exception $e) {
            Log::error('Failed to get paginated conversation participants', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getParticipantById(int $id): ?ConversationParticipant
    {
        try {
            $participant = $this->repository->findById($id);
            
            if (!$participant) {
                Log::warning('Conversation participant not found', ['id' => $id]);
                return null;
            }
            
            return $participant;
        } catch (\Exception $e) {
            Log::error('Failed to get conversation participant by ID', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getParticipantsByConversation(int $conversationId, bool $activeOnly = true): Collection
    {
        try {
            return $this->repository->findByConversationId($conversationId, $activeOnly);
        } catch (\Exception $e) {
            Log::error('Failed to get participants by conversation', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage()
            ]);
            return new Collection();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function addParticipant(array $data): ConversationParticipant
    {
        try {
            DB::beginTransaction();

            // Validate participant doesn't already exist in conversation
            $exists = $this->repository->existsInConversation(
                $data['conversation_id'],
                $data['participant_type'],
                $data['participant_id']
            );

            if ($exists) {
                throw new \Exception('Participant already exists in conversation');
            }

            // Validate role
            if (isset($data['role']) && !in_array($data['role'], [
                ConversationParticipant::ROLE_OWNER,
                ConversationParticipant::ROLE_MODERATOR,
                ConversationParticipant::ROLE_MEMBER,
                ConversationParticipant::ROLE_READ_ONLY
            ])) {
                throw new \Exception('Invalid participant role');
            }

            // Validate participant type
            if (!in_array($data['participant_type'], [
                ConversationParticipant::PARTICIPANT_STAFF,
                ConversationParticipant::PARTICIPANT_PATIENT
            ])) {
                throw new \Exception('Invalid participant type');
            }

            $participant = $this->repository->create($data);
            
            DB::commit();
            
            Log::info('Conversation participant added successfully', [
                'id' => $participant->id,
                'conversation_id' => $data['conversation_id']
            ]);
            
            return $participant;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to add conversation participant', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function updateParticipant(int $id, array $data): ConversationParticipant
    {
        try {
            DB::beginTransaction();

            $participant = $this->getParticipantById($id);
            
            if (!$participant) {
                throw new \Exception('Conversation participant not found');
            }

            // Validate role if provided
            if (isset($data['role']) && !in_array($data['role'], [
                ConversationParticipant::ROLE_OWNER,
                ConversationParticipant::ROLE_MODERATOR,
                ConversationParticipant::ROLE_MEMBER,
                ConversationParticipant::ROLE_READ_ONLY
            ])) {
                throw new \Exception('Invalid participant role');
            }

            // Prevent updating conversation_id and participant details
            unset($data['conversation_id'], $data['participant_type'], $data['participant_id']);

            $updatedParticipant = $this->repository->update($participant, $data);
            
            DB::commit();
            
            Log::info('Conversation participant updated successfully', ['id' => $id]);
            
            return $updatedParticipant;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update conversation participant', [
                'id' => $id,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function removeParticipant(int $id): bool
    {
        try {
            DB::beginTransaction();

            $participant = $this->getParticipantById($id);
            
            if (!$participant) {
                throw new \Exception('Conversation participant not found');
            }

            // Check if participant is an owner
            if ($participant->hasRole(ConversationParticipant::ROLE_OWNER)) {
                throw new \Exception('Cannot remove conversation owner');
            }

            $result = $this->repository->delete($participant);
            
            DB::commit();
            
            if ($result) {
                Log::info('Conversation participant removed successfully', ['id' => $id]);
            }
            
            return $result;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to remove conversation participant', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function leaveConversation(int $id): bool
    {
        try {
            DB::beginTransaction();

            $participant = $this->getParticipantById($id);
            
            if (!$participant) {
                throw new \Exception('Conversation participant not found');
            }

            // Check if already left
            if ($participant->hasLeft()) {
                throw new \Exception('Participant has already left the conversation');
            }

            // Check if participant is an owner
            if ($participant->hasRole(ConversationParticipant::ROLE_OWNER)) {
                throw new \Exception('Conversation owner cannot leave, transfer ownership first');
            }

            $result = $this->repository->markAsLeft($participant);
            
            DB::commit();
            
            if ($result) {
                Log::info('Participant left conversation successfully', ['id' => $id]);
            }
            
            return $result;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to mark participant as left', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function muteParticipant(int $id): bool
    {
        try {
            $participant = $this->getParticipantById($id);
            
            if (!$participant) {
                throw new \Exception('Conversation participant not found');
            }

            if ($participant->is_muted) {
                throw new \Exception('Participant is already muted');
            }

            $result = $this->repository->setMutedStatus($participant, true);
            
            if ($result) {
                Log::info('Participant muted successfully', ['id' => $id]);
            }
            
            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to mute participant', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function unmuteParticipant(int $id): bool
    {
        try {
            $participant = $this->getParticipantById($id);
            
            if (!$participant) {
                throw new \Exception('Conversation participant not found');
            }

            if (!$participant->is_muted) {
                throw new \Exception('Participant is not muted');
            }

            $result = $this->repository->setMutedStatus($participant, false);
            
            if ($result) {
                Log::info('Participant unmuted successfully', ['id' => $id]);
            }
            
            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to unmute participant', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function updateParticipantRole(int $id, string $role): bool
    {
        try {
            DB::beginTransaction();

            $participant = $this->getParticipantById($id);
            
            if (!$participant) {
                throw new \Exception('Conversation participant not found');
            }

            // Validate role
            if (!in_array($role, [
                ConversationParticipant::ROLE_OWNER,
                ConversationParticipant::ROLE_MODERATOR,
                ConversationParticipant::ROLE_MEMBER,
                ConversationParticipant::ROLE_READ_ONLY
            ])) {
                throw new \Exception('Invalid participant role');
            }

            // Check if trying to change owner role
            if ($participant->hasRole(ConversationParticipant::ROLE_OWNER) && $role !== ConversationParticipant::ROLE_OWNER) {
                throw new \Exception('Cannot change owner role, transfer ownership first');
            }

            $result = $this->repository->updateRole($participant, $role);
            
            DB::commit();
            
            if ($result) {
                Log::info('Participant role updated successfully', [
                    'id' => $id,
                    'new_role' => $role
                ]);
            }
            
            return $result;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update participant role', [
                'id' => $id,
                'role' => $role,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isParticipantInConversation(
        int $conversationId,
        string $participantType,
        int $participantId
    ): bool {
        try {
            return $this->repository->existsInConversation($conversationId, $participantType, $participantId);
        } catch (\Exception $e) {
            Log::error('Failed to check if participant is in conversation', [
                'conversation_id' => $conversationId,
                'participant_type' => $participantType,
                'participant_id' => $participantId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getConversationActiveParticipantsCount(int $conversationId): int
    {
        try {
            return $this->repository->getActiveParticipantsCount($conversationId);
        } catch (\Exception $e) {
            Log::error('Failed to get active participants count', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }
}