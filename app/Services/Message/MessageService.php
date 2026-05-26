<?php

declare(strict_types=1);

namespace App\Services\Message;

use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\MessageLabel;
use App\Models\MessageRecipient;
use App\Models\MessageUserState;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use App\Exceptions\MessageRecipientNotResolvedException;
use InvalidArgumentException;
use RuntimeException;
use Throwable;


/**
 * MessageService
 * ──────────────
 * Encapsulates all business logic for the messaging module.
 * The controller delegates every non-HTTP concern to this class.
 *
 * Public surface:
 *   getFolder()          – Paginated list for a mailbox folder
 *   getMessage()         – Single message with relationships
 *   saveDraft()          – Create or update a draft
 *   sendMessage()        – Dispatch a composed/draft message
 *   updateDraft()        – Patch a draft's fields
 *   moveToDraft()        – Pull a sent message back to draft (edge case)
 *   markRead()           – Mark as read for the calling user
 *   markUnread()         – Mark as unread
 *   toggleStar()         – Toggle star flag
 *   archiveMessage()     – Move to archive folder
 *   unarchiveMessage()   – Restore from archive
 *   trashMessage()       – Move to trash
 *   restoreFromTrash()   – Move back to original folder
 *   permanentDelete()    – Hard-delete message+state
 *   emptyTrash()         – Permanently delete all trashed items for user
 *   bulkAction()         – Apply an action to many messages at once
 *   addLabel()           – Add a label tag
 *   removeLabel()        – Remove a label tag
 *   uploadAttachment()   – Store a file and attach to a message
 *   removeAttachment()   – Detach & delete a file
 *   purgeExpiredTrash()  – Cron-callable: hard-delete expired trash rows
 *   getStats()           – Folder unread/total counts for sidebar badges
 */
class MessageService
{
    /**
     * Available folder types
     */
    public const FOLDERS = ['inbox', 'sent', 'drafts', 'archive', 'trash'];
    
    /**
     * Available filter types
     */
    public const FILTERS = ['all', 'unread', 'starred', 'archived', 'incomplete', 'failed'];
    
    /**
     * Available sort options
     */
    public const SORTS = ['newest', 'oldest', 'alphabetical', 'recentlyDeleted', 'originalDate'];

    // ── Folder listing ────────────────────────────────────────────────────

    /**
     * Return a paginated list of messages for a given folder.
     *
     * @param User $user The authenticated user
     * @param array $params Validated query parameters
     * @return LengthAwarePaginator
     * 
     * @throws InvalidArgumentException
     */
    public function getFolder(User $user, array $params): LengthAwarePaginator
    {
        try {
            $folder = $this->validateFolder($params['folder'] ?? 'inbox');
            $filter = $this->validateFilter($params['filter'] ?? 'all');
            $sort = $this->validateSort($params['sort'] ?? 'newest');
            $search = $params['search'] ?? null;
            $perPage = (int) ($params['per_page'] ?? 20);

            // Build base query
            $query = $this->buildBaseFolderQuery($user, $folder);
            
            // Apply filters
            $query = $this->applyFilter($query, $filter);
            
            // Apply search if provided
            if ($search) {
                $query = $this->applySearch($query, $user, $search);
            }
            
            // Apply sorting
            $query = $this->applySorting($query, $sort);

            return $query->select('message_user_states.*')->paginate($perPage);
            
        } catch (Throwable $e) {
            Log::error('Failed to get folder messages', [
                'user_id' => $user->id,
                'params' => $params,
                'error' => $e->getMessage()
            ]);
            
            throw new RuntimeException('Failed to retrieve messages. Please try again.', 0, $e);
        }
    }

    /**
     * Build base folder query.
     */
    private function buildBaseFolderQuery(User $user, string $folder): Builder
    {
        return MessageUserState::query()
            ->where('message_user_states.user_id', $user->id)
            ->where('message_user_states.folder', $folder)
            ->with([
                    'message.sender:id,first_name,last_name,display_name,email_hash',
                    'message.toRecipients',
                    'message.ccRecipients',
                    'message.attachments',
                    'message.labels' => fn ($q) => $q->where('user_id', $user->id), 
                ])
            ->join('messages', 'messages.id', '=', 'message_user_states.message_id')
            ->whereNull('messages.deleted_at');
    }

    /**
     * Apply filter to query.
     */
    private function applyFilter(Builder $query, string $filter): Builder
    {
        return match ($filter) {
            'unread' => $query->where('message_user_states.is_read', false),
            'starred' => $query->where('message_user_states.is_starred', true),
            'archived' => $query->where('message_user_states.is_archived', true),
            'failed' => $query->where('messages.status', 'failed'),
            'incomplete' => $query->where(function (Builder $q) {
                $q->whereJsonLength('messages.subject', 0)
                  ->orWhereNull('messages.subject');
            }),
            default => $query,
        };
    }

