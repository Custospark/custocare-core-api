<?php

namespace App\Repositories\Contracts;

use App\Models\Message;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface MessageRepositoryInterface
{
    /**
     * Find a message by ID.
     */
    public function findById(int $id): ?Message;

    /**
     * Find a message by UUID.
     */
    public function findByUuid(string $uuid): ?Message;

    /**
     * Get all messages.
     */
    public function all(): Collection;

    /**
     * Get paginated messages.
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator;

    /**
     * Get messages by conversation ID.
     */
    public function getByConversation(int $conversationId, int $perPage = 20): LengthAwarePaginator;

    /**
     * Create a new message.
     */
    public function create(array $data): Message;

    /**
     * Update a message.
     */
    public function update(Message $message, array $data): bool;

    /**
     * Delete a message.
     */
    public function delete(Message $message): bool;

    /**
     * Restore a soft-deleted message.
     */
    public function restore(Message $message): bool;

    /**
     * Get messages that require acknowledgement.
     */
    public function getPendingAcknowledgements(int $conversationId = null): Collection;

    /**
     * Get clinical messages.
     */
    public function getClinicalMessages(int $conversationId = null): Collection;

    /**
     * Get message with relations.
     */
    public function findWithRelations(int $id, array $relations = []): ?Message;

    /**
     * Update delivery status.
     */
    public function updateDeliveryStatus(Message $message, string $status): bool;
}