<?php

namespace App\Services\Contracts;

use App\Models\MessageReceipt;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface MessageReceiptServiceInterface
{
    /**
     * Get all message receipts with pagination.
     *
     * @param int $perPage
     * @return array
     */
    public function getAllReceipts(int $perPage = 15): array;

    /**
     * Get a specific message receipt by ID.
     *
     * @param int $id
     * @return array
     */
    public function getReceiptById(int $id): array;

    /**
     * Create a new message receipt.
     *
     * @param array $data
     * @return array
     */
    public function createReceipt(array $data): array;

    /**
     * Update an existing message receipt.
     *
     * @param int $id
     * @param array $data
     * @return array
     */
    public function updateReceipt(int $id, array $data): array;

    /**
     * Delete a message receipt.
     *
     * @param int $id
     * @return array
     */
    public function deleteReceipt(int $id): array;

    /**
     * Get receipts for a specific message.
     *
     * @param int $messageId
     * @return array
     */
    public function getReceiptsByMessage(int $messageId): array;

    /**
     * Get receipts for a specific recipient.
     *
     * @param string $recipientType
     * @param int $recipientId
     * @return array
     */
    public function getReceiptsByRecipient(string $recipientType, int $recipientId): array;

    /**
     * Mark a receipt as delivered.
     *
     * @param int $id
     * @return array
     */
    public function markAsDelivered(int $id): array;

    /**
     * Mark a receipt as read.
     *
     * @param int $id
     * @return array
     */
    public function markAsRead(int $id): array;

    /**
     * Mark a receipt as acknowledged.
     *
     * @param int $id
     * @return array
     */
    public function markAsAcknowledged(int $id): array;

    /**
     * Bulk update receipt statuses.
     *
     * @param array $receiptIds
     * @param string $status
     * @return array
     */
    public function bulkUpdateStatus(array $receiptIds, string $status): array;

    /**
     * Get unread count for a recipient.
     *
     * @param string $recipientType
     * @param int $recipientId
     * @return array
     */
    public function getUnreadCount(string $recipientType, int $recipientId): array;
}