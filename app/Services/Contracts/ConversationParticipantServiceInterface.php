<?php

namespace App\Services\Contracts;

use App\Models\ConversationParticipant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ConversationParticipantServiceInterface
{
    /**
     * Get all conversation participants.
     *
     * @param array $filters
     * @return Collection
     */
    public function getAllParticipants(array $filters = []): Collection;

    /**
     * Get paginated conversation participants.
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPaginatedParticipants(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Get participant by ID.
     *
     * @param int $id
     * @return ConversationParticipant|null
     */
    public function getParticipantById(int $id): ?ConversationParticipant;

    /**
     * Get participants by conversation ID.
     *
     * @param int $conversationId
     * @param bool $activeOnly
     * @return Collection
     */
    public function getParticipantsByConversation(int $conversationId, bool $activeOnly = true): Collection;

    /**
     * Add a participant to a conversation.
     *
     * @param array $data
     * @return ConversationParticipant
     */
    public function addParticipant(array $data): ConversationParticipant;

    /**
     * Update participant details.
     *
     * @param int $id
     * @param array $data
     * @return ConversationParticipant
     */
    public function updateParticipant(int $id, array $data): ConversationParticipant;

    /**
     * Remove participant from conversation.
     *
     * @param int $id
     * @return bool
     */
    public function removeParticipant(int $id): bool;

    /**
     * Mark participant as left.
     *
     * @param int $id
     * @return bool
     */
    public function leaveConversation(int $id): bool;

    /**
     * Mute a participant in conversation.
     *
     * @param int $id
     * @return bool
     */
    public function muteParticipant(int $id): bool;

    /**
     * Unmute a participant in conversation.
     *
     * @param int $id
     * @return bool
     */
    public function unmuteParticipant(int $id): bool;

    /**
     * Update participant role.
     *
     * @param int $id
     * @param string $role
     * @return bool
     */
    public function updateParticipantRole(int $id, string $role): bool;

    /**
     * Check if participant exists in conversation.
     *
     * @param int $conversationId
     * @param string $participantType
     * @param int $participantId
     * @return bool
     */
    public function isParticipantInConversation(
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
    public function getConversationActiveParticipantsCount(int $conversationId): int;
}