<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int         $id
 * @property int         $message_id
 * @property int|null    $user_id
 * @property string|null $name
 * @property string      $email
 * @property string      $type              to|cc|bcc
 * @property string      $delivery_status   pending|sent|delivered|failed|read
 * @property Carbon|null $delivered_at
 * @property Carbon|null $read_at
 * @property Carbon      $created_at
 * @property Carbon      $updated_at
 */
class MessageRecipient extends Model
{
    public const TYPES = ['to', 'cc', 'bcc'];

    public const DELIVERY_STATUSES = ['pending', 'sent', 'delivered', 'failed', 'read'];

    protected $fillable = [
        'message_id',
        'user_id',
        'name',
        'email',
        'type',
        'delivery_status',
        'delivered_at',
        'read_at',
    ];

    protected $casts = [
        'delivered_at' => 'datetime',
        'read_at'      => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────

    /** The message this recipient belongs to. */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * The internal user account for this recipient (null for external emails).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * Mark delivery as successful and record the timestamp.
     */
    public function markDelivered(): void
    {
        $this->update([
            'delivery_status' => 'delivered',
            'delivered_at'    => now(),
        ]);
    }

    /**
     * Mark the message as read by this recipient (for read receipts).
     */
    public function markRead(): void
    {
        if ($this->read_at === null) {
            $this->update([
                'delivery_status' => 'read',
                'read_at'         => now(),
            ]);
        }
    }

    /**
     * Mark the delivery as failed.
     */
    public function markFailed(): void
    {
        $this->update(['delivery_status' => 'failed']);
    }

    /**
     * Human-readable display string: "Jane Doe <jane@example.com>"
     */
    public function toDisplayString(): string
    {
        return $this->name
            ? "{$this->name} <{$this->email}>"
            : $this->email;
    }
}
