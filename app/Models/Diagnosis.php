<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Diagnosis extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'diagnoses';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'facility_id',
        'visit_id',
        'patient_id',
        'staff_id',
        'diagnosis_code',
        'diagnosis_description',
        'diagnosis_type',
        'certainty',
        'clinical_status',
        'clinical_notes',
        'onset_date',
        'abatement_date',
        'supporting_evidence',
        'diagnostic_criteria_met',
        'custom_fields',
        'coding_metadata',
        'verification_status',
        'verified_at',
        'verified_by',
        'dispute_reason',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'supporting_evidence' => 'array',
        'custom_fields' => 'array',
        'coding_metadata' => 'array',
        'onset_date' => 'date',
        'abatement_date' => 'date',
        'verified_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * The attributes that should have default values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'diagnosis_type' => 'primary',
        'certainty' => 'confirmed',
        'clinical_status' => 'active',
        'verification_status' => 'draft',
    ];

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Get the facility that owns the diagnosis.
     *
     * @return BelongsTo
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'facility_id');
    }

    /**
     * Get the visit associated with this diagnosis.
     *
     * @return BelongsTo
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class, 'visit_id');
    }

    /**
     * Get the patient who owns this diagnosis.
     *
     * @return BelongsTo
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    /**
     * Get the staff member (clinician) who made this diagnosis.
     *
     * @return BelongsTo
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    /**
     * Get the staff member who verified this diagnosis.
     *
     * @return BelongsTo
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'verified_by');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * Scope a query to only include diagnoses of a specific type.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('diagnosis_type', $type);
    }

    /**
     * Scope a query to only include primary diagnoses.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePrimary($query)
    {
        return $query->where('diagnosis_type', 'primary');
    }

    /**
     * Scope a query to only include secondary diagnoses.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSecondary($query)
    {
        return $query->where('diagnosis_type', 'secondary');
    }

    /**
     * Scope a query to only include active diagnoses.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('clinical_status', 'active');
    }

    /**
     * Scope a query to only include resolved diagnoses.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeResolved($query)
    {
        return $query->where('clinical_status', 'resolved');
    }

    /**
     * Scope a query to only include verified diagnoses.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeVerified($query)
    {
        return $query->where('verification_status', 'verified');
    }

    /**
     * Scope a query to only include diagnoses with a specific certainty level.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $certainty
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithCertainty($query, string $certainty)
    {
        return $query->where('certainty', $certainty);
    }

    /**
     * Scope a query to only include confirmed diagnoses.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeConfirmed($query)
    {
        return $query->where('certainty', 'confirmed');
    }

    /**
     * Scope a query to search by diagnosis code or description.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $search
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('diagnosis_code', 'like', '%' . $search . '%')
                ->orWhere('diagnosis_description', 'like', '%' . $search . '%');
        });
    }

    /**
     * Scope a query for a specific patient.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $patientId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForPatient($query, int $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    /**
     * Scope a query for a specific facility.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $facilityId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForFacility($query, int $facilityId)
    {
        return $query->where('facility_id', $facilityId);
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Check if the diagnosis is primary.
     *
     * @return bool
     */
    public function isPrimary(): bool
    {
        return $this->diagnosis_type === 'primary';
    }

    /**
     * Check if the diagnosis is secondary.
     *
     * @return bool
     */
    public function isSecondary(): bool
    {
        return $this->diagnosis_type === 'secondary';
    }

    /**
     * Check if the diagnosis is active.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->clinical_status === 'active';
    }

    /**
     * Check if the diagnosis is resolved.
     *
     * @return bool
     */
    public function isResolved(): bool
    {
        return $this->clinical_status === 'resolved';
    }

    /**
     * Check if the diagnosis is verified.
     *
     * @return bool
     */
    public function isVerified(): bool
    {
        return $this->verification_status === 'verified';
    }

    /**
     * Check if the diagnosis is disputed.
     *
     * @return bool
     */
    public function isDisputed(): bool
    {
        return $this->verification_status === 'disputed';
    }

    /**
     * Check if the diagnosis is confirmed.
     *
     * @return bool
     */
    public function isConfirmed(): bool
    {
        return $this->certainty === 'confirmed';
    }

    /**
     * Mark the diagnosis as verified.
     *
     * @param int $verifiedByStaffId
     * @return bool
     */
    public function verify(int $verifiedByStaffId): bool
    {
        return $this->update([
            'verification_status' => 'verified',
            'verified_at' => now(),
            'verified_by' => $verifiedByStaffId,
        ]);
    }

    /**
     * Mark the diagnosis as disputed.
     *
     * @param string|null $reason
     * @return bool
     */
    public function markAsDisputed(?string $reason = null): bool
    {
        return $this->update([
            'verification_status' => 'disputed',
            'dispute_reason' => $reason,
        ]);
    }

    /**
     * Mark the diagnosis as invalidated.
     *
     * @param string|null $reason
     * @return bool
     */
    public function invalidate(?string $reason = null): bool
    {
        return $this->update([
            'verification_status' => 'invalidated',
            'dispute_reason' => $reason,
        ]);
    }

    /**
     * Resolve the diagnosis.
     *
     * @param string|null $resolutionNotes
     * @return bool
     */
    public function resolve(?string $resolutionNotes = null): bool
    {
        $updateData = [
            'clinical_status' => 'resolved',
            'abatement_date' => now(),
        ];

        if ($resolutionNotes) {
            $updateData['clinical_notes'] = ($this->clinical_notes ? $this->clinical_notes . "\n\n" : '') .
                "Resolution Notes: " . $resolutionNotes;
        }

        return $this->update($updateData);
    }

    /**
     * Reactivate a resolved diagnosis.
     *
     * @return bool
     */
    public function reactivate(): bool
    {
        return $this->update([
            'clinical_status' => 'active',
            'abatement_date' => null,
        ]);
    }

    /**
     * Get the certainty level display text.
     *
     * @return string
     */
    public function getCertaintyTextAttribute(): string
    {
        return [
            'confirmed' => 'Confirmed',
            'probable' => 'Probable',
            'possible' => 'Possible',
            'rule_out' => 'Rule Out',
            'suspected' => 'Suspected',
            'uncertain' => 'Uncertain',
        ][$this->certainty] ?? $this->certainty;
    }

    /**
     * Get the clinical status display text.
     *
     * @return string
     */
    public function getClinicalStatusTextAttribute(): string
    {
        return [
            'active' => 'Active',
            'inactive' => 'Inactive',
            'resolved' => 'Resolved',
            'remission' => 'Remission',
            'chronic' => 'Chronic',
        ][$this->clinical_status] ?? $this->clinical_status;
    }

    /**
     * Get the diagnosis type display text.
     *
     * @return string
     */
    public function getDiagnosisTypeTextAttribute(): string
    {
        return [
            'primary' => 'Primary',
            'secondary' => 'Secondary',
            'differential' => 'Differential',
            'admitting' => 'Admitting',
            'discharge' => 'Discharge',
            'provisional' => 'Provisional',
        ][$this->diagnosis_type] ?? $this->diagnosis_type;
    }
}