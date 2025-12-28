<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitCurrentState extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'visit_current_states';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'visit_id',
        'facility_id',
        'patient_id',
        'current_department_id',
        'current_phase',
        'waiting_since',
        'total_wait_minutes',
        'current_phase_duration_minutes',
        'next_scheduled_action_at',
        'next_action_type',
        'pending_tasks',
        'pending_tasks_count',
        'critical_alerts',
        'has_critical_alerts',
        'acuity_score',
        'staff_assigned_ids',
        'primary_provider_staff_id',
        'primary_nurse_staff_id',
        'recent_vitals_last_reading',
        'vitals_last_recorded_at',
        'active_orders',
        'active_orders_count',
        'estimated_completion_time',
        'estimated_minutes_remaining',
        'last_event_at',
        'last_event_id',
        'materialized_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'waiting_since' => 'datetime',
        'next_scheduled_action_at' => 'datetime',
        'vitals_last_recorded_at' => 'datetime',
        'estimated_completion_time' => 'datetime',
        'last_event_at' => 'datetime',
        'materialized_at' => 'datetime',
        'pending_tasks' => 'array',
        'critical_alerts' => 'array',
        'staff_assigned_ids' => 'array',
        'recent_vitals_last_reading' => 'array',
        'active_orders' => 'array',
        'has_critical_alerts' => 'boolean',
        'acuity_score' => 'integer',
        'total_wait_minutes' => 'integer',
        'current_phase_duration_minutes' => 'integer',
        'pending_tasks_count' => 'integer',
        'active_orders_count' => 'integer',
        'estimated_minutes_remaining' => 'integer',
    ];

    /**
     * The phases available for a visit.
     *
     * @var array<string, string>
     */
    public const PHASES = [
        'registration' => 'Registration',
        'waiting_triage' => 'Waiting for Triage',
        'triage' => 'Triage',
        'waiting_provider' => 'Waiting for Provider',
        'consultation' => 'Consultation',
        'diagnostic_tests' => 'Diagnostic Tests',
        'awaiting_results' => 'Awaiting Results',
        'treatment' => 'Treatment',
        'procedures' => 'Procedures',
        'observation' => 'Observation',
        'billing' => 'Billing',
        'discharge_pending' => 'Discharge Pending',
        'discharged' => 'Discharged',
    ];

    /**
     * Get the current phase as a human-readable label.
     *
     * @return string
     */
    public function getCurrentPhaseLabelAttribute(): string
    {
        return self::PHASES[$this->current_phase] ?? $this->current_phase;
    }

    /**
     * Check if the visit has critical alerts.
     *
     * @return bool
     */
    public function hasCriticalAlerts(): bool
    {
        return $this->has_critical_alerts && !empty($this->critical_alerts);
    }

    /**
     * Calculate current wait time in minutes.
     *
     * @return int|null
     */
    public function calculateCurrentWaitTime(): ?int
    {
        if (!$this->waiting_since) {
            return null;
        }

        return now()->diffInMinutes($this->waiting_since);
    }

    /**
     * Relationship with the visit.
     *
     * @return BelongsTo
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /**
     * Relationship with the facility.
     *
     * @return BelongsTo
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * Relationship with the patient.
     *
     * @return BelongsTo
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Relationship with the current department.
     *
     * @return BelongsTo
     */
    public function currentDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'current_department_id');
    }

    /**
     * Relationship with the primary provider.
     *
     * @return BelongsTo
     */
    public function primaryProvider(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'primary_provider_staff_id');
    }

    /**
     * Relationship with the primary nurse.
     *
     * @return BelongsTo
     */
    public function primaryNurse(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'primary_nurse_staff_id');
    }
}