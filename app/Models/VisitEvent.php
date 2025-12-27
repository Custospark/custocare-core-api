<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;

class VisitEvent extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'visit_events';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'event_uuid',
        'facility_id',
        'visit_id',
        'event_type',
        'event_payload',
        'payload_schema_version',
        'actor_type',
        'actor_id',
        'actor_identifier',
        'department_id_at_time',
        'system_component',
        'client_ip',
        'client_user_agent',
        'preceding_event_id',
        'integrity_hash',
        'event_occurred_at',
        'event_recorded_at',
        'processing_latency_ms',
        'created_at',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'event_payload' => AsArrayObject::class,
        'metadata' => AsArrayObject::class,
        'event_occurred_at' => 'datetime',
        'event_recorded_at' => 'datetime',
        'created_at' => 'datetime',
        'processing_latency_ms' => 'integer',
    ];

    /**
     * The event types that are considered clinical actions.
     *
     * @var array<string>
     */
    public const CLINICAL_EVENTS = [
        'triage_started',
        'triage_completed',
        'vitals_recorded',
        'consultation_started',
        'consultation_completed',
        'diagnostic_ordered',
        'diagnostic_completed',
        'medication_ordered',
        'medication_administered',
        'procedure_started',
        'procedure_completed',
    ];

    /**
     * The event types that indicate visit state changes.
     *
     * @var array<string>
     */
    public const VISIT_STATE_EVENTS = [
        'visit_created',
        'patient_arrived',
        'patient_registered',
        'visit_cancelled',
        'patient_left_ama',
        'patient_lwbs',
        'discharge_completed',
    ];

    /**
     * Get the preceding event in the chain.
     *
     * @return HasOne
     */
    public function precedingEvent(): HasOne
    {
        return $this->hasOne(VisitEvent::class, 'id', 'preceding_event_id');
    }

    /**
     * Get the visit associated with the event.
     *
     * @return BelongsTo
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /**
     * Check if this event is a clinical action.
     *
     * @return bool
     */
    public function isClinicalEvent(): bool
    {
        return in_array($this->event_type, self::CLINICAL_EVENTS);
    }

    /**
     * Check if this event changes visit state.
     *
     * @return bool
     */
    public function isVisitStateEvent(): bool
    {
        return in_array($this->event_type, self::VISIT_STATE_EVENTS);
    }

    /**
     * Generate integrity hash for the event.
     *
     * @param string|null $precedingHash
     * @return string
     */
    public function generateIntegrityHash(?string $precedingHash = null): string
    {
        $data = [
            'event_uuid' => $this->event_uuid,
            'visit_id' => $this->visit_id,
            'event_type' => $this->event_type,
            'event_payload' => json_encode($this->event_payload, JSON_UNESCAPED_SLASHES),
            'event_occurred_at' => $this->event_occurred_at->toISOString(),
            'preceding_hash' => $precedingHash,
        ];

        return hash('sha256', json_encode($data, JSON_UNESCAPED_SLASHES));
    }

    /**
     * Verify the integrity hash of this event.
     *
     * @param string|null $precedingHash
     * @return bool
     */
    public function verifyIntegrityHash(?string $precedingHash = null): bool
    {
        return hash_equals(
            $this->generateIntegrityHash($precedingHash),
            $this->integrity_hash
        );
    }

    /**
     * Scope a query to only include events of a specific type.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string|array $eventTypes
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOfType(\Illuminate\Database\Eloquent\Builder $query, $eventTypes): \Illuminate\Database\Eloquent\Builder
    {
        $eventTypes = is_array($eventTypes) ? $eventTypes : [$eventTypes];
        return $query->whereIn('event_type', $eventTypes);
    }

    /**
     * Scope a query to only include events within a time range.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Carbon\Carbon $from
     * @param \Carbon\Carbon $to
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeBetweenDates(
        \Illuminate\Database\Eloquent\Builder $query,
        \Carbon\Carbon $from,
        \Carbon\Carbon $to
    ): \Illuminate\Database\Eloquent\Builder {
        return $query->whereBetween('event_occurred_at', [$from, $to]);
    }

    /**
     * Scope a query to only include events for a specific facility.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $facilityId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForFacility(\Illuminate\Database\Eloquent\Builder $query, int $facilityId): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('facility_id', $facilityId);
    }

    /**
     * Scope a query to only include events for a specific visit.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $visitId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForVisit(\Illuminate\Database\Eloquent\Builder $query, int $visitId): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('visit_id', $visitId);
    }
}