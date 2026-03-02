<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int         $id
 * @property string      $uuid
 * @property int         $sender_id
 * @property string|null $subject
 * @property string|null $body
 * @property string      $body_type         plain|html|markdown
 * @property string      $status            draft|scheduled|sending|sent|failed
 * @property string      $priority          low|normal|high
 * @property Carbon|null $scheduled_send_at
 * @property Carbon|null $sent_at
 * @property bool        $read_receipt_requested
 * @property bool        $delivery_confirmation_requested
 * @property int|null    $parent_id
 * @property int|null    $thread_root_id
 * @property int|null    $word_count
 * @property int|null    $character_count
 * @property Carbon|null $last_auto_saved_at
 * @property array|null  $metadata
 * @property Carbon      $created_at
 * @property Carbon      $updated_at
 * @property Carbon|null $deleted_at
 *
 * @property-read User                                     $sender
 * @property-read \Illuminate\Database\Eloquent\Collection $recipients
 * @property-read \Illuminate\Database\Eloquent\Collection $toRecipients
 * @property-read \Illuminate\Database\Eloquent\Collection $ccRecipients
 * @property-read \Illuminate\Database\Eloquent\Collection $bccRecipients
 * @property-read \Illuminate\Database\Eloquent\Collection $attachments
 * @property-read \Illuminate\Database\Eloquent\Collection $labels
 * @property-read \Illuminate\Database\Eloquent\Collection $userStates
 * @property-read Message|null                             $parent
 * @property-read \Illuminate\Database\Eloquent\Collection $replies
 */
class Message extends Model
{
    use HasFactory, SoftDeletes;

    // ── Valid enumerations (re-used in validation) ──────────────────────
    public const STATUSES   = ['draft', 'scheduled', 'sending', 'sent', 'failed'];
    public const PRIORITIES = ['low', 'normal', 'high'];
    public const BODY_TYPES = ['plain', 'html', 'markdown'];

    /** Auto-purge window for trashed messages (days). */
    public const TRASH_EXPIRES_DAYS = 30;

    protected $fillable = [
        'uuid',
        'sender_id',
        'subject',
        'body',
        'body_type',
        'status',
        'priority',
        'scheduled_send_at',
        'sent_at',
        'read_receipt_requested',
        'delivery_confirmation_requested',
        'parent_id',
        'thread_root_id',
        'word_count',
        'character_count',
        'last_auto_saved_at',
        'metadata',
    ];

    protected $casts = [
        'scheduled_send_at'               => 'datetime',
        'sent_at'                          => 'datetime',
        'last_auto_saved_at'               => 'datetime',
        'read_receipt_requested'           => 'boolean',
        'delivery_confirmation_requested'  => 'boolean',
        'word_count'                       => 'integer',
        'character_count'                  => 'integer',
        'metadata'                         => 'array',
    ];

    // ── Boot ────────────────────────────────────────────────────────────

    protected static function boot(): void
    {
        parent::boot();

        // Auto-generate UUID on creation
        static::creating(function (self $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });

        // Recompute body metrics on every save
        static::saving(function (self $model): void {
            if ($model->isDirty('body') && $model->body !== null) {
                $plain               = strip_tags($model->body);
                $model->word_count   = str_word_count($plain);
                $model->character_count = mb_strlen($plain);
            }
        });
    }

    // ── Relationships ────────────────────────────────────────────────────

    /** The user who composed / owns this message. */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /** All recipients regardless of type. */
    public function recipients(): HasMany
    {
        return $this->hasMany(MessageRecipient::class);
    }

    /** Only "To" recipients. */
    public function toRecipients(): HasMany
    {
        return $this->hasMany(MessageRecipient::class)->where('type', 'to');
    }

    /** Only CC recipients. */
    public function ccRecipients(): HasMany
    {
        return $this->hasMany(MessageRecipient::class)->where('type', 'cc');
    }

    /** Only BCC recipients. */
    public function bccRecipients(): HasMany
    {
        return $this->hasMany(MessageRecipient::class)->where('type', 'bcc');
    }

    /** File attachments. */
    public function attachments(): HasMany
    {
        return $this->hasMany(MessageAttachment::class);
    }

    /** Per-user folder/read/star/archive states. */
    public function userStates(): HasMany
    {
        return $this->hasMany(MessageUserState::class);
    }

    /** Labels (tags) applied by any user. */
    public function labels(): HasMany
    {
        return $this->hasMany(MessageLabel::class);
    }

    /** The message this one is replying to. */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'parent_id');
    }

    /** Direct replies to this message. */
    public function replies(): HasMany
    {
        return $this->hasMany(Message::class, 'parent_id');
    }

    /** All messages in the same thread. */
    public function threadMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'thread_root_id');
    }

    // ── Local scopes ─────────────────────────────────────────────────────

    /** Limit to messages in a specific lifecycle status. */
    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /** Drafts only. */
    public function scopeDrafts(Builder $query): Builder
    {
        return $query->where('status', 'draft');
    }

    /** Messages ready to be dispatched (status=scheduled, time reached). */
    public function scopeReadyToSend(Builder $query): Builder
    {
        return $query->where('status', 'scheduled')
                     ->where('scheduled_send_at', '<=', now());
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * Build a short preview string (stripped of markup, max 160 chars).
     */
    public function getPreviewAttribute(): string
    {
        $plain = strip_tags((string) $this->body);
        return mb_substr(trim($plain), 0, 160);
    }

    /**
     * Compute the list of fields that are still incomplete for a draft.
     * Mirrors the `incompleteFields` concept used in the Draft component.
     *
     * @return string[]
     */
    public function getIncompleteFieldsAttribute(): array
    {
        $missing = [];

        if (empty(trim((string) $this->subject))) {
            $missing[] = 'subject';
        }

        if (empty(trim(strip_tags((string) $this->body)))) {
            $missing[] = 'body';
        }

        if ($this->recipients()->whereIn('type', ['to', 'cc'])->doesntExist()) {
            $missing[] = 'recipients';
        }

        return $missing;
    }

    /** Friendly "expires in X days" label when this draft is in trash. */
    public function trashExpiresLabel(Carbon $expiresAt): string
    {
        $days = (int) now()->diffInDays($expiresAt, false);

        if ($days < 0) {
            return 'Expired';
        }

        return $days === 0 ? 'Today' : "{$days} days";
    }

    /** Whether this message was sent (not draft, not failed). */
    public function isSent(): bool
    {
        return in_array($this->status, ['sent', 'delivered'], true);
    }

    /** Whether the draft is complete enough to send. */
    public function isReadyToSend(): bool
    {
        return count($this->incomplete_fields) === 0;
    }
}
