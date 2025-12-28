<?php

namespace App\Services\Contracts;

use App\Models\Conversation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ConversationServiceInterface
{
    /**
     * Get all conversations with pagination.
     *
     * @param array $filters
     * @param int $perPage
     * @return array
     */
    public function getAllConversations(array $filters = [], int $perPage = 15): array;

    /**
     * Get conversation by ID.
     *
     * @param int $id
     * @return array
     */
    public function getConversationById(int $id): array;

    /**
     * Get conversation by UUID.
     *
     * @param string $uuid
     * @return array
     */
    public function getConversationByUuid(string $uuid): array;

    /**
     * Create a new conversation.
     *
     * @param array $data
     * @param int $createdByUserId
     * @return array
     */
    public function createConversation(array $data, int $createdByUserId): array;

    /**
     * Update an existing conversation.
     *
     * @param int $id
     * @param array $data
     * @return array
     */
    public function updateConversation(int $id, array $data): array;

    /**
     * Delete a conversation.
     *
     * @param int $id
     * @return array
     */
    public function deleteConversation(int $id): array;

    /**
     * Archive a conversation.
     *
     * @param int $id
     * @return array
     */
    public function archiveConversation(int $id): array;

    /**
     * Lock a conversation.
     *
     * @param int $id
     * @return array
     */
    public function lockConversation(int $id): array;

    /**
     * Activate a conversation.
     *
     * @param int $id
     * @return array
     */
    public function activateConversation(int $id): array;

    /**
     * Mark conversation as emergency.
     *
     * @param int $id
     * @param bool $emergency
     * @return array
     */
    public function markConversationAsEmergency(int $id, bool $emergency = true): array;

    /**
     * Update PHI status of conversation.
     *
     * @param int $id
     * @param bool $containsPHI
     * @return array
     */
    public function updateConversationPHIStatus(int $id, bool $containsPHI): array;

    /**
     * Add participant to conversation.
     *
     * @param int $conversationId
     * @param int $userId
     * @param array $participantData
     * @return array
     */
    public function addParticipant(int $conversationId, int $userId, array $participantData = []): array;

    /**
     * Remove participant from conversation.
     *
     * @param int $conversationId
     * @param int $userId
     * @return array
     */
    public function removeParticipant(int $conversationId, int $userId): array;

    /**
     * Get conversation participants.
     *
     * @param int $conversationId
     * @return array
     */
    public function getConversationParticipants(int $conversationId): array;

    /**
     * Validate conversation data.
     *
     * @param array $data
     * @param bool $isUpdate
     * @return array
     */
    public function validateConversationData(array $data, bool $isUpdate = false): array;
}