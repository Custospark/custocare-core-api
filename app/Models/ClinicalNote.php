<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicalNote extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'clinical_notes';

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
        'subjective',
        'objective',
        'assessment',
        'plan',
        'review_of_systems',
        'past_medical_history',
        'note_type',
        'note_status',
        'noted_at',
        'signature',
        'custom_fields',
        'structured_data',
        'parent_note_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'custom_fields' => 'array',
        'structured_data' => 'array',
        'noted_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * The attributes that should have default values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'note_type' => 'progress',
        'note_status' => 'draft',
    ];

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Get the facility that owns the clinical note.
     *
     * @return BelongsTo
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'facility_id');
    }

    /**
     * Get the visit associated with this clinical note.
     *
     * @return BelongsTo
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class, 'visit_id');
    }

    /**
     * Get the patient who owns this clinical note.
     *
     * @return BelongsTo
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    /**
     * Get the staff member (clinician) who created this note.
     *
     * @return BelongsTo
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    /**
     * Get the parent note (for amended notes).
     *
     * @return BelongsTo
     */
    public function parentNote(): BelongsTo
    {
        return $this->belongsTo(ClinicalNote::class, 'parent_note_id');
    }

    /**
     * Get the child notes (amendments to this note).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function childNotes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ClinicalNote::class, 'parent_note_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * Scope a query to only include notes of a specific type.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('note_type', $type);
    }

    /**
     * Scope a query to only include notes with a specific status.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $status
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('note_status', $status);
    }

    /**
     * Scope a query to only include final notes (not drafts).
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFinal($query)
    {
        return $query->where('note_status', 'final');
    }

    /**
     * Scope a query to only include draft notes.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDraft($query)
    {
        return $query->where('note_status', 'draft');
    }

    /**
     * Scope a query to only include notes for a specific patient.
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
     * Scope a query to only include notes for a specific visit.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $visitId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForVisit($query, int $visitId)
    {
        return $query->where('visit_id', $visitId);
    }

    /**
     * Scope a query to only include notes for a specific facility.
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
     * Check if the note is a draft.
     *
     * @return bool
     */
    public function isDraft(): bool
    {
        return $this->note_status === 'draft';
    }

    /**
     * Check if the note is final.
     *
     * @return bool
     */
    public function isFinal(): bool
    {
        return $this->note_status === 'final';
    }

    /**
     * Check if the note is cancelled.
     *
     * @return bool
     */
    public function isCancelled(): bool
    {
        return $this->note_status === 'cancelled';
    }

    /**
     * Check if the note is amended.
     *
     * @return bool
     */
    public function isAmended(): bool
    {
        return $this->note_status === 'amended';
    }

    /**
     * Check if the note has a parent (is an amendment).
     *
     * @return bool
     */
    public function isAmendment(): bool
    {
        return !is_null($this->parent_note_id);
    }

    /**
     * Mark the note as final.
     *
     * @return bool
     */
    public function markAsFinal(): bool
    {
        return $this->update(['note_status' => 'final']);
    }

    /**
     * Mark the note as cancelled.
     *
     * @return bool
     */
    public function markAsCancelled(): bool
    {
        return $this->update(['note_status' => 'cancelled']);
    }

    /**
     * Create an amended version of this note.
     *
     * @param array $newData
     * @param int $staffId
     * @return self
     */
    public function amend(array $newData, int $staffId): self
    {
        // Mark current note as amended
        $this->update(['note_status' => 'amended']);

        // Create new note as child
        $amendedNote = $this->replicate();
        $amendedNote->fill(array_merge($newData, [
            'parent_note_id' => $this->id,
            'staff_id' => $staffId,
            'note_status' => 'final',
            'note_type' => $this->note_type,
        ]));
        $amendedNote->save();

        return $amendedNote;
    }

    /**
     * Get the full note text (combined subjective, objective, assessment, plan).
     *
     * @return string
     */
    public function getFullNoteTextAttribute(): string
    {
        $parts = [];

        if ($this->subjective) {
            $parts[] = "SUBJECTIVE:\n{$this->subjective}";
        }
        if ($this->objective) {
            $parts[] = "OBJECTIVE:\n{$this->objective}";
        }
        if ($this->assessment) {
            $parts[] = "ASSESSMENT:\n{$this->assessment}";
        }
        if ($this->plan) {
            $parts[] = "PLAN:\n{$this->plan}";
        }

        return implode("\n\n", $parts);
    }
}