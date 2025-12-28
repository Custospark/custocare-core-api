<?php

namespace App\Repositories\ConversationParticipant;

use App\Models\ConversationParticipant;
use App\Repositories\Contracts\ConversationParticipantRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConversationParticipantRepository implements ConversationParticipantRepositoryInterface
{
    /**
     * @var ConversationParticipant
     */
    protected ConversationParticipant $model;

    /**
     * Constructor.
     *
     * @param ConversationParticipant $model
     */
    public function __construct(ConversationParticipant $model)
    {
        $this->model = $model;
    }

    /**
     * {@inheritdoc}
     */
    public function findById(int $id): ?ConversationParticipant
    {
        try {
            return $this->model->with(['conversation', 'participant'])->find($id);
        } catch (\Exception $e) {
            Log::error('Failed to find conversation participant by ID', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function all(array $filters = []): Collection
    {
        try {
            $query = $this->model->with(['conversation', 'participant']);

            // Apply filters
            if (!empty($filters['conversation_id'])) {
                $query->where('conversation_id', $filters['conversation_id']);
            }

            if (!empty($filters['participant_type'])) {
                $query->where('participant_type', $filters['participant_type']);
            }

            if (!empty($filters['participant_id'])) {
                $query->where('participant_id', $filters['participant_id']);
            }

            if (!empty($filters['role'])) {
                $query->where('role', $filters['role']);
            }

            if (isset($filters['is_muted'])) {
                $query->where('is_muted', (bool) $filters['is_muted']);
            }

            if (isset($filters['active_only']) && $filters['active_only']) {
                $query->active();
            }

            return $query->get();
        } catch (\Exception $e) {
            Log::error('Failed to retrieve conversation participants', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return new Collection();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        try {
            $query = $this->model->with(['conversation', 'participant']);

            // Apply filters
            if (!empty($filters['conversation_id'])) {
                $query->where('conversation_id', $filters['conversation_id']);
            }

            if (!empty($filters['participant_type'])) {
                $query->where('participant_type', $filters['participant_type']);
            }

            if (!empty($filters['participant_id'])) {
                $query->where('participant_id', $filters['participant_id']);
            }

            if (!empty($filters['role'])) {
                $query->where('role', $filters['role']);
            }

            if (isset($filters['is_muted'])) {
                $query->where('is_muted', (bool) $filters['is_muted']);
            }

            if (isset($filters['active_only']) && $filters['active_only']) {
                $query->active();
            }

            // Order by latest
            $query->orderBy('created_at', 'desc');

            return $query->paginate($perPage);
        } catch (\Exception $e) {
            Log::error('Failed to paginate conversation participants', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function findByConversationId(int $conversationId, bool $activeOnly = true): Collection
    {
        try {
            $query = $this->model->with(['conversation', 'participant'])
                                ->where('conversation_id', $conversationId);

            if ($activeOnly) {
                $query->active();
            }

            return $query->get();
        } catch (\Exception $e) {
            Log::error('Failed to find participants by conversation ID', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage()
            ]);
            return new Collection();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function findByParticipantDetails(
        int $conversationId,
        string $participantType,
        int $participantId
    ): ?ConversationParticipant {
        try {
            return $this->model->with(['conversation', 'participant'])
                ->where('conversation_id', $conversationId)
                ->where('participant_type', $participantType)
                ->where('participant_id', $participantId)
                ->first();
        } catch (\Exception $e) {
            Log::error('Failed to find participant by details', [
                'conversation_id' => $conversationId,
                'participant_type' => $participantType,
                'participant_id' => $participantId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $data): ConversationParticipant
    {
        try {
            DB::beginTransaction();

            // Set joined_at if not provided
            if (!isset($data['joined_at'])) {
                $data['joined_at'] = now();
            }

            $participant = $this->model->create($data);
            
            DB::commit();
            
            return $participant->load(['conversation', 'participant']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create conversation participant', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function update(ConversationParticipant $participant, array $data): ConversationParticipant
    {
        try {
            DB::beginTransaction();

            $participant->update($data);
            
            DB::commit();
            
            return $participant->load(['conversation', 'participant']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update conversation participant', [
                'participant_id' => $participant->id,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function delete(ConversationParticipant $participant): bool
    {
        try {
            DB::beginTransaction();

            $deleted = $participant->delete();
            
            DB::commit();
            
            return $deleted;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete conversation participant', [
                'participant_id' => $participant->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function markAsLeft(ConversationParticipant $participant): bool
    {
        try {
            DB::beginTransaction();

            $updated = $participant->update([
                'left_at' => now(),
                'is_muted' => false // Unmute when leaving
            ]);
            
            DB::commit();
            
            return $updated;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to mark participant as left', [
                'participant_id' => $participant->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setMutedStatus(ConversationParticipant $participant, bool $muted): bool
    {
        try {
            DB::beginTransaction();

            $updated = $participant->update([
                'is_muted' => $muted
            ]);
            
            DB::commit();
            
            return $updated;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update participant muted status', [
                'participant_id' => $participant->id,
                'muted' => $muted,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function updateRole(ConversationParticipant $participant, string $role): bool
    {
        try {
            DB::beginTransaction();

            $updated = $participant->update([
                'role' => $role
            ]);
            
            DB::commit();
            
            return $updated;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update participant role', [
                'participant_id' => $participant->id,
                'role' => $role,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function existsInConversation(
        int $conversationId,
        string $participantType,
        int $participantId
    ): bool {
        try {
            return $this->model
                ->where('conversation_id', $conversationId)
                ->where('participant_type', $participantType)
                ->where('participant_id', $participantId)
                ->whereNull('left_at')
                ->exists();
        } catch (\Exception $e) {
            Log::error('Failed to check if participant exists in conversation', [
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
    public function getActiveParticipantsCount(int $conversationId): int
    {
        try {
            return $this->model
                ->where('conversation_id', $conversationId)
                ->active()
                ->count();
        } catch (\Exception $e) {
            Log::error('Failed to get active participants count', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }
}