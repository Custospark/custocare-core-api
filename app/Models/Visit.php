<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;

class Visit extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Care-delivery module queue identifiers (aligned with frontend CareDeliveryWorkflow enum).
     *
     * @var list<string>
     */
    public const CARE_DELIVERY_WORKFLOWS = [
        'registration',
        'triage',
        'medical_records',
        'clinical',
        'laboratory',
        'pharmacy',
        'billing',
        'nursing',
        'imaging',
        'ambulance',
    ];

    /**
     * Target visit.current_phase when a visit is placed on a module queue.
     *
     * @var array<string, string>
     */
    public const CARE_DELIVERY_TARGET_PHASES = [
        'registration' => 'registration',
        'triage' => 'waiting_triage',
        'medical_records' => 'registration',
        'clinical' => 'waiting_provider',
        'laboratory' => 'diagnostic_tests',
        'pharmacy' => 'treatment',
        'billing' => 'billing',
        'nursing' => 'observation',
        'imaging' => 'procedures',
        'ambulance' => 'treatment',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'visit_uuid',
        'facility_id',
        'assigned_staff_id',
        'patient_id',
        'visit_type',
        'visit_subtype',
        'acuity_score',
        'chief_complaints',
        'symptoms_on_arrival',
        'patient_reported_history',
        'arrived_at',
        'registered_at',
        'mode_of_arrival',
        'accompanying_person',
        'referring_facility_id',
        'referring_provider_staff_id',
        'external_referral_id',
        'referral_reason',
        'current_department_id',
        'current_phase',
        'care_delivery_workflow',
        'waiting_since',
        'clinical_care_started_at',
        'clinical_care_ended_at',
        'expected_duration_minutes',
        'actual_duration_minutes',
        'scheduled_appointment_id',
        'is_walk_in',
        'scheduled_time',
        'insurance_preauth_id',
        'insurance_verification_status',
        'insurance_verified_at',
        'vital_signs_summary',
        'diagnosis_codes',
        'procedure_codes',
        'medications_administered',
        'discharged_at',
        'discharged_by_staff_id',
        'discharge_disposition',
        'discharge_instructions',
        'discharge_medications',
        'followup_scheduled_at',
        'followup_provider_staff_id',
        'sentinel_event_flagged',
        'safety_alerts',
        'requires_interpreter',
        'interpreter_language',
        'isolation_required',
        'isolation_type',
        'estimated_total_charges',
        'patient_estimated_responsibility',
        'payment_status',
        'status',
        'cancellation_reason',
        'cancelled_at',
        'created_by_staff_id',
        'updated_by_staff_id',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'visit_uuid' => 'string',
        'acuity_score' => 'integer',
        'chief_complaints' => 'array',
        'symptoms_on_arrival' => 'array',
        'arrived_at' => 'datetime',
        'registered_at' => 'datetime',
        'waiting_since' => 'datetime',
        'clinical_care_started_at' => 'datetime',
        'clinical_care_ended_at' => 'datetime',
        'expected_duration_minutes' => 'integer',
        'actual_duration_minutes' => 'integer',
        'is_walk_in' => 'boolean',
        'scheduled_time' => 'datetime',
        'insurance_verification_status' => 'string',
        'insurance_verified_at' => 'datetime',
        'vital_signs_summary' => 'array',
        'diagnosis_codes' => 'array',
        'procedure_codes' => 'array',
        'medications_administered' => 'array',
        'discharged_at' => 'datetime',
        'discharge_medications' => 'array',
        'followup_scheduled_at' => 'datetime',
        'sentinel_event_flagged' => 'boolean',
        'safety_alerts' => 'array',
        'requires_interpreter' => 'boolean',
        'isolation_required' => 'boolean',
        'estimated_total_charges' => 'decimal:2',
        'patient_estimated_responsibility' => 'decimal:2',
        'cancelled_at' => 'datetime',
        'deleted_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'deleted_at',
    ];

    /**
     * Get the route key for the model.
     *
     * @return string
     */
    public function getRouteKeyName(): string
    {
        return 'visit_uuid';
    }

    /**
     * Boot the model.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->visit_uuid)) {
                $model->visit_uuid = (string) \Illuminate\Support\Str::uuid();
            }
        });

        static::saving(function (Visit $visit) {
            $hasStaff = $visit->assigned_staff_id !== null && (int) $visit->assigned_staff_id > 0;

            if ($visit->isDirty('current_phase') && ! $visit->isTerminalVisitState()) {
                $wf = $visit->care_delivery_workflow;
                if ($wf !== null && $wf !== '' && isset(static::CARE_DELIVERY_TARGET_PHASES[$wf])) {
                    if ($visit->current_phase !== static::CARE_DELIVERY_TARGET_PHASES[$wf]) {
                        $visit->care_delivery_workflow = null;
                    }
                }
            }

            $hasWorkflow = $visit->care_delivery_workflow !== null && $visit->care_delivery_workflow !== '';

            if ($hasStaff) {
                $visit->care_delivery_workflow = null;
            } elseif ($hasWorkflow && ! $visit->isTerminalVisitState()) {
                $visit->assigned_staff_id = null;
                $visit->assigned_at = null;
                if (isset(static::CARE_DELIVERY_TARGET_PHASES[$visit->care_delivery_workflow])) {
                    $visit->current_phase = static::CARE_DELIVERY_TARGET_PHASES[$visit->care_delivery_workflow];
                }
            }
        });
    }

    /**
     * Terminal phases / finished visit records: do not auto-sync workflow↔phase
     * so completed visits can retain last `care_delivery_workflow` for module history.
     */
    public function isTerminalVisitState(): bool
    {
        $terminalPhases = [
            'discharged',
            'left_without_being_seen',
            'left_against_medical_advice',
            'transferred',
            'expired',
        ];

        if (in_array($this->current_phase, $terminalPhases, true)) {
            return true;
        }

        return in_array($this->status, ['completed', 'cancelled', 'no_show'], true);
    }

    /**
     * Relationship: Facility where visit occurred
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function facility()
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * Relationship: Patient for this visit
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Relationship: Current department
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function currentDepartment()
    {
        return $this->belongsTo(Department::class, 'current_department_id');
    }

    /**
     * Relationship: Referring facility
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function referringFacility()
    {
        return $this->belongsTo(Facility::class, 'referring_facility_id');
    }

    /**
     * Relationship: Referring provider
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function referringProvider()
    {
        return $this->belongsTo(Staff::class, 'referring_provider_staff_id');
    }

    /**
     * Relationship: Staff who discharged the patient
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function dischargedBy()
    {
        return $this->belongsTo(Staff::class, 'discharged_by_staff_id');
    }

    /**
     * Relationship: Follow-up provider
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function followupProvider()
    {
        return $this->belongsTo(Staff::class, 'followup_provider_staff_id');
    }

    /**
     * Relationship: Staff who created the visit
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function createdBy()
    {
        return $this->belongsTo(Staff::class, 'created_by_staff_id');
    }

    /**
     * Relationship: Staff who last updated the visit
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function updatedBy()
    {
        return $this->belongsTo(Staff::class, 'updated_by_staff_id');
    }

    /**
     * Scope: Active visits
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope: Completed visits
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope: Visit by facility
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $facilityId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByFacility($query, int $facilityId)
    {
        return $query->where('facility_id', $facilityId);
    }

    /**
     * Scope: Visit by patient
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $patientId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByPatient($query, int $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    /**
     * Scope: Visit by date range
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $startDate
     * @param string $endDate
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDateRange($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('arrived_at', [$startDate, $endDate]);
    }

    /**
     * Check if visit is currently active
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if visit is completed
     *
     * @return bool
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if visit is discharged
     *
     * @return bool
     */
    public function isDischarged(): bool
    {
        return !is_null($this->discharged_at);
    }

    /**
     * Calculate actual duration if not already set
     *
     * @return int|null
     */
    public function calculateActualDuration(): ?int
    {
        if ($this->clinical_care_started_at && $this->clinical_care_ended_at) {
            return $this->clinical_care_started_at->diffInMinutes($this->clinical_care_ended_at);
        }

        return null;
    }

    /**
     * Update the waiting time if patient is in waiting phase
     *
     * @return void
     */
    public function updateWaitingTime(): void
    {
        if (in_array($this->current_phase, ['waiting_triage', 'waiting_provider', 'awaiting_results'])) {
            if (!$this->waiting_since) {
                $this->waiting_since = now();
                $this->save();
            }
        } else {
            if ($this->waiting_since) {
                $this->waiting_since = null;
                $this->save();
            }
        }
    }
}