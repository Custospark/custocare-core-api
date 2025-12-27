<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;

class ClinicalEncounter extends Model
{
    use HasFactory, SoftDeletes, HasUuids;

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
        'encounter_uuid',
        'facility_id',
        'visit_id',
        'patient_id',
        'encounter_type',
        'primary_provider_staff_id',
        'supervising_provider_staff_id',
        'department_id',
        'subjective_assessment',
        'chief_complaints',
        'history_present_illness',
        'review_of_systems',
        'patient_concerns',
        'objective_findings',
        'vital_signs',
        'physical_examination',
        'laboratory_results',
        'imaging_results',
        'diagnostic_test_results',
        'assessment_diagnosis_codes',
        'clinical_impression',
        'differential_diagnoses',
        'severity_score',
        'risk_factors',
        'comorbidities',
        'plan_treatment_codes',
        'treatment_plan',
        'medications_prescribed',
        'procedures_planned',
        'referrals_ordered',
        'followup_instructions',
        'next_review_scheduled_at',
        'clinical_notes_structured',
        'clinical_notes_free_text',
        'provider_comments',
        'risk_flags',
        'safety_alerts',
        'requires_immediate_attention',
        'meets_quality_measures',
        'quality_measure_codes',
        'ai_assistance_used',
        'clinical_decision_support_alerts',
        'documentation_status',
        'documented_at',
        'signed_at',
        'electronic_signature_hash',
        'amended_from_encounter_id',
        'amendment_reason',
        'amended_at',
        'is_billable',
        'billing_code',
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
        'encounter_uuid' => 'string',
        'chief_complaints' => 'array',
        'review_of_systems' => 'array',
        'vital_signs' => 'array',
        'physical_examination' => 'array',
        'laboratory_results' => 'array',
        'imaging_results' => 'array',
        'diagnostic_test_results' => 'array',
        'assessment_diagnosis_codes' => 'array',
        'differential_diagnoses' => 'array',
        'risk_factors' => 'array',
        'comorbidities' => 'array',
        'plan_treatment_codes' => 'array',
        'medications_prescribed' => 'array',
        'procedures_planned' => 'array',
        'referrals_ordered' => 'array',
        'followup_instructions' => 'array',
        'clinical_notes_structured' => 'array',
        'risk_flags' => 'array',
        'safety_alerts' => 'array',
        'quality_measure_codes' => 'array',
        'clinical_decision_support_alerts' => 'array',
        'documented_at' => 'datetime',
        'signed_at' => 'datetime',
        'next_review_scheduled_at' => 'datetime',
        'amended_at' => 'datetime',
        'requires_immediate_attention' => 'boolean',
        'meets_quality_measures' => 'boolean',
        'ai_assistance_used' => 'boolean',
        'is_billable' => 'boolean',
        'severity_score' => 'integer',
        'metadata' => 'array',
        'deleted_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'electronic_signature_hash',
        'metadata',
    ];

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'encounter_uuid';
    }

    /**
     * Relationship: Visit associated with this encounter
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /**
     * Relationship: Patient associated with this encounter
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Relationship: Primary provider (staff) for this encounter
     */
    public function primaryProvider(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'primary_provider_staff_id');
    }

    /**
     * Relationship: Supervising provider (staff) for this encounter
     */
    public function supervisingProvider(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'supervising_provider_staff_id');
    }

    /**
     * Relationship: Department where encounter occurred
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Relationship: Facility where encounter occurred
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * Relationship: Original encounter that was amended
     */
    public function amendedFrom(): BelongsTo
    {
        return $this->belongsTo(ClinicalEncounter::class, 'amended_from_encounter_id');
    }

    /**
     * Relationship: Amendments made from this encounter
     */
    public function amendments(): HasOne
    {
        return $this->hasOne(ClinicalEncounter::class, 'amended_from_encounter_id');
    }

    /**
     * Relationship: Staff who created the encounter
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by_staff_id');
    }

    /**
     * Relationship: Staff who last updated the encounter
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'updated_by_staff_id');
    }

    /**
     * Accessor: Check if encounter is signed
     */
    protected function isSigned(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => !is_null($attributes['signed_at']),
        );
    }

    /**
     * Accessor: Check if encounter is completed
     */
    protected function isCompleted(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => 
                in_array($attributes['documentation_status'], ['completed', 'signed']),
        );
    }

    /**
     * Accessor: Check if encounter requires amendment
     */
    protected function requiresAmendment(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => 
                $attributes['documentation_status'] === 'amended' || 
                $attributes['documentation_status'] === 'corrected',
        );
    }

    /**
     * Scope: Signed encounters
     */
    public function scopeSigned($query)
    {
        return $query->whereNotNull('signed_at');
    }

    /**
     * Scope: Completed encounters
     */
    public function scopeCompleted($query)
    {
        return $query->whereIn('documentation_status', ['completed', 'signed']);
    }

    /**
     * Scope: Encounters requiring attention
     */
    public function scopeRequiringAttention($query)
    {
        return $query->where('requires_immediate_attention', true);
    }

    /**
     * Scope: Billable encounters
     */
    public function scopeBillable($query)
    {
        return $query->where('is_billable', true);
    }

    /**
     * Scope: Encounters by type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('encounter_type', $type);
    }

    /**
     * Scope: Encounters within date range
     */
    public function scopeDocumentedBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('documented_at', [$startDate, $endDate]);
    }

    /**
     * Boot method for model events
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->encounter_uuid)) {
                $model->encounter_uuid = (string) \Illuminate\Support\Str::orderedUuid();
            }
            if (empty($model->documented_at)) {
                $model->documented_at = now();
            }
        });
    }
}