    /**
     * Apply search to query.
     */
    private function applySearch(Builder $query, User $user, string $search): Builder
    {
        $term = '%' . $search . '%';
        
        return $query->where(function (Builder $q) use ($term, $user) {
            $q->where('messages.subject', 'LIKE', $term)
              ->orWhereHas('message.sender', function (Builder $sq) use ($term) {
                  $sq->where('display_name', 'LIKE', $term)
                     ->orWhere('first_name', 'LIKE', $term)
                     ->orWhere('last_name', 'LIKE', $term);
              })
              ->orWhereHas('message.recipients', function (Builder $rq) use ($term) {
                  $rq->where('email', 'LIKE', $term)
                     ->orWhere('phone', 'LIKE', $term)
                     ->orWhere('name', 'LIKE', $term);
              })
              ->orWhereHas('message.labels', function (Builder $lq) use ($user, $term) {
                  $lq->where('user_id', $user->id)
                     ->where('label', 'LIKE', $term);
              });
        });
    }

    /**
     * Apply sorting to query.
     */
    private function applySorting(Builder $query, string $sort): Builder
    {
        return match ($sort) {
            'oldest' => $query->orderBy('messages.created_at', 'asc'),
            'alphabetical' => $query->orderBy('messages.subject', 'asc'),
            'recentlyDeleted' => $query->orderBy('message_user_states.trashed_at', 'desc'),
            'oldestDeleted' => $query->orderBy('message_user_states.trashed_at', 'asc'),
            'originalDate' => $query->orderBy('messages.sent_at', 'desc'),
            default => $query->orderBy('messages.created_at', 'desc'),
        };
    }

    // ── Single message ────────────────────────────────────────────────────

    /**
     * Return a single message with all relationships for the detail view.
     */
    public function getMessage(User $user, int $messageId): array
    {
        try {
            $state = MessageUserState::query()
                ->where('user_id', $user->id)
                ->where('message_id', $messageId)
                ->with([
                    'message.sender:id,first_name,last_name,display_name,email_hash',
                    'message.toRecipients',
                    'message.ccRecipients',
                    'message.bccRecipients',
                    'message.attachments',
                    'message.labels' => fn ($q) => $q->where('user_id', $user->id), // Remove Builder type hint
                    'message.parent.sender',
                ])
                ->firstOrFail();

            // Auto-mark as read when opened from inbox
            if ($state->folder === 'inbox' && !$state->is_read) {
                $state->markRead();
            }

            return [
                'state' => $state,
                'message' => $state->message,
            ];
            
        } catch (Throwable $e) {
            Log::error('Failed to get message', [
                'user_id' => $user->id,
                'message_id' => $messageId,
                'error' => $e->getMessage()
            ]);
            
            throw new RuntimeException('Message not found.', 404, $e);
        }
    }

    // ── Compose / Draft / Send ────────────────────────────────────────────

    /**
     * Create a new draft message or auto-save an existing one.
     */
    public function saveDraft(User $user, array $data): Message
    {
        try {
            return DB::transaction(function () use ($user, $data): Message {
                // Create or update the message record
                $message = $this->createOrUpdateDraft($user, $data);

                // Sync recipients and labels (skip addresses not on Custocare)
                $this->syncRecipients($message, $data, skipUnresolved: true);
                $this->syncLabels($message, $user->id, $data['labels'] ?? []);

                // Ensure user has a draft state
                $this->ensureDraftState($user, $message);

                return $message->load(['recipients', 'attachments', 'labels']);
            });
        } catch (MessageRecipientNotResolvedException|InvalidArgumentException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to save draft', [
                'user_id' => $user->id,
                'data' => $this->sanitizeData($data),
                'error' => $e->getMessage()
            ]);
            
