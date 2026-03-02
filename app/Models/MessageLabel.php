<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single label (tag) that a specific user has applied to a message.
 * Labels are per-user — two users can label the same message differently.
 *
 * @property int    $id
 * @property int    $message_id
 * @property int    $user_id
 * @property string $label
 */
class MessageLabel extends Model
{
    // Only a created_at timestamp (no updated_at — labels are add/remove).
    public const UPDATED_AT = null;

    protected $fillable = [
        'message_id',
        'user_id',
        'label',
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

    // ── Boot ─────────────────────────────────────────────────────────────

    protected static function boot(): void
    {
        parent::boot();

        // Normalise label to lowercase on save
        static::saving(function (self $model): void {
            $model->label = mb_strtolower(trim($model->label));
        });
    }
}
