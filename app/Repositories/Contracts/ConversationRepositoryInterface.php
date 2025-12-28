<?php

namespace App\Repositories\Contracts;

use App\Models\Conversation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ConversationRepositoryInterface
{
    /**
     * Find a conversation by ID.
     *
     * @param int $id
     * @return Conversation|null
     */
    public function findById(int $id): ?Conversation;

    /**
     * Find a conversation by UUID.
     *
     * @param string $uuid
     * @return Conversation|null
     */
    public function findByUuid(string $uuid): ?Conversation;

    /**
     * Get all conversations with pagination.
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Get conversations by facility.
     *
     * @param int $facilityId
     * @param array $filters
     * @return Collection
     */
    public function getByFacility(int $facilityId, array $filters = []): Collection;

    /**
     * Create a new conversation.
     *
     * @param array $data
     * @return Conversation
     */
    public function create(array $data): Conversation;

    /**
     * Update an existing conversation.
     *
     * @param Conversation $conversation
     * @param array $data
     * @return bool
     */
    public function update(Conversation $conversation, array $data): bool;

    /**
     * Delete a conversation.
     *
     * @param Conversation $conversation
     * @return bool|null
     */
    public function delete(Conversation $conversation): ?bool;

    /**
     * Restore a soft-deleted conversation.
     *
     * @param Conversation $conversation
     * @return bool
     */
    public function restore(Conversation $conversation): bool;

    /**
     * Archive a conversation.
     *
     * @param Conversation $conversation
     * @return bool
     */
    public function archive(Conversation $conversation): bool;

    /**
     * Lock a conversation.
     *
     * @param Conversation $conversation
     * @return bool
     */
    public function lock(Conversation $conversation): bool;

    /**
     * Activate a conversation.
     *
     * @param Conversation $conversation
     * @return bool
     */
    public function activate(Conversation $conversation): bool;

    /**
     * Mark conversation as emergency.
     *
     * @param Conversation $conversation
     * @param bool $emergency
     * @return bool
     */
    public function markAsEmergency(Conversation $conversation, bool $emergency = true): bool;

    /**
     * Update PHI status of conversation.
     *
     * @param Conversation $conversation
     * @param bool $containsPHI
     * @return bool
     */
    public function updatePHIStatus(Conversation $conversation, bool $containsPHI): bool;

    /**
     * Add participant to conversation.
     *
     * @param Conversation $conversation
     * @param int $userId
     * @param array $participantData
     * @return bool
     */
    public function addParticipant(Conversation $conversation, int $userId, array $participantData = []): bool;

    /**
     * Remove participant from conversation.
     *
     * @param Conversation $conversation
     * @param int $userId
     * @return bool
     */
    public function removeParticipant(Conversation $conversation, int $userId): bool;

    /**
     * Get conversation participants.
     *
     * @param Conversation $conversation
     * @return Collection
     */
    public function getParticipants(Conversation $conversation): Collection;
}