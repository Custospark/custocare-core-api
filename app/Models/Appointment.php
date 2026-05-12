<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'int';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'appointment_uuid',
        'facility_id',
        'patient_id',
        'provider_staff_id',
        'department_id',
        'created_visit_id',
        'appointment_type',
        'scheduled_start_time',
        'scheduled_end_time',
        'duration_minutes',
        'reason_for_visit',
        'requested_services',
        'status',
        'confirmed_at',
        'checked_in_at',
        'cancellation_reason',
        'cancelled_at',
        'reminder_sent',
        'reminder_sent_at',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'appointment_uuid' => 'string',
        'scheduled_start_time' => 'datetime',
        'scheduled_end_time' => 'datetime',
        'confirmed_at' => 'datetime',
        'checked_in_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
        'reminder_sent' => 'boolean',
        'requested_services' => 'array',
        'metadata' => 'array',
    ];

    /**
     * Appointment type constants for type-safe usage
     */
    public const TYPE_NEW_PATIENT_CONSULTATION = 'new_patient_consultation';
    public const TYPE_FOLLOWUP_VISIT = 'followup_visit';
    public const TYPE_ANNUAL_PHYSICAL = 'annual_physical';
    public const TYPE_PROCEDURE = 'procedure';
    public const TYPE_DIAGNOSTIC_TEST = 'diagnostic_test';
    public const TYPE_THERAPY_SESSION = 'therapy_session';
    public const TYPE_TELEHEALTH = 'telehealth';
    public const TYPE_VACCINATION = 'vaccination';
    public const TYPE_CONSULTATION = 'consultation';

    /**
     * Status constants for type-safe usage
     */
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CHECKED_IN = 'checked_in';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_NO_SHOW = 'no_show';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_RESCHEDULED = 'rescheduled';

    /**
     * Get the route key for the model.
     *
     * @return string
     */
    public function getRouteKeyName(): string
    {
        return 'appointment_uuid';
    }

    /**
     * Relationship with facility
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * Relationship with patient
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Relationship with provider/staff
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'provider_staff_id');
    }

    /**
     * Relationship with created visit
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class, 'created_visit_id');
    }

    /**
     * Check if appointment is upcoming
     */
    public function isUpcoming(): bool
    {
        return in_array($this->status, [
            self::STATUS_SCHEDULED,
            self::STATUS_CONFIRMED,
        ]) && $this->scheduled_start_time > now();
    }

    /**
     * Check if appointment is in progress
     */
    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    /**
     * Check if appointment is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if appointment is cancellable
     */
    public function isCancellable(): bool
    {
        $nonCancellableStatuses = [
            self::STATUS_COMPLETED,
            self::STATUS_NO_SHOW,
            self::STATUS_CANCELLED,
        ];

        return !in_array($this->status, $nonCancellableStatuses) &&
               $this->scheduled_start_time > now()->addHours(2);
    }

    /**
     * Scope for upcoming appointments
     */
    public function scopeUpcoming($query)
    {
        return $query->whereIn('status', [
            self::STATUS_SCHEDULED,
            self::STATUS_CONFIRMED,
        ])->where('scheduled_start_time', '>', now());
    }

    /**
     * Scope for today's appointments
     */
    public function scopeToday($query)
    {
        return $query->whereDate('scheduled_start_time', today());
    }

    /**
     * Scope by facility
     */
    public function scopeByFacility($query, int $facilityId)
    {
        return $query->where('facility_id', $facilityId);
    }

    /**
     * Scope by patient
     */
    public function scopeByPatient($query, int $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    /**
     * Scope by provider
     */
    public function scopeByProvider($query, int $providerId)
    {
        return $query->where('provider_staff_id', $providerId);
    }

    /**
     * Generate a unique appointment UUID
     */
    public static function generateUuid(): string
    {
        return (string) \Illuminate\Support\Str::uuid();
    }
}