            throw new RuntimeException('Failed to save draft. Please try again.', 0, $e);
        }
    }

    /**
     * Create or update a draft message.
     */
    private function createOrUpdateDraft(User $user, array $data): Message
    {
        $attributes = [
            'sender_id' => $user->id,
            'subject' => $data['subject'] ?? null,
            'body' => $data['body'] ?? null,
            'body_type' => $data['body_type'] ?? 'plain',
            'priority' => $data['priority'] ?? 'normal',
            'status' => 'draft',
            'scheduled_send_at' => $data['scheduled_send_at'] ?? null,
            'read_receipt_requested' => $data['read_receipt'] ?? false,
            'delivery_confirmation_requested' => $data['delivery_confirmation'] ?? false,
            'parent_id' => $data['parent_id'] ?? null,
            'last_auto_saved_at' => now(),
        ];

        if (isset($data['id'])) {
            $message = Message::where('id', $data['id'])
                ->where('sender_id', $user->id)
                ->where('status', 'draft')
                ->firstOrFail();
                
            $message->update($attributes);
            return $message;
        }

        return Message::create($attributes);
    }

    /**
     * Ensure user has a draft state for the message.
     */
    private function ensureDraftState(User $user, Message $message): void
    {
        MessageUserState::updateOrCreate(
            ['message_id' => $message->id, 'user_id' => $user->id],
            ['folder' => 'drafts']
        );
    }

    /**
     * Patch specific fields of an existing draft without replacing recipients.
     */
    public function updateDraft(User $user, int $messageId, array $data): Message
    {
        try {
            $message = $this->findDraft($user, $messageId);

            $allowedFields = [
                'subject', 'body', 'body_type', 'priority',
                'scheduled_send_at', 'read_receipt_requested',
                'delivery_confirmation_requested',
            ];

            $message->fill(array_intersect_key($data, array_flip($allowedFields)));
            $message->last_auto_saved_at = now();
            $message->save();

            // Optionally sync recipients/labels if provided
            if (array_key_exists('to', $data) || array_key_exists('cc', $data) || array_key_exists('bcc', $data)) {
                $message->recipients()->delete();
                $this->syncRecipients($message, $data, skipUnresolved: true);
            }

            if (isset($data['labels'])) {
                $this->syncLabels($message, $user->id, $data['labels']);
            }

            return $message->refresh()->load(['recipients', 'attachments', 'labels']);
        } catch (MessageRecipientNotResolvedException|InvalidArgumentException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to update draft', [
                'user_id' => $user->id,
                'message_id' => $messageId,
                'error' => $e->getMessage()
            ]);
            
            throw new RuntimeException('Failed to update draft. Please try again.', 0, $e);
        }
    }

    /**
     * Find a draft message.
     */
    private function findDraft(User $user, int $messageId): Message
    {
        return Message::where('id', $messageId)
            ->where('sender_id', $user->id)
            ->where('status', 'draft')
            ->firstOrFail();
    }

    /**
     * Send a message immediately or schedule it.
     */
    /**
     * @return array{message: Message, skipped_recipients: list<array{type: string, channel: string, value: string, message: string}>}
     */
    public function sendMessage(User $user, array $data): array
    {
        try {
            return DB::transaction(function () use ($user, $data): array {
                // Get or create message
                $message = $this->getOrCreateMessage($user, $data);

                // Set message data
                $scheduledAt = $data['scheduled_send_at'] ?? null;
                $this->prepareMessageForSending($message, $data, $scheduledAt);
                $message->save();

                // Sync recipients (skip unresolved) and labels
                $skipped = $this->syncRecipients($message, $data, skipUnresolved: true);
                $this->assertHasResolvedRecipients($message);
                $this->syncLabels($message, $user->id, $data['labels'] ?? []);

                // Create user states
                $this->createSenderState($user, $message, $scheduledAt);

                // Handle immediate sending
                if (!$scheduledAt) {
                    $this->processImmediateSend($message);
                }

                return [
                    'message' => $message->load(['recipients', 'attachments', 'labels']),
                    'skipped_recipients' => $skipped,
                ];
            });
        } catch (MessageRecipientNotResolvedException|InvalidArgumentException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Failed to send message', [
                'user_id' => $user->id,
                'data' => $this->sanitizeData($data),
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException('Failed to send message. Please try again.', 0, $e);
        }
    }

    /**
     * Get or create a message for sending.
     */
    private function getOrCreateMessage(User $user, array $data): Message
    {
        if (isset($data['message_id'])) {
            return Message::where('id', $data['message_id'])
                ->where('sender_id', $user->id)
                ->firstOrFail();
        }

        return new Message(['sender_id' => $user->id]);
    }

    /**
     * Prepare message for sending.
     */
    private function prepareMessageForSending(Message $message, array $data, ?string $scheduledAt): void
    {
        $message->fill([
            'subject' => $data['subject'],
            'body' => $data['body'],
            'body_type' => $data['body_type'] ?? 'plain',
            'priority' => $data['priority'] ?? 'normal',
            'read_receipt_requested' => $data['read_receipt'] ?? false,
            'delivery_confirmation_requested' => $data['delivery_confirmation'] ?? false,
            'parent_id' => $data['parent_id'] ?? null,
        ]);

        if ($scheduledAt) {
            $message->status = 'scheduled';
            $message->scheduled_send_at = $scheduledAt;
        } else {
            $message->status = 'sending';
            $message->sent_at = now();
        }
    }

    /**
     * Create sender state.
     */
    private function createSenderState(User $user, Message $message, ?string $scheduledAt): void
    {
        MessageUserState::updateOrCreate(
            ['message_id' => $message->id, 'user_id' => $user->id],
            [
                'folder' => $scheduledAt ? 'drafts' : 'sent',
                'is_read' => true,
                'read_at' => now(),
            ]
        );
    }

    /**
     * Process immediate send.
     */
    private function processImmediateSend(Message $message): void
    {
        $this->createRecipientInboxStates($message);
        $this->markMessageSent($message);
    }

    /**
     * Send a previously saved draft.
     */
    public function sendDraft(User $user, int $messageId): Message
    {
        try {
            $message = $this->findDraft($user, $messageId);

            return DB::transaction(function () use ($user, $message): Message {
                $message->status = 'sending';
                $message->sent_at = now();
                $message->save();

                // Flip sender state from drafts → sent
                MessageUserState::where('message_id', $message->id)
                    ->where('user_id', $user->id)
                    ->update(['folder' => 'sent', 'is_read' => true]);

                $this->createRecipientInboxStates($message);
                $this->markMessageSent($message);

                return $message->refresh()->load(['recipients', 'attachments', 'labels']);
            });
            
        } catch (Throwable $e) {
            Log::error('Failed to send draft', [
                'user_id' => $user->id,
                'message_id' => $messageId,
                'error' => $e->getMessage()
            ]);
            
            throw new RuntimeException('Failed to send draft. Please try again.', 0, $e);
        }
    }

    // ── Per-message actions ───────────────────────────────────────────────

    /**
     * Mark a message as read.
     */
    public function markRead(User $user, int $messageId): void
    {
        try {
            $state = $this->requireState($user, $messageId);
            $state->markRead();
        } catch (Throwable $e) {
            Log::error('Failed to mark message as read', [
                'user_id' => $user->id,
                'message_id' => $messageId,
                'error' => $e->getMessage()
            ]);
            
            throw new RuntimeException('Failed to mark message as read.', 0, $e);
        }
    }

    /**
     * Mark a message as unread.
     */
    public function markUnread(User $user, int $messageId): void
    {
        try {
            $state = $this->requireState($user, $messageId);
            $state->markUnread();
        } catch (Throwable $e) {
            Log::error('Failed to mark message as unread', [
                'user_id' => $user->id,
                'message_id' => $messageId,
                'error' => $e->getMessage()
            ]);
            
            throw new RuntimeException('Failed to mark message as unread.', 0, $e);
        }
    }

    /**
     * Toggle star flag.
     */
    public function toggleStar(User $user, int $messageId): bool
    {
        try {
            $state = $this->requireState($user, $messageId);
            $state->toggleStar();
            
            return $state->is_starred;
        } catch (Throwable $e) {
            Log::error('Failed to toggle star', [
                'user_id' => $user->id,
                'message_id' => $messageId,
                'error' => $e->getMessage()
            ]);
            
            throw new RuntimeException('Failed to toggle star.', 0, $e);
        }
    }

    /**
     * Archive a message.
     */
    public function archiveMessage(User $user, int $messageId): void
    {
        try {
            $state = $this->requireState($user, $messageId);
            $state->archive();
        } catch (Throwable $e) {
            Log::error('Failed to archive message', [
                'user_id' => $user->id,
                'message_id' => $messageId,
                'error' => $e->getMessage()
            ]);
            
            throw new RuntimeException('Failed to archive message.', 0, $e);
        }
    }

    /**
     * Unarchive a message.
     */
    public function unarchiveMessage(User $user, int $messageId): void
    {
        try {
            $state = $this->requireState($user, $messageId);
            $state->unarchive();
        } catch (Throwable $e) {
            Log::error('Failed to unarchive message', [
                'user_id' => $user->id,
                'message_id' => $messageId,
                'error' => $e->getMessage()
            ]);
            
            throw new RuntimeException('Failed to unarchive message.', 0, $e);
        }
    }

    /**
     * Move message to trash.
     */
    public function trashMessage(User $user, int $messageId): void
    {
        try {
            $state = $this->requireState($user, $messageId);
            $state->moveToTrash();
        } catch (Throwable $e) {
            Log::error('Failed to move message to trash', [
                'user_id' => $user->id,
                'message_id' => $messageId,
                'error' => $e->getMessage()
            ]);
            
            throw new RuntimeException('Failed to move message to trash.', 0, $e);
        }
    }

    /**
     * Restore message from trash.
     */
    public function restoreFromTrash(User $user, int $messageId): void
    {
        try {
            $state = $this->requireState($user, $messageId);
            $state->restore();
        } catch (Throwable $e) {
            Log::error('Failed to restore message from trash', [
                'user_id' => $user->id,
                'message_id' => $messageId,
                'error' => $e->getMessage()
            ]);
            
            throw new RuntimeException('Failed to restore message from trash.', 0, $e);
        }
    }

    /**
     * Permanently delete a message.
     */
    public function permanentDelete(User $user, int $messageId): void
    {
        try {
            DB::transaction(function () use ($user, $messageId): void {
                $state = $this->requireState($user, $messageId);
                $state->delete();

                $message = Message::find($messageId);
                if ($message && $message->sender_id === $user->id && $message->status === 'draft') {
                    $message->forceDelete();
                }
            });
        } catch (Throwable $e) {
            Log::error('Failed to permanently delete message', [
                'user_id' => $user->id,
                'message_id' => $messageId,
                'error' => $e->getMessage()
            ]);
            
            throw new RuntimeException('Failed to delete message.', 0, $e);
        }
    }

    /**
     * Empty trash for user.
     */
    public function emptyTrash(User $user): int
    {
        try {
            return DB::transaction(function () use ($user): int {
                $states = MessageUserState::query()
                    ->where('user_id', $user->id)
                    ->where('folder', 'trash')
                    ->get();

                $count = 0;
                foreach ($states as $state) {
                    $message = $state->message;
                    if ($message && $message->sender_id === $user->id && $message->status === 'draft') {
                        $message->forceDelete();
                    }
                    $state->delete();
                    $count++;
                }

                return $count;
            });
        } catch (Throwable $e) {
            Log::error('Failed to empty trash', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            
            throw new RuntimeException('Failed to empty trash.', 0, $e);
        }
    }

    // ── Bulk operations ───────────────────────────────────────────────────

    /**
     * Apply a single action to multiple messages at once.
     *
     * @param User $user
     * @param string $action
     * @param int[] $messageIds
     * @return int Number of messages affected
     * 
     * @throws InvalidArgumentException
     */
    public function bulkAction(User $user, string $action, array $messageIds): int
    {
        $count = 0;
        $supportedActions = [
            'trash', 'restore', 'star', 'archive', 'unarchive',
            'markRead', 'markUnread', 'permanentDelete'
        ];

        if (!in_array($action, $supportedActions)) {
            throw new InvalidArgumentException("Unknown action: {$action}");
        }

        foreach ($messageIds as $id) {
            try {
                match ($action) {
                    'trash' => $this->trashMessage($user, $id),
                    'restore' => $this->restoreFromTrash($user, $id),
                    'star' => $this->toggleStar($user, $id),
                    'archive' => $this->archiveMessage($user, $id),
                    'unarchive' => $this->unarchiveMessage($user, $id),
                    'markRead' => $this->markRead($user, $id),
                    'markUnread' => $this->markUnread($user, $id),
                    'permanentDelete' => $this->permanentDelete($user, $id),
                };
                $count++;
            } catch (Throwable $e) {
                Log::warning("Bulk action '{$action}' failed for message {$id}", [
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $count;
    }

    // ── Labels ────────────────────────────────────────────────────────────

    /**
     * Add a label to a message.
     */
    public function addLabel(User $user, int $messageId, string $label): void
    {
        try {
            MessageLabel::firstOrCreate([
                'message_id' => $messageId,
                'user_id' => $user->id,
                'label' => $this->normalizeLabel($label),
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to add label', [
                'user_id' => $user->id,
                'message_id' => $messageId,
                'label' => $label,
                'error' => $e->getMessage()
            ]);
            
            throw new RuntimeException('Failed to add label.', 0, $e);
        }
    }

    /**
     * Remove a label from a message.
     */
    public function removeLabel(User $user, int $messageId, string $label): void
    {
        try {
            MessageLabel::where('message_id', $messageId)
                ->where('user_id', $user->id)
                ->where('label', $this->normalizeLabel($label))
                ->delete();
        } catch (Throwable $e) {
            Log::error('Failed to remove label', [
                'user_id' => $user->id,
                'message_id' => $messageId,
                'label' => $label,
                'error' => $e->getMessage()
            ]);
            
            throw new RuntimeException('Failed to remove label.', 0, $e);
        }
    }

    /**
     * Normalize label string.
     */
    private function normalizeLabel(string $label): string
    {
        return mb_strtolower(trim($label));
    }

    // ── Attachments ───────────────────────────────────────────────────────

    /**
     * Upload and attach a file.
     */
    public function uploadAttachment(
        User $user,
        int $messageId,
        UploadedFile $file,
        string $disk = 'local'
    ): MessageAttachment {
        try {
            // Verify ownership
            $this->verifyMessageOwnership($user, $messageId);

            $storedName = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs(
                "messages/{$messageId}/attachments",
                $storedName,
                $disk
            );

            if (!$path) {
                throw new RuntimeException('Failed to store file.');
            }

            return MessageAttachment::create([
                'message_id' => $messageId,
                'original_name' => $file->getClientOriginalName(),
                'stored_name' => $storedName,
                'disk' => $disk,
                'path' => $path,
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
                'uploaded_by' => $user->id,
                'upload_status' => 'complete',
                'upload_progress' => 100,
            ]);
            
        } catch (Throwable $e) {
            Log::error('Failed to upload attachment', [
                'user_id' => $user->id,
                'message_id' => $messageId,
                'file_name' => $file->getClientOriginalName(),
                'error' => $e->getMessage()
            ]);
            
            throw new RuntimeException('Failed to upload attachment. Please try again.', 0, $e);
        }
    }

    /**
     * Remove an attachment.
     */
    public function removeAttachment(User $user, int $attachmentId): void
    {
        try {
            $attachment = MessageAttachment::with('message')
                ->findOrFail($attachmentId);

            if ($attachment->message->sender_id !== $user->id) {
                throw new RuntimeException('You do not own this attachment.');
            }

            $attachment->delete();
            
        } catch (Throwable $e) {
            Log::error('Failed to remove attachment', [
                'user_id' => $user->id,
                'attachment_id' => $attachmentId,
                'error' => $e->getMessage()
            ]);
            
            throw new RuntimeException('Failed to remove attachment.', 0, $e);
        }
    }

    /**
 * Download an attachment.
 * 
 * @param User $user
 * @param int $attachmentId
 * @return MessageAttachment
 * @throws RuntimeException
 */
public function downloadAttachment(User $user, int $attachmentId)
{
    try {
        $attachment = MessageAttachment::with('message')
            ->findOrFail($attachmentId);

        $hasAccess = MessageUserState::where('message_id', $attachment->message_id)
            ->where('user_id', $user->id)
            ->exists();

        $isSender = $attachment->message->sender_id === $user->id;

        if (!$isSender && !$hasAccess) {
            throw new RuntimeException('You do not have permission to download this attachment.');
        }

        if ($attachment->upload_status !== 'complete') {
            throw new RuntimeException('Attachment is not fully uploaded yet.');
        }

        if (!Storage::disk($attachment->disk)->exists($attachment->path)) {
            throw new RuntimeException('Attachment file not found.');
        }

        return $attachment;

    } catch (Throwable $e) {
        Log::error('Failed to download attachment', [
            'user_id' => $user->id,
            'attachment_id' => $attachmentId,
            'error' => $e->getMessage()
        ]);

        throw new RuntimeException('Failed to download attachment: ' . $e->getMessage(), 0, $e);
    }
}


    /**
     * Verify message ownership.
     */
    private function verifyMessageOwnership(User $user, int $messageId): void
    {
        Message::where('id', $messageId)
            ->where('sender_id', $user->id)
            ->firstOrFail();
    }

    // ── Stats ────────────────────────────────────────────────────────────

    /**
     * Get folder statistics.
     *
     * @return array<string, array{total: int, unread: int}>
     */
    public function getStats(User $user): array
    {
        $cacheKey = 'message_stats_' . $user->id;

        return Cache::remember($cacheKey, 10, function () use ($user) {
            try {
                $rows = MessageUserState::query()
                    ->where('user_id', $user->id)
                    ->join('messages', 'messages.id', '=', 'message_user_states.message_id')
                    ->whereNull('messages.deleted_at')
                    ->select(
                        'message_user_states.folder',
                        DB::raw('COUNT(*) as total'),
                        DB::raw('SUM(CASE WHEN message_user_states.is_read = 0 THEN 1 ELSE 0 END) as unread')
                    )
                    ->groupBy('message_user_states.folder')
                    ->get();

                $stats = [];
                foreach (self::FOLDERS as $folder) {
                    $stats[$folder] = ['total' => 0, 'unread' => 0];
                }

                foreach ($rows as $row) {
                    $stats[$row->folder] = [
                        'total' => (int) $row->total,
                        'unread' => (int) $row->unread,
                    ];
                }

                return $stats;

            } catch (Throwable $e) {
                Log::error('Failed to get message stats', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);

                return array_fill_keys(self::FOLDERS, ['total' => 0, 'unread' => 0]);
            }
        });
    }

    // ── Scheduled job ─────────────────────────────────────────────────────

    /**
     * Purge expired trash entries.
     */
    public function purgeExpiredTrash(): int
    {
        try {
            $expiredStates = MessageUserState::query()
                ->where('folder', 'trash')
                ->where('trash_expires_at', '<=', now())
                ->get();

            $count = 0;

            foreach ($expiredStates as $state) {
                $message = $state->message;
                if ($message && $message->sender_id === $state->user_id && $message->status === 'draft') {
                    $message->forceDelete();
                }
                $state->delete();
                $count++;
            }

            Log::info("Purged {$count} expired trash entries.");
            return $count;
            
        } catch (Throwable $e) {
            Log::error('Failed to purge expired trash', [
                'error' => $e->getMessage()
            ]);
            
            return 0;
        }
    }

    // ── Private helpers ───────────────────────────────────────────────────

    /**
     * Get message state or fail.
     */
    private function requireState(User $user, int $messageId): MessageUserState
    {
        return MessageUserState::where('user_id', $user->id)
            ->where('message_id', $messageId)
            ->firstOrFail();
    }

    /**
     * Sync recipients. When $skipUnresolved is true, non-Custocare addresses are skipped
     * and returned in the result instead of aborting the whole operation.
     *
     * @return list<array{type: string, channel: string, value: string, message: string}>
     */
    private function syncRecipients(Message $message, array $data, bool $skipUnresolved = false): array
    {
        $skipped = [];

        foreach (['to', 'cc', 'bcc'] as $type) {
            foreach (($data[$type] ?? []) as $recipient) {
                try {
                    $this->createRecipient($message, $recipient, $type);
                } catch (MessageRecipientNotResolvedException $e) {
                    if (!$skipUnresolved) {
                        throw $e;
                    }

                    $skipped[] = [
                        'type'    => $type,
                        'channel' => $e->channel,
                        'value'   => $e->normalizedValue,
                        'message' => $e->getMessage(),
                    ];
                }
            }
        }

        return $skipped;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function assertHasResolvedRecipients(Message $message): void
    {
        if ($message->recipients()->count() === 0) {
            throw new InvalidArgumentException(
                'At least one recipient must be registered on Custocare.',
            );
        }
    }

    /**
     * Normalize phone for hashing — same rules as patient registration
     * ({@see PatientController::createPatientByAdmin}).
     */
    private function normalizeRecipientPhone(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $trimmed = trim($value);
        $normalized = preg_replace('/(?!^\+)[^\d]/', '', $trimmed);

        return $normalized !== '' ? $normalized : null;
    }

    private function decryptUserEmail(User $user): ?string
    {
        if (!$user->email_encrypted) {
            return null;
        }
        try {
            return decrypt($user->email_encrypted);
        } catch (Throwable) {
            return null;
        }
    }

    private function decryptUserPhone(User $user): ?string
    {
        if (!$user->phone_encrypted) {
            return null;
        }
        try {
            return decrypt($user->phone_encrypted);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Create a single recipient (must resolve to an internal {@see User}).
     *
     * @throws MessageRecipientNotResolvedException
     */
    private function createRecipient(Message $message, array $recipient, string $type): void
    {
        $emailInput = isset($recipient['email']) ? strtolower(trim((string) $recipient['email'])) : '';
        $phoneInput = $this->normalizeRecipientPhone($recipient['phone'] ?? null);

        if ($emailInput !== '' && $phoneInput !== null) {
            throw new InvalidArgumentException('Provide only one of email or phone per recipient.');
        }
        if ($emailInput === '' && $phoneInput === null) {
            throw new InvalidArgumentException('Each recipient must include an email or a phone number.');
        }

        if ($emailInput !== '') {
            $internalUser = User::query()
                ->where('email_hash', hash('sha256', $emailInput))
                ->first();

            if (!$internalUser) {
                throw new MessageRecipientNotResolvedException('email', $emailInput);
            }

            $displayEmail = $this->decryptUserEmail($internalUser) ?: $emailInput;

            MessageRecipient::create([
                'message_id' => $message->id,
                'user_id' => $internalUser->id,
                'name' => $recipient['name'] ?? null,
                'email' => $displayEmail,
                'phone' => null,
                'type' => $type,
                'delivery_status' => 'pending',
            ]);

            return;
        }

        $internalUser = User::query()
            ->where('phone_hash', hash('sha256', $phoneInput))
            ->first();

        if (!$internalUser) {
            throw new MessageRecipientNotResolvedException('phone', $phoneInput);
        }

        $displayEmail = $this->decryptUserEmail($internalUser) ?? '';
        $displayPhone = $this->decryptUserPhone($internalUser) ?: $phoneInput;

        MessageRecipient::create([
            'message_id' => $message->id,
            'user_id' => $internalUser->id,
            'name' => $recipient['name'] ?? null,
            'email' => $displayEmail,
            'phone' => $displayPhone,
            'type' => $type,
            'delivery_status' => 'pending',
        ]);
    }
    /**
     * Sync labels.
     *
     * @param string[] $labels
     */
    private function syncLabels(Message $message, int $userId, array $labels): void
    {
        $normalizedLabels = array_map([$this, 'normalizeLabel'], $labels);

        // Remove labels not in the new set
        MessageLabel::where('message_id', $message->id)
            ->where('user_id', $userId)
            ->whereNotIn('label', $normalizedLabels)
            ->delete();

        // Add new labels
        foreach ($normalizedLabels as $label) {
            MessageLabel::firstOrCreate([
                'message_id' => $message->id,
                'user_id' => $userId,
                'label' => $label,
            ]);
        }
    }

    /**
     * Create inbox states for recipients.
     */
    private function createRecipientInboxStates(Message $message): void
    {
        $recipients = $message->recipients()
            ->whereIn('type', ['to', 'cc'])
            ->whereNotNull('user_id')
            ->get();

        foreach ($recipients as $recipient) {
            MessageUserState::firstOrCreate(
                ['message_id' => $message->id, 'user_id' => $recipient->user_id],
                [
                    'folder' => 'inbox',
                    'is_read' => false,
                ]
            );

            $recipient->markDelivered();
        }
    }
    /**
 * Move a sent message back to drafts.
 * Edge case for when a message needs to be recalled/edited.
 *
 * @param User $user
 * @param int $messageId
 * @return Message
 * @throws RuntimeException
 */
public function moveToDraft(User $user, int $messageId): Message
{
    try {
        return DB::transaction(function () use ($user, $messageId): Message {
            // Find the sent message
            $message = Message::where('id', $messageId)
                ->where('sender_id', $user->id)
                ->whereIn('status', ['sent', 'sending', 'failed'])
                ->firstOrFail();

            // Update message status
            $message->status = 'draft';
            $message->sent_at = null;
            $message->save();

            // Update user state
            MessageUserState::where('message_id', $message->id)
                ->where('user_id', $user->id)
                ->update([
                    'folder' => 'drafts',
                    'is_read' => false,
                    'read_at' => null,
                ]);

            // Remove recipient inbox states if they exist
            $recipientStates = MessageUserState::where('message_id', $message->id)
                ->where('user_id', '!=', $user->id)
                ->get();

            foreach ($recipientStates as $state) {
                $state->delete();
            }

            // Update recipient records
            MessageRecipient::where('message_id', $message->id)
                ->update(['delivery_status' => 'pending']);

            Log::info('Message moved back to drafts', [
                'message_id' => $message->id,
                'user_id' => $user->id
            ]);

            return $message->load(['recipients', 'attachments', 'labels']);
        });
        
    } catch (Throwable $e) {
        Log::error('Failed to move message to draft', [
            'user_id' => $user->id,
            'message_id' => $messageId,
            'error' => $e->getMessage()
        ]);
        
        throw new RuntimeException('Failed to move message to drafts. Please try again.', 0, $e);
    }
}

    /**
     * Mark message as sent.
     */
    private function markMessageSent(Message $message): void
    {
        $message->update(['status' => 'sent', 'sent_at' => now()]);

        MessageRecipient::where('message_id', $message->id)
            ->where('delivery_status', 'pending')
            ->update(['delivery_status' => 'sent']);
    }

    /**
     * Sanitize data for logging.
     */
    private function sanitizeData(array $data): array
    {
        $sensitiveFields = ['body', 'content'];
        $sanitized = $data;

        foreach ($sensitiveFields as $field) {
            if (isset($sanitized[$field])) {
                $sanitized[$field] = '[REDACTED]';
            }
        }

        return $sanitized;
    }

    /**
     * Validate folder parameter.
     */
    private function validateFolder(string $folder): string
    {
        if (!in_array($folder, self::FOLDERS)) {
            throw new InvalidArgumentException("Invalid folder: {$folder}");
        }
        
        return $folder;
    }

    /**
     * Validate filter parameter.
     */
    private function validateFilter(string $filter): string
    {
        if (!in_array($filter, self::FILTERS)) {
            throw new InvalidArgumentException("Invalid filter: {$filter}");
        }
        
        return $filter;
    }

    /**
     * Validate sort parameter.
     */
    private function validateSort(string $sort): string
    {
        if (!in_array($sort, self::SORTS)) {
            throw new InvalidArgumentException("Invalid sort: {$sort}");
        }
        
        return $sort;
    }
}