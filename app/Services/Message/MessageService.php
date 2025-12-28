<?php

namespace App\Services\Message;

use App\Models\Message;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Services\Contracts\MessageServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;

class MessageService implements MessageServiceInterface
{
    /**
     * The message repository instance.
     */
    private MessageRepositoryInterface $messageRepository;

    /**
     * Create a new service instance.
     */
    public function __construct(MessageRepositoryInterface $messageRepository)
    {
        $this->messageRepository = $messageRepository;
    }

    /**
     * Get paginated messages.
     */
    public function getMessages(int $perPage = 15): LengthAwarePaginator
    {
        try {
            return $this->messageRepository->paginate($perPage);
        } catch (\Exception $e) {
            Log::error('Service: Failed to get paginated messages', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return new LengthAwarePaginator([], 0, $perPage);
        }
    }

    /**
     * Get messages by conversation.
     */
    public function getConversationMessages(int $conversationId, int $perPage = 20): LengthAwarePaginator
    {
        try {
            return $this->messageRepository->getByConversation($conversationId, $perPage);
        } catch (\Exception $e) {
            Log::error('Service: Failed to get conversation messages', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return new LengthAwarePaginator([], 0, $perPage);
        }
    }

    /**
     * Get a message by ID.
     */
    public function getMessage(int $id): ?Message
    {
        try {
            return $this->messageRepository->findWithRelations($id, ['conversation', 'sender', 'parent', 'replies', 'editor']);
        } catch (\Exception $e) {
            Log::error('Service: Failed to get message by ID', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Get a message by UUID.
     */
    public function getMessageByUuid(string $uuid): ?Message
    {
        try {
            return $this->messageRepository->findByUuid($uuid);
        } catch (\Exception $e) {
            Log::error('Service: Failed to get message by UUID', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Create a new message.
     */
    public function createMessage(array $data): Message
    {
        DB::beginTransaction();
        
        try {
            // Encrypt content if provided
            if (isset($data['content'])) {
                $encrypted = $this->encryptContent($data['content']);
                $data['content_encrypted'] = $encrypted['encrypted'];
                $data['content_hash'] = $encrypted['hash'];
                unset($data['content']);
            }

            // Set default values
            $data['delivery_status'] = $data['delivery_status'] ?? 'pending';
            $data['contains_phi'] = $data['contains_phi'] ?? true;

            // Create the message
            $message = $this->messageRepository->create($data);

            // If message requires acknowledgement, trigger notification
            if ($message->requires_acknowledgement) {
                $this->triggerAcknowledgementNotification($message);
            }

            DB::commit();
            return $message;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Service: Failed to create message', [
                'data' => $this->sanitizeData($data),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new \RuntimeException('Failed to create message. Please try again.');
        }
    }

    /**
     * Update a message.
     */
    public function updateMessage(int $id, array $data): ?Message
    {
        DB::beginTransaction();
        
        try {
            $message = $this->messageRepository->findById($id);
            
            if (!$message) {
                return null;
            }

            // If content is being updated, encrypt it and set edit timestamp
            if (isset($data['content'])) {
                $encrypted = $this->encryptContent($data['content']);
                $data['content_encrypted'] = $encrypted['encrypted'];
                $data['content_hash'] = $encrypted['hash'];
                $data['edited_at'] = now();
                unset($data['content']);
            }

            // Update the message
            $updated = $this->messageRepository->update($message, $data);
            
            if (!$updated) {
                DB::rollBack();
                return null;
            }

            // Refresh the message instance
            $message->refresh();

            DB::commit();
            return $message;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Service: Failed to update message', [
                'id' => $id,
                'data' => $this->sanitizeData($data),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new \RuntimeException('Failed to update message. Please try again.');
        }
    }

    /**
     * Delete a message.
     */
    public function deleteMessage(int $id): bool
    {
        DB::beginTransaction();
        
        try {
            $message = $this->messageRepository->findById($id);
            
            if (!$message) {
                return false;
            }

            // Perform soft delete
            $deleted = $this->messageRepository->delete($message);
            
            if (!$deleted) {
                DB::rollBack();
                return false;
            }

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Service: Failed to delete message', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new \RuntimeException('Failed to delete message. Please try again.');
        }
    }

    /**
     * Restore a deleted message.
     */
    public function restoreMessage(int $id): bool
    {
        DB::beginTransaction();
        
        try {
            $message = Message::withTrashed()->find($id);
            
            if (!$message) {
                return false;
            }

            $restored = $this->messageRepository->restore($message);
            
            if (!$restored) {
                DB::rollBack();
                return false;
            }

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Service: Failed to restore message', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new \RuntimeException('Failed to restore message. Please try again.');
        }
    }

    /**
     * Mark message as delivered.
     */
    public function markAsDelivered(int $messageId): bool
    {
        try {
            $message = $this->messageRepository->findById($messageId);
            
            if (!$message) {
                return false;
            }

            return $this->messageRepository->updateDeliveryStatus($message, 'delivered');
        } catch (\Exception $e) {
            Log::error('Service: Failed to mark message as delivered', [
                'message_id' => $messageId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Mark message as sent.
     */
    public function markAsSent(int $messageId): bool
    {
        try {
            $message = $this->messageRepository->findById($messageId);
            
            if (!$message) {
                return false;
            }

            return $this->messageRepository->updateDeliveryStatus($message, 'sent');
        } catch (\Exception $e) {
            Log::error('Service: Failed to mark message as sent', [
                'message_id' => $messageId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Acknowledge a message.
     */
    public function acknowledgeMessage(int $messageId): bool
    {
        DB::beginTransaction();
        
        try {
            $message = $this->messageRepository->findById($messageId);
            
            if (!$message || !$message->requires_acknowledgement) {
                DB::rollBack();
                return false;
            }

            // Update delivery status to delivered (acknowledged)
            $updated = $this->messageRepository->updateDeliveryStatus($message, 'delivered');
            
            if (!$updated) {
                DB::rollBack();
                return false;
            }

            // Here you would typically trigger any post-acknowledgement actions
            // such as notifications to the sender

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Service: Failed to acknowledge message', [
                'message_id' => $messageId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Get clinical messages.
     */
    public function getClinicalMessages(?int $conversationId = null): Collection
    {
        try {
            return $this->messageRepository->getClinicalMessages($conversationId);
        } catch (\Exception $e) {
            Log::error('Service: Failed to get clinical messages', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return new Collection();
        }
    }

    /**
     * Validate message content.
     */
    public function validateContent(string $content): array
    {
        $validation = [
            'is_valid' => true,
            'errors' => [],
        ];

        // Check content length
        if (strlen($content) > 10000) {
            $validation['is_valid'] = false;
            $validation['errors'][] = 'Content exceeds maximum length of 10,000 characters';
        }

        // Check for empty content (unless it's a system event or file)
        if (empty(trim($content))) {
            $validation['is_valid'] = false;
            $validation['errors'][] = 'Content cannot be empty';
        }

        return $validation;
    }

    /**
     * Get message with full details.
     */
    public function getMessageWithDetails(int $id): ?Message
    {
        try {
            return $this->messageRepository->findWithRelations($id, [
                'conversation',
                'sender',
                'parent',
                'replies',
                'editor',
                'conversation.participants',
            ]);
        } catch (\Exception $e) {
            Log::error('Service: Failed to get message with details', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Process message encryption.
     */
    public function encryptContent(string $content): array
    {
        try {
            // In a real application, you would use proper encryption
            // This is a simplified example
            $encrypted = base64_encode($content); // Replace with proper encryption
            $hash = hash('sha256', $content);
            
            return [
                'encrypted' => $encrypted,
                'hash' => $hash,
            ];
        } catch (\Exception $e) {
            Log::error('Service: Failed to encrypt content', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new \RuntimeException('Failed to encrypt message content.');
        }
    }

    /**
     * Trigger acknowledgement notification.
     */
    private function triggerAcknowledgementNotification(Message $message): void
    {
        // Implementation would depend on your notification system
        // This is a placeholder for the actual notification logic
        Log::info('Acknowledgement required for message', [
            'message_id' => $message->id,
            'conversation_id' => $message->conversation_id,
        ]);
    }

    /**
     * Sanitize data for logging.
     */
    private function sanitizeData(array $data): array
    {
        $sensitiveFields = ['content', 'content_encrypted'];
        
        foreach ($sensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '[REDACTED]';
            }
        }
        
        return $data;
    }
}