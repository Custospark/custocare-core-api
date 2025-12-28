<?php

namespace App\Repositories\Conversation;

use App\Models\Conversation;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConversationRepository implements ConversationRepositoryInterface
{
    /**
     * Find a conversation by ID.
     *
     * @param int $id
     * @return Conversation|null
     */
    public function findById(int $id): ?Conversation
    {
        try {
            return Conversation::with(['facility', 'visit', 'appointment', 'createdBy'])
                ->find($id);
        } catch (\Exception $e) {
            Log::error('Failed to find conversation by ID', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Find a conversation by UUID.
     *
     * @param string $uuid
     * @return Conversation|null
     */
    public function findByUuid(string $uuid): ?Conversation
    {
        try {
            return Conversation::with(['facility', 'visit', 'appointment', 'createdBy'])
                ->where('conversation_uuid', $uuid)
                ->first();
        } catch (\Exception $e) {
            Log::error('Failed to find conversation by UUID', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get all conversations with pagination.
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Conversation::with(['facility', 'createdBy'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if (!empty($filters['facility_id'])) {
            $query->where('facility_id', $filters['facility_id']);
        }

        if (!empty($filters['conversation_type'])) {
            $query->where('conversation_type', $filters['conversation_type']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['contains_phi'])) {
            $query->where('contains_phi', (bool) $filters['contains_phi']);
        }

        if (isset($filters['is_emergency'])) {
            $query->where('is_emergency', (bool) $filters['is_emergency']);
        }

        if (!empty($filters['department_code'])) {
            $query->where('department_code', $filters['department_code']);
        }

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('title', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('conversation_uuid', 'like', '%' . $filters['search'] . '%');
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * Get conversations by facility.
     *
     * @param int $facilityId
     * @param array $filters
     * @return Collection
     */
    public function getByFacility(int $facilityId, array $filters = []): Collection
    {
        $query = Conversation::with(['createdBy', 'visit', 'appointment'])
            ->where('facility_id', $facilityId)
            ->orderBy('created_at', 'desc');

        if (!empty($filters['conversation_type'])) {
            $query->where('conversation_type', $filters['conversation_type']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->get();
    }

    /**
     * Create a new conversation.
     *
     * @param array $data
     * @return Conversation
     */
    public function create(array $data): Conversation
    {
        try {
            DB::beginTransaction();

            $conversation = Conversation::create($data);

            DB::commit();
            return $conversation;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create conversation', [
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new \RuntimeException('Failed to create conversation: ' . $e->getMessage());
        }
    }

    /**
     * Update an existing conversation.
     *
     * @param Conversation $conversation
     * @param array $data
     * @return bool
     */
    public function update(Conversation $conversation, array $data): bool
    {
        try {
            DB::beginTransaction();

            $updated = $conversation->update($data);

            DB::commit();
            return $updated;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update conversation', [
                'conversation_id' => $conversation->id,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to update conversation: ' . $e->getMessage());
        }
    }

    /**
     * Delete a conversation.
     *
     * @param Conversation $conversation
     * @return bool|null
     */
    public function delete(Conversation $conversation): ?bool
    {
        try {
            DB::beginTransaction();

            $deleted = $conversation->delete();

            DB::commit();
            return $deleted;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete conversation', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to delete conversation: ' . $e->getMessage());
        }
    }

    /**
     * Restore a soft-deleted conversation.
     *
     * @param Conversation $conversation
     * @return bool
     */
    public function restore(Conversation $conversation): bool
    {
        try {
            return $conversation->restore();
        } catch (\Exception $e) {
            Log::error('Failed to restore conversation', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to restore conversation: ' . $e->getMessage());
        }
    }

    /**
     * Archive a conversation.
     *
     * @param Conversation $conversation
     * @return bool
     */
    public function archive(Conversation $conversation): bool
    {
        try {
            return $conversation->update(['status' => 'archived']);
        } catch (\Exception $e) {
            Log::error('Failed to archive conversation', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to archive conversation: ' . $e->getMessage());
        }
    }

    /**
     * Lock a conversation.
     *
     * @param Conversation $conversation
     * @return bool
     */
    public function lock(Conversation $conversation): bool
    {
        try {
            return $conversation->update(['status' => 'locked']);
        } catch (\Exception $e) {
            Log::error('Failed to lock conversation', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to lock conversation: ' . $e->getMessage());
        }
    }

    /**
     * Activate a conversation.
     *
     * @param Conversation $conversation
     * @return bool
     */
    public function activate(Conversation $conversation): bool
    {
        try {
            return $conversation->update(['status' => 'active']);
        } catch (\Exception $e) {
            Log::error('Failed to activate conversation', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to activate conversation: ' . $e->getMessage());
        }
    }

    /**
     * Mark conversation as emergency.
     *
     * @param Conversation $conversation
     * @param bool $emergency
     * @return bool
     */
    public function markAsEmergency(Conversation $conversation, bool $emergency = true): bool
    {
        try {
            return $conversation->update(['is_emergency' => $emergency]);
        } catch (\Exception $e) {
            Log::error('Failed to mark conversation as emergency', [
                'conversation_id' => $conversation->id,
                'emergency' => $emergency,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to mark conversation as emergency: ' . $e->getMessage());
        }
    }

    /**
     * Update PHI status of conversation.
     *
     * @param Conversation $conversation
     * @param bool $containsPHI
     * @return bool
     */
    public function updatePHIStatus(Conversation $conversation, bool $containsPHI): bool
    {
        try {
            return $conversation->update(['contains_phi' => $containsPHI]);
        } catch (\Exception $e) {
            Log::error('Failed to update PHI status', [
                'conversation_id' => $conversation->id,
                'containsPHI' => $containsPHI,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to update PHI status: ' . $e->getMessage());
        }
    }

    /**
     * Add participant to conversation.
     *
     * @param Conversation $conversation
     * @param int $userId
     * @param array $participantData
     * @return bool
     */
    public function addParticipant(Conversation $conversation, int $userId, array $participantData = []): bool
    {
        try {
            DB::beginTransaction();

            $conversation->participants()->attach($userId, array_merge([
                'joined_at' => now(),
                'role' => 'participant',
                'is_admin' => false,
            ], $participantData));

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to add participant to conversation', [
                'conversation_id' => $conversation->id,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to add participant: ' . $e->getMessage());
        }
    }

    /**
     * Remove participant from conversation.
     *
     * @param Conversation $conversation
     * @param int $userId
     * @return bool
     */
    public function removeParticipant(Conversation $conversation, int $userId): bool
    {
        try {
            DB::beginTransaction();

            $conversation->participants()->detach($userId);

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to remove participant from conversation', [
                'conversation_id' => $conversation->id,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to remove participant: ' . $e->getMessage());
        }
    }

    /**
     * Get conversation participants.
     *
     * @param Conversation $conversation
     * @return Collection
     */
    public function getParticipants(Conversation $conversation): Collection
    {
        try {
            return $conversation->participants()->get();
        } catch (\Exception $e) {
            Log::error('Failed to get conversation participants', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to get participants: ' . $e->getMessage());
        }
    }
}