<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ConversationParticipant extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'conversation_id',
        'participant_type',
        'participant_id',
        'role',
        'joined_at',
        'left_at',
        'is_muted',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
        'is_muted' => 'boolean',
    ];

    /**
     * Role constants for easy reference
     */
    public const ROLE_OWNER = 'owner';
    public const ROLE_MODERATOR = 'moderator';
    public const ROLE_MEMBER = 'member';
    public const ROLE_READ_ONLY = 'read_only';

    /**
     * Participant type constants
     */
    public const PARTICIPANT_STAFF = 'staff';
    public const PARTICIPANT_PATIENT = 'patient';

    /**
     * Get the conversation this participant belongs to.
     *
     * @return BelongsTo
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the participant model (polymorphic relationship).
     *
     * @return MorphTo
     */
    public function participant(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Check if participant is currently active in conversation.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return !empty($this->joined_at) && empty($this->left_at);
    }

    /**
     * Check if participant has left the conversation.
     *
     * @return bool
     */
    public function hasLeft(): bool
    {
        return !empty($this->left_at);
    }

    /**
     * Check if participant has a specific role.
     *
     * @param string $role
     * @return bool
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Check if participant is an owner or moderator.
     *
     * @return bool
     */
    public function isPrivileged(): bool
    {
        return in_array($this->role, [self::ROLE_OWNER, self::ROLE_MODERATOR]);
    }

    /**
     * Scope to get only active participants.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->whereNotNull('joined_at')
                    ->whereNull('left_at');
    }

    /**
     * Scope to get participants by conversation.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $conversationId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByConversation($query, int $conversationId)
    {
        return $query->where('conversation_id', $conversationId);
    }

    /**
     * Scope to get participants by type.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $participantType
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByParticipantType($query, string $participantType)
    {
        return $query->where('participant_type', $participantType);
    }
}