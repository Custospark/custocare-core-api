<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Conversation extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'conversation_uuid',
        'facility_id',
        'conversation_type',
        'visit_id',
        'appointment_id',
        'department_code',
        'title',
        'contains_phi',
        'is_emergency',
        'status',
        'created_by_user_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'conversation_uuid' => 'string',
        'contains_phi' => 'boolean',
        'is_emergency' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the attributes that should be hidden for serialization.
     *
     * @return array<int, string>
     */
    protected function hidden(): array
    {
        return [
            'deleted_at',
        ];
    }

    /**
     * Conversation belongs to a facility.
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * Conversation may be associated with a visit.
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /**
     * Conversation may be associated with an appointment.
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * User who created the conversation.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Messages in this conversation.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Participants in this conversation.
     */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
            ->withPivot(['joined_at', 'left_at', 'role', 'is_admin'])
            ->withTimestamps();
    }

    /**
     * Scope to filter by conversation type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('conversation_type', $type);
    }

    /**
     * Scope to filter by facility.
     */
    public function scopeInFacility($query, int $facilityId)
    {
        return $query->where('facility_id', $facilityId);
    }

    /**
     * Scope to filter active conversations.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to filter emergency conversations.
     */
    public function scopeEmergency($query)
    {
        return $query->where('is_emergency', true);
    }

    /**
     * Check if conversation contains PHI.
     */
    public function hasPHI(): bool
    {
        return $this->contains_phi;
    }

    /**
     * Check if conversation is emergency.
     */
    public function isEmergency(): bool
    {
        return $this->is_emergency;
    }

    /**
     * Check if conversation is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if conversation is archived.
     */
    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }

    /**
     * Check if conversation is locked.
     */
    public function isLocked(): bool
    {
        return $this->status === 'locked';
    }

    /**
     * Generate a conversation UUID.
     */
    public static function generateUuid(): string
    {
        return (string) \Illuminate\Support\Str::uuid();
    }
}