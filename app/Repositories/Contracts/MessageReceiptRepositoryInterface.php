<?php

namespace App\Repositories\Contracts;

use App\Models\MessageReceipt;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface MessageReceiptRepositoryInterface
{
    /**
     * Find a message receipt by ID.
     *
     * @param int $id
     * @return MessageReceipt|null
     */
    public function find(int $id): ?MessageReceipt;

    /**
     * Find a message receipt by ID or throw an exception.
     *
     * @param int $id
     * @return MessageReceipt
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findOrFail(int $id): MessageReceipt;

    /**
     * Get all message receipts.
     *
     * @param array $columns
     * @return Collection
     */
    public function all(array $columns = ['*']): Collection;

    /**
     * Get paginated message receipts.
     *
     * @param int $perPage
     * @param array $columns
     * @return LengthAwarePaginator
     */
    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator;

    /**
     * Create a new message receipt.
     *
     * @param array $data
     * @return MessageReceipt
     */
    public function create(array $data): MessageReceipt;

    /**
     * Update an existing message receipt.
     *
     * @param int $id
     * @param array $data
     * @return MessageReceipt
     */
    public function update(int $id, array $data): MessageReceipt;

    /**
     * Delete a message receipt.
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool;

    /**
     * Find receipts by message ID.
     *
     * @param int $messageId
     * @return Collection
     */
    public function findByMessageId(int $messageId): Collection;

    /**
     * Find receipts by recipient.
     *
     * @param string $recipientType
     * @param int $recipientId
     * @return Collection
     */
    public function findByRecipient(string $recipientType, int $recipientId): Collection;

    /**
     * Mark a receipt as delivered.
     *
     * @param int $id
     * @return MessageReceipt
     */
    public function markAsDelivered(int $id): MessageReceipt;

    /**
     * Mark a receipt as read.
     *
     * @param int $id
     * @return MessageReceipt
     */
    public function markAsRead(int $id): MessageReceipt;

    /**
     * Mark a receipt as acknowledged.
     *
     * @param int $id
     * @return MessageReceipt
     */
    public function markAsAcknowledged(int $id): MessageReceipt;

    /**
     * Get unread receipts for a recipient.
     *
     * @param string $recipientType
     * @param int $recipientId
     * @return Collection
     */
    public function getUnreadReceipts(string $recipientType, int $recipientId): Collection;

    /**
     * Get undelivered receipts for a message.
     *
     * @param int $messageId
     * @return Collection
     */
    public function getUndeliveredReceipts(int $messageId): Collection;
}