<?php

namespace App\Repositories\Contracts;

use App\Models\ConversationParticipant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ConversationParticipantRepositoryInterface
{
    /**
     * Find a conversation participant by ID.
     *
     * @param int $id
     * @return ConversationParticipant|null
     */
    public function findById(int $id): ?ConversationParticipant;

    /**
     * Get all conversation participants.
     *
     * @param array $filters
     * @return Collection
     */
    public function all(array $filters = []): Collection;

    /**
     * Get paginated conversation participants.
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Get participants by conversation ID.
     *
     * @param int $conversationId
     * @param bool $activeOnly
     * @return Collection
     */
    public function findByConversationId(int $conversationId, bool $activeOnly = true): Collection;

    /**
     * Find participant by conversation and participant details.
     *
     * @param int $conversationId
     * @param string $participantType
     * @param int $participantId
     * @return ConversationParticipant|null
     */
    public function findByParticipantDetails(
        int $conversationId,
        string $participantType,
        int $participantId
    ): ?ConversationParticipant;

    /**
     * Create a new conversation participant.
     *
     * @param array $data
     * @return ConversationParticipant
     */
    public function create(array $data): ConversationParticipant;

    /**
     * Update an existing conversation participant.
     *
     * @param ConversationParticipant $participant
     * @param array $data
     * @return ConversationParticipant
     */
    public function update(ConversationParticipant $participant, array $data): ConversationParticipant;

    /**
     * Delete a conversation participant.
     *
     * @param ConversationParticipant $participant
     * @return bool
     */
    public function delete(ConversationParticipant $participant): bool;

    /**
     * Mark participant as left.
     *
     * @param ConversationParticipant $participant
     * @return bool
     */
    public function markAsLeft(ConversationParticipant $participant): bool;

    /**
     * Mute/unmute a participant.
     *
     * @param ConversationParticipant $participant
     * @param bool $muted
     * @return bool
     */
    public function setMutedStatus(ConversationParticipant $participant, bool $muted): bool;

    /**
     * Update participant role.
     *
     * @param ConversationParticipant $participant
     * @param string $role
     * @return bool
     */
    public function updateRole(ConversationParticipant $participant, string $role): bool;

    /**
     * Check if participant exists in conversation.
     *
     * @param int $conversationId
     * @param string $participantType
     * @param int $participantId
     * @return bool
     */
    public function existsInConversation(
        int $conversationId,
        string $participantType,
        int $participantId
    ): bool;

    /**
     * Get active participants count for conversation.
     *
     * @param int $conversationId
     * @return int
     */
    public function getActiveParticipantsCount(int $conversationId): int;
}