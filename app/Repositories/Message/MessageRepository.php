<?php

namespace App\Repositories\Message;

use App\Models\Message;
use App\Repositories\Contracts\MessageRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MessageRepository implements MessageRepositoryInterface
{
    /**
     * Find a message by ID.
     */
    public function findById(int $id): ?Message
    {
        try {
            return Message::find($id);
        } catch (\Exception $e) {
            Log::error('Failed to find message by ID', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Find a message by UUID.
     */
    public function findByUuid(string $uuid): ?Message
    {
        try {
            return Message::where('message_uuid', $uuid)->first();
        } catch (\Exception $e) {
            Log::error('Failed to find message by UUID', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get all messages.
     */
    public function all(): Collection
    {
        try {
            return Message::all();
        } catch (\Exception $e) {
            Log::error('Failed to retrieve all messages', [
                'error' => $e->getMessage(),
            ]);
            return new Collection();
        }
    }

    /**
     * Get paginated messages.
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        try {
            return Message::with(['conversation', 'sender'])
                ->latest()
                ->paginate($perPage);
        } catch (\Exception $e) {
            Log::error('Failed to paginate messages', [
                'error' => $e->getMessage(),
            ]);
            return new LengthAwarePaginator([], 0, $perPage);
        }
    }

    /**
     * Get messages by conversation ID.
     */
    public function getByConversation(int $conversationId, int $perPage = 20): LengthAwarePaginator
    {
        try {
            return Message::where('conversation_id', $conversationId)
                ->with(['sender', 'parent', 'replies', 'editor'])
                ->orderBy('created_at', 'asc')
                ->paginate($perPage);
        } catch (\Exception $e) {
            Log::error('Failed to get messages by conversation', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
            ]);
            return new LengthAwarePaginator([], 0, $perPage);
        }
    }

    /**
     * Create a new message.
     */
    public function create(array $data): Message
    {
        try {
            return Message::create($data);
        } catch (\Exception $e) {
            Log::error('Failed to create message', [
                'data' => $data,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Failed to create message: ' . $e->getMessage());
        }
    }

    /**
     * Update a message.
     */
    public function update(Message $message, array $data): bool
    {
        try {
            return $message->update($data);
        } catch (\Exception $e) {
            Log::error('Failed to update message', [
                'message_id' => $message->id,
                'data' => $data,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Delete a message.
     */
    public function delete(Message $message): bool
    {
        try {
            return $message->delete();
        } catch (\Exception $e) {
            Log::error('Failed to delete message', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Restore a soft-deleted message.
     */
    public function restore(Message $message): bool
    {
        try {
            return $message->restore();
        } catch (\Exception $e) {
            Log::error('Failed to restore message', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get messages that require acknowledgement.
     */
    public function getPendingAcknowledgements(?int $conversationId = null): Collection
    {
        try {
            $query = Message::where('requires_acknowledgement', true)
                ->where('delivery_status', 'sent');

            if ($conversationId) {
                $query->where('conversation_id', $conversationId);
            }

            return $query->get();
        } catch (\Exception $e) {
            Log::error('Failed to get pending acknowledgements', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
            ]);
            return new Collection();
        }
    }

    /**
     * Get clinical messages.
     */
    public function getClinicalMessages(?int $conversationId = null): Collection
    {
        try {
            $query = Message::where('is_clinical', true);

            if ($conversationId) {
                $query->where('conversation_id', $conversationId);
            }

            return $query->get();
        } catch (\Exception $e) {
            Log::error('Failed to get clinical messages', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
            ]);
            return new Collection();
        }
    }

    /**
     * Get message with relations.
     */
    public function findWithRelations(int $id, array $relations = []): ?Message
    {
        try {
            return Message::with($relations)->find($id);
        } catch (\Exception $e) {
            Log::error('Failed to find message with relations', [
                'id' => $id,
                'relations' => $relations,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Update delivery status.
     */
    public function updateDeliveryStatus(Message $message, string $status): bool
    {
        try {
            return $message->update(['delivery_status' => $status]);
        } catch (\Exception $e) {
            Log::error('Failed to update delivery status', [
                'message_id' => $message->id,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}