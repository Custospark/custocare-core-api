<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class VisitActor extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'facility_id',
        'visit_id',
        'staff_id',
        'role_at_time',
        'credential_snapshot_id',
        'participation_type',
        'participation_started_at',
        'participation_ended_at',
        'time_involvement_minutes',
        'department_id_at_time',
        'services_performed',
        'procedures_assisted',
        'is_billable_provider',
        'provider_charge_amount',
        'is_teaching_case',
        'supervising_staff_id',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'participation_started_at' => 'datetime',
        'participation_ended_at' => 'datetime',
        'services_performed' => 'array',
        'procedures_assisted' => 'array',
        'is_billable_provider' => 'boolean',
        'provider_charge_amount' => 'decimal:2',
        'is_teaching_case' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Participation types constants for better code readability
     */
    public const PARTICIPATION_TYPES = [
        'PRIMARY_PROVIDER' => 'primary_provider',
        'CONSULTING_PROVIDER' => 'consulting_provider',
        'ASSISTING_PROVIDER' => 'assisting_provider',
        'SUPERVISING_PROVIDER' => 'supervising_provider',
        'NURSE_PRIMARY' => 'nurse_primary',
        'NURSE_ASSISTING' => 'nurse_assisting',
        'TRIAGE_NURSE' => 'triage_nurse',
        'ANESTHESIOLOGIST' => 'anesthesiologist',
        'SURGICAL_ASSISTANT' => 'surgical_assistant',
        'PHARMACIST' => 'pharmacist',
        'TECHNICIAN' => 'technician',
        'THERAPIST' => 'therapist',
        'DOCUMENTING_STAFF' => 'documenting_staff',
        'ADMINISTRATIVE' => 'administrative',
        'OBSERVER_TRAINEE' => 'observer_trainee',
    ];

    /**
     * Get the visit associated with this actor participation.
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /**
     * Get the staff member associated with this participation.
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /**
     * Get the supervising staff if this is a teaching case.
     */
    public function supervisingStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'supervising_staff_id');
    }

    /**
     * Get the credential snapshot for this participation.
     */
    public function credentialSnapshot(): BelongsTo
    {
        return $this->belongsTo(StaffCredential::class, 'credential_snapshot_id');
    }

    /**
     * Get the facility associated with this participation.
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * Calculate time involvement automatically if not provided.
     */
    public function calculateTimeInvolvement(): ?int
    {
        if ($this->participation_ended_at && $this->participation_started_at) {
            return $this->participation_started_at->diffInMinutes($this->participation_ended_at);
        }

        return null;
    }

    /**
     * Check if this participation is currently active.
     */
    public function isActive(): bool
    {
        return $this->participation_started_at && !$this->participation_ended_at;
    }

    /**
     * Check if this participation involves billing.
     */
    public function isBillable(): bool
    {
        return $this->is_billable_provider && $this->provider_charge_amount > 0;
    }
}