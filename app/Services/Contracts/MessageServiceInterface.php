<?php

namespace App\Services\Contracts;

use App\Models\Message;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface MessageServiceInterface
{
    /**
     * Get paginated messages.
     */
    public function getMessages(int $perPage = 15): LengthAwarePaginator;

    /**
     * Get messages by conversation.
     */
    public function getConversationMessages(int $conversationId, int $perPage = 20): LengthAwarePaginator;

    /**
     * Get a message by ID.
     */
    public function getMessage(int $id): ?Message;

    /**
     * Get a message by UUID.
     */
    public function getMessageByUuid(string $uuid): ?Message;

    /**
     * Create a new message.
     */
    public function createMessage(array $data): Message;

    /**
     * Update a message.
     */
    public function updateMessage(int $id, array $data): ?Message;

    /**
     * Delete a message.
     */
    public function deleteMessage(int $id): bool;

    /**
     * Restore a deleted message.
     */
    public function restoreMessage(int $id): bool;

    /**
     * Mark message as delivered.
     */
    public function markAsDelivered(int $messageId): bool;

    /**
     * Mark message as sent.
     */
    public function markAsSent(int $messageId): bool;

    /**
     * Acknowledge a message.
     */
    public function acknowledgeMessage(int $messageId): bool;

    /**
     * Get clinical messages.
     */
    public function getClinicalMessages(?int $conversationId = null): Collection;

    /**
     * Validate message content.
     */
    public function validateContent(string $content): array;

    /**
     * Get message with full details.
     */
    public function getMessageWithDetails(int $id): ?Message;

    /**
     * Process message encryption.
     */
    public function encryptContent(string $content): array;
}