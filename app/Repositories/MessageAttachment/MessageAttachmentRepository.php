<?php

namespace App\Repositories\MessageAttachment;

use App\Models\MessageAttachment;
use App\Repositories\Contracts\MessageAttachmentRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MessageAttachmentRepository implements MessageAttachmentRepositoryInterface
{
    /**
     * Find a message attachment by its ID.
     *
     * @param int $id
     * @return MessageAttachment|null
     */
    public function findById(int $id): ?MessageAttachment
    {
        try {
            return MessageAttachment::find($id);
        } catch (\Exception $e) {
            Log::error('Failed to find message attachment by ID', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Find a message attachment by its UUID.
     *
     * @param string $uuid
     * @return MessageAttachment|null
     */
    public function findByUuid(string $uuid): ?MessageAttachment
    {
        try {
            return MessageAttachment::where('attachment_uuid', $uuid)->first();
        } catch (\Exception $e) {
            Log::error('Failed to find message attachment by UUID', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Find all attachments for a specific message.
     *
     * @param int $messageId
     * @return Collection
     */
    public function findByMessageId(int $messageId): Collection
    {
        try {
            return MessageAttachment::where('message_id', $messageId)
                ->orderBy('created_at', 'desc')
                ->get();
        } catch (\Exception $e) {
            Log::error('Failed to find message attachments by message ID', [
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);
            return new Collection();
        }
    }

    /**
     * Find attachments by type.
     *
     * @param string $attachmentType
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function findByType(string $attachmentType, int $perPage = 15): LengthAwarePaginator
    {
        try {
            return MessageAttachment::where('attachment_type', $attachmentType)
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);
        } catch (\Exception $e) {
            Log::error('Failed to find message attachments by type', [
                'type' => $attachmentType,
                'error' => $e->getMessage(),
            ]);
            return MessageAttachment::paginate($perPage);
        }
    }

    /**
     * Get all message attachments with pagination.
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAll(int $perPage = 15): LengthAwarePaginator
    {
        try {
            return MessageAttachment::with('message')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);
        } catch (\Exception $e) {
            Log::error('Failed to get all message attachments', [
                'error' => $e->getMessage(),
            ]);
            return MessageAttachment::paginate($perPage);
        }
    }

    /**
     * Create a new message attachment.
     *
     * @param array $data
     * @return MessageAttachment
     */
    public function create(array $data): MessageAttachment
    {
        try {
            return MessageAttachment::create($data);
        } catch (\Exception $e) {
            Log::error('Failed to create message attachment', [
                'data' => $data,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Update an existing message attachment.
     *
     * @param int $id
     * @param array $data
     * @return MessageAttachment
     */
    public function update(int $id, array $data): MessageAttachment
    {
        try {
            $attachment = $this->findById($id);
            
            if (!$attachment) {
                throw new \Exception("Message attachment with ID {$id} not found");
            }
            
            $attachment->update($data);
            return $attachment->fresh();
        } catch (\Exception $e) {
            Log::error('Failed to update message attachment', [
                'id' => $id,
                'data' => $data,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Delete a message attachment.
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        try {
            $attachment = $this->findById($id);
            
            if (!$attachment) {
                return false;
            }
            
            return $attachment->delete();
        } catch (\Exception $e) {
            Log::error('Failed to delete message attachment', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Check if a checksum already exists.
     *
     * @param string $checksum
     * @param int|null $excludeId
     * @return bool
     */
    public function checksumExists(string $checksum, ?int $excludeId = null): bool
    {
        try {
            $query = MessageAttachment::where('checksum', $checksum);
            
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
            
            return $query->exists();
        } catch (\Exception $e) {
            Log::error('Failed to check checksum existence', [
                'checksum' => $checksum,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get attachments containing PHI.
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPhiAttachments(int $perPage = 15): LengthAwarePaginator
    {
        try {
            return MessageAttachment::where('contains_phi', true)
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);
        } catch (\Exception $e) {
            Log::error('Failed to get PHI attachments', [
                'error' => $e->getMessage(),
            ]);
            return MessageAttachment::paginate($perPage);
        }
    }

    /**
     * Get the total storage used by attachments.
     *
     * @return int
     */
    public function getTotalStorageUsed(): int
    {
        try {
            return MessageAttachment::sum('file_size_bytes');
        } catch (\Exception $e) {
            Log::error('Failed to calculate total storage used', [
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }
}