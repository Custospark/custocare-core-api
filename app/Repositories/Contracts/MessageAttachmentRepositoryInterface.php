<?php

namespace App\Repositories\Contracts;

use App\Models\MessageAttachment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface MessageAttachmentRepositoryInterface
{
    /**
     * Find a message attachment by its ID.
     *
     * @param int $id
     * @return MessageAttachment|null
     */
    public function findById(int $id): ?MessageAttachment;

    /**
     * Find a message attachment by its UUID.
     *
     * @param string $uuid
     * @return MessageAttachment|null
     */
    public function findByUuid(string $uuid): ?MessageAttachment;

    /**
     * Find all attachments for a specific message.
     *
     * @param int $messageId
     * @return Collection
     */
    public function findByMessageId(int $messageId): Collection;

    /**
     * Find attachments by type.
     *
     * @param string $attachmentType
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function findByType(string $attachmentType, int $perPage = 15): LengthAwarePaginator;

    /**
     * Get all message attachments with pagination.
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAll(int $perPage = 15): LengthAwarePaginator;

    /**
     * Create a new message attachment.
     *
     * @param array $data
     * @return MessageAttachment
     */
    public function create(array $data): MessageAttachment;

    /**
     * Update an existing message attachment.
     *
     * @param int $id
     * @param array $data
     * @return MessageAttachment
     */
    public function update(int $id, array $data): MessageAttachment;

    /**
     * Delete a message attachment.
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool;

    /**
     * Check if a checksum already exists.
     *
     * @param string $checksum
     * @param int|null $excludeId
     * @return bool
     */
    public function checksumExists(string $checksum, ?int $excludeId = null): bool;

    /**
     * Get attachments containing PHI.
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPhiAttachments(int $perPage = 15): LengthAwarePaginator;

    /**
     * Get the total storage used by attachments.
     *
     * @return int
     */
    public function getTotalStorageUsed(): int;
}