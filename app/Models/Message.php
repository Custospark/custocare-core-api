<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * App\Models\Message
 *
 * @property int $id
 * @property string $message_uuid
 * @property int $conversation_id
 * @property string $sender_type
 * @property int|null $sender_id
 * @property string $message_type
 * @property string|null $content_encrypted
 * @property string $content_hash
 * @property bool $contains_phi
 * @property bool $is_clinical
 * @property bool $requires_acknowledgement
 * @property int|null $parent_message_id
 * @property string $delivery_status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $edited_at
 * @property int|null $edited_by_user_id
 */
class Message extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'message_uuid',
        'conversation_id',
        'sender_type',
        'sender_id',
        'message_type',
        'content_encrypted',
        'content_hash',
        'contains_phi',
        'is_clinical',
        'requires_acknowledgement',
        'parent_message_id',
        'delivery_status',
        'edited_at',
        'edited_by_user_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'contains_phi' => 'boolean',
        'is_clinical' => 'boolean',
        'requires_acknowledgement' => 'boolean',
        'edited_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'content_encrypted', // Sensitive data
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Message $message) {
            if (empty($message->message_uuid)) {
                $message->message_uuid = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    /**
     * Get the conversation that owns the message.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the sender of the message (polymorphic).
     */
    public function sender(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the parent message.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'parent_message_id');
    }

    /**
     * Get the user who edited the message.
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by_user_id');
    }

    /**
     * Get the child messages (replies).
     */
    public function replies()
    {
        return $this->hasMany(Message::class, 'parent_message_id');
    }

    /**
     * Scope messages by conversation.
     */
    public function scopeInConversation($query, $conversationId)
    {
        return $query->where('conversation_id', $conversationId);
    }

    /**
     * Scope clinical messages.
     */
    public function scopeClinical($query)
    {
        return $query->where('is_clinical', true);
    }

    /**
     * Scope non-PHI messages.
     */
    public function scopeNonPHI($query)
    {
        return $query->where('contains_phi', false);
    }

    /**
     * Check if message requires acknowledgement.
     */
    public function requiresAcknowledgement(): bool
    {
        return $this->requires_acknowledgement;
    }

    /**
     * Check if message is delivered.
     */
    public function isDelivered(): bool
    {
        return $this->delivery_status === 'delivered';
    }

    /**
     * Mark message as delivered.
     */
    public function markAsDelivered(): bool
    {
        return $this->update(['delivery_status' => 'delivered']);
    }

    /**
     * Mark message as sent.
     */
    public function markAsSent(): bool
    {
        return $this->update(['delivery_status' => 'sent']);
    }
}