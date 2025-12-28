<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MessageReceipt extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'message_id',
        'recipient_type',
        'recipient_id',
        'delivered_at',
        'read_at',
        'acknowledged_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    /**
     * Get the message associated with this receipt.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * Get the recipient model (polymorphic relationship).
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function recipient(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope to filter by recipient type.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRecipientType($query, string $type)
    {
        return $query->where('recipient_type', $type);
    }

    /**
     * Scope to filter by message ID.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $messageId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByMessage($query, int $messageId)
    {
        return $query->where('message_id', $messageId);
    }

    /**
     * Check if the receipt has been delivered.
     *
     * @return bool
     */
    public function isDelivered(): bool
    {
        return !is_null($this->delivered_at);
    }

    /**
     * Check if the receipt has been read.
     *
     * @return bool
     */
    public function isRead(): bool
    {
        return !is_null($this->read_at);
    }

    /**
     * Check if the receipt has been acknowledged.
     *
     * @return bool
     */
    public function isAcknowledged(): bool
    {
        return !is_null($this->acknowledged_at);
    }
}