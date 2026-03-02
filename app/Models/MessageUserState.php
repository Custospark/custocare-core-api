<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Per-user, per-message mailbox state.
 * Determines which folder a message appears in for each user and
 * stores UI flags (read, starred, archived) independently per user.
 *
 * @property int         $id
 * @property int         $message_id
 * @property int         $user_id
 * @property string      $folder             inbox|sent|drafts|archive|trash
 * @property string|null $original_folder    Previous folder before trash move
 * @property bool        $is_read
 * @property Carbon|null $read_at
 * @property bool        $is_starred
 * @property Carbon|null $starred_at
 * @property bool        $is_archived
 * @property Carbon|null $archived_at
 * @property Carbon|null $trashed_at
 * @property Carbon|null $trash_expires_at
 * @property Carbon      $created_at
 * @property Carbon      $updated_at
 */
class MessageUserState extends Model
{
    public const FOLDERS = ['inbox', 'sent', 'drafts', 'archive', 'trash'];

    protected $fillable = [
        'message_id',
        'user_id',
        'folder',
        'original_folder',
        'is_read',
        'read_at',
        'is_starred',
        'starred_at',
        'is_archived',
        'archived_at',
        'trashed_at',
        'trash_expires_at',
    ];

    protected $casts = [
        'is_read'         => 'boolean',
        'read_at'         => 'datetime',
        'is_starred'      => 'boolean',
        'starred_at'      => 'datetime',
        'is_archived'     => 'boolean',
        'archived_at'     => 'datetime',
        'trashed_at'      => 'datetime',
        'trash_expires_at'=> 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── State transition helpers ──────────────────────────────────────────

    /**
     * Mark the message as read for this user.
     */
    public function markRead(): void
    {
        if (! $this->is_read) {
            $this->update(['is_read' => true, 'read_at' => now()]);
        }
    }

    /**
     * Mark the message as unread for this user.
     */
    public function markUnread(): void
    {
        $this->update(['is_read' => false, 'read_at' => null]);
    }

    /**
     * Toggle the star flag.
     */
    public function toggleStar(): void
    {
        $this->update([
            'is_starred' => ! $this->is_starred,
            'starred_at' => $this->is_starred ? null : now(),
        ]);
    }

    /**
     * Move the message to the archive folder for this user.
     */
    public function archive(): void
    {
        $this->update([
            'folder'       => 'archive',
            'is_archived'  => true,
            'archived_at'  => now(),
        ]);
    }

    /**
     * Move the message back out of archive to the original folder.
     */
    public function unarchive(): void
    {
        // Default to inbox if we can't determine the prior folder
        $prior = in_array($this->original_folder, self::FOLDERS, true)
            ? $this->original_folder
            : 'inbox';

        $this->update([
            'folder'          => $prior,
            'is_archived'     => false,
            'archived_at'     => null,
            'original_folder' => null,
        ]);
    }

    /**
     * Move the message to trash for this user.
     * Preserves the current folder so it can be restored later.
     */
    public function moveToTrash(): void
    {
        $this->update([
            'original_folder' => $this->folder,
            'folder'          => 'trash',
            'trashed_at'      => now(),
            'trash_expires_at'=> now()->addDays(Message::TRASH_EXPIRES_DAYS),
        ]);
    }

    /**
     * Restore this message from trash to its original folder.
     */
    public function restore(): void
    {
        $prior = $this->original_folder ?? 'inbox';

        $this->update([
            'folder'          => $prior,
            'original_folder' => null,
            'trashed_at'      => null,
            'trash_expires_at'=> null,
        ]);
    }

    // ── Derived helpers ───────────────────────────────────────────────────

    /**
     * Whether this trashed message can still be restored.
     */
    public function isRecoverable(): bool
    {
        return $this->folder === 'trash'
            && ($this->trash_expires_at === null || $this->trash_expires_at->isFuture());
    }

    /**
     * Human-readable label for how long before auto-purge.
     * Mirrors the Trash component's `expiresIn` string.
     */
    public function expiresInLabel(): string
    {
        if ($this->trash_expires_at === null) {
            return 'Never';
        }

        $days = (int) now()->diffInDays($this->trash_expires_at, false);

        if ($days < 0) {
            return 'Expired';
        }

        return $days === 0 ? 'Today' : "{$days} days";
    }
}
