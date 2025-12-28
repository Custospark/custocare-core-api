<?php

namespace App\Services\Contracts;

use App\Models\MessageAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

interface MessageAttachmentServiceInterface
{
    /**
     * Get all message attachments.
     *
     * @param int $perPage
     * @return array
     */
    public function getAllAttachments(int $perPage = 15): array;

    /**
     * Get a specific message attachment by ID.
     *
     * @param int $id
     * @return array
     */
    public function getAttachmentById(int $id): array;

    /**
     * Get a specific message attachment by UUID.
     *
     * @param string $uuid
     * @return array
     */
    public function getAttachmentByUuid(string $uuid): array;

    /**
     * Get attachments for a specific message.
     *
     * @param int $messageId
     * @return array
     */
    public function getAttachmentsByMessage(int $messageId): array;

    /**
     * Create a new message attachment.
     *
     * @param array $data
     * @return array
     */
    public function createAttachment(array $data): array;

    /**
     * Update an existing message attachment.
     *
     * @param int $id
     * @param array $data
     * @return array
     */
    public function updateAttachment(int $id, array $data): array;

    /**
     * Delete a message attachment.
     *
     * @param int $id
     * @return array
     */
    public function deleteAttachment(int $id): array;

    /**
     * Process and store an uploaded file as message attachment.
     *
     * @param UploadedFile $file
     * @param int $messageId
     * @param string $attachmentType
     * @param bool $containsPhi
     * @return array
     */
    public function processFileUpload(
        UploadedFile $file,
        int $messageId,
        string $attachmentType,
        bool $containsPhi = true
    ): array;

    /**
     * Get attachments by type.
     *
     * @param string $type
     * @param int $perPage
     * @return array
     */
    public function getAttachmentsByType(string $type, int $perPage = 15): array;

    /**
     * Get statistics about attachments.
     *
     * @return array
     */
    public function getAttachmentStatistics(): array;

    /**
     * Validate attachment type.
     *
     * @param string $type
     * @return bool
     */
    public function validateAttachmentType(string $type): bool;

    /**
     * Check if file with same checksum already exists.
     *
     * @param string $checksum
     * @param int|null $excludeId
     * @return array
     */
    public function checkFileDuplicate(string $checksum, ?int $excludeId = null): array;
}