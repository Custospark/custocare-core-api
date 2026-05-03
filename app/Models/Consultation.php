<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Consultation extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'consultations';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'facility_id',
        'visit_id',
        'patient_id',
        'requesting_staff_id',
        'consultant_staff_id',
        'specialty_required',
        'consultation_type',
        'priority',
        'clinical_question',
        'background_information',
        'attached_documents',
        'findings',
        'recommendations',
        'recommended_orders',
        'consultant_notes',
        'request_status',
        'requested_at',
        'responded_at',
        'completed_at',
        'decline_reason',
        'cancellation_reason',
        'scheduled_for',
        'duration_minutes',
        'location',
        'requires_followup',
        'followup_by',
        'followup_instructions',
        'custom_fields',
        'satisfaction_metrics',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'attached_documents' => 'array',
        'recommended_orders' => 'array',
        'custom_fields' => 'array',
        'satisfaction_metrics' => 'array',
        'requested_at' => 'datetime',
        'responded_at' => 'datetime',
        'completed_at' => 'datetime',
        'scheduled_for' => 'datetime',
        'followup_by' => 'datetime',
        'deleted_at' => 'datetime',
        'requires_followup' => 'boolean', 

    ];

    /**
     * The attributes that should have default values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'consultation_type' => 'in_person',
        'priority' => 'routine',
        'request_status' => 'pending',
        'duration_minutes' => 30,
        'requires_followup' => false,
    ];

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Get the facility that owns the consultation.
     *
     * @return BelongsTo
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'facility_id');
    }

    /**
     * Get the visit associated with this consultation.
     *
     * @return BelongsTo
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class, 'visit_id');
    }

    /**
     * Get the patient who owns this consultation.
     *
     * @return BelongsTo
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    /**
     * Get the staff member who requested the consultation.
     *
     * @return BelongsTo
     */
    public function requestingStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'requesting_staff_id');
    }

    /**
     * Get the staff member (consultant) assigned to this consultation.
     *
     * @return BelongsTo
     */
    public function consultantStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'consultant_staff_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * Scope a query to only include consultations for a specific patient.
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
     * Scope a query to only include consultations for a specific visit.
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
     * Scope a query to only include consultations for a specific facility.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $facilityId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForFacility($query, int $facilityId)
    {
        return $query->where('facility_id', $facilityId);
    }

    /**
     * Scope a query to only include consultations with a specific status.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $status
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('request_status', $status);
    }

    /**
     * Scope a query to only include pending consultations.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePending($query)
    {
        return $query->where('request_status', 'pending');
    }

    /**
     * Scope a query to only include accepted consultations.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAccepted($query)
    {
        return $query->where('request_status', 'accepted');
    }

    /**
     * Scope a query to only include completed consultations.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCompleted($query)
    {
        return $query->where('request_status', 'completed');
    }

    /**
     * Scope a query to only include consultations with a specific priority.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $priority
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope a query to only include urgent or emergent consultations.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeUrgent($query)
    {
        return $query->whereIn('priority', ['urgent', 'emergent']);
    }

    /**
     * Scope a query to only include consultations for a specific specialty.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $specialty
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForSpecialty($query, string $specialty)
    {
        return $query->where('specialty_required', $specialty);
    }

    /**
     * Scope a query to only include consultations assigned to a specific consultant.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $consultantStaffId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForConsultant($query, int $consultantStaffId)
    {
        return $query->where('consultant_staff_id', $consultantStaffId);
    }

    /**
     * Scope a query to only include consultations requested by a specific staff member.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $requestingStaffId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRequestedBy($query, int $requestingStaffId)
    {
        return $query->where('requesting_staff_id', $requestingStaffId);
    }

    /**
     * Scope a query to only include consultations scheduled for a date range.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $startDate
     * @param string $endDate
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeScheduledBetween($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('scheduled_for', [$startDate, $endDate]);
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Check if the consultation is pending.
     *
     * @return bool
     */
    public function isPending(): bool
    {
        return $this->request_status === 'pending';
    }

    /**
     * Check if the consultation is accepted.
     *
     * @return bool
     */
    public function isAccepted(): bool
    {
        return $this->request_status === 'accepted';
    }

    /**
     * Check if the consultation is declined.
     *
     * @return bool
     */
    public function isDeclined(): bool
    {
        return $this->request_status === 'declined';
    }

    /**
     * Check if the consultation is completed.
     *
     * @return bool
     */
    public function isCompleted(): bool
    {
        return $this->request_status === 'completed';
    }

    /**
     * Check if the consultation is cancelled.
     *
     * @return bool
     */
    public function isCancelled(): bool
    {
        return $this->request_status === 'cancelled';
    }

    /**
     * Check if the consultation is urgent.
     *
     * @return bool
     */
    public function isUrgent(): bool
    {
        return in_array($this->priority, ['urgent', 'emergent']);
    }

    /**
     * Check if the consultation requires follow-up.
     *
     * @return bool
     */
    public function requiresFollowup(): bool
    {
        return $this->requires_followup;
    }

    /**
     * Accept the consultation request.
     *
     * @param int $consultantStaffId
     * @return bool
     */
    public function accept(int $consultantStaffId): bool
    {
        return $this->update([
            'request_status' => 'accepted',
            'consultant_staff_id' => $consultantStaffId,
            'responded_at' => now(),
        ]);
    }

    /**
     * Decline the consultation request.
     *
     * @param string|null $reason
     * @return bool
     */
    public function decline(?string $reason = null): bool
    {
        return $this->update([
            'request_status' => 'declined',
            'decline_reason' => $reason,
            'responded_at' => now(),
        ]);
    }

    /**
     * Mark the consultation as completed.
     *
     * @param array|null $findings
     * @param array|null $recommendations
     * @return bool
     */
    public function complete(?array $findings = null, ?array $recommendations = null): bool
    {
        $data = [
            'request_status' => 'completed',
            'completed_at' => now(),
        ];

        if ($findings) {
            $data['findings'] = $findings;
        }

        if ($recommendations) {
            $data['recommendations'] = $recommendations;
        }

        return $this->update($data);
    }

    /**
     * Cancel the consultation request.
     *
     * @param string|null $reason
     * @return bool
     */
    public function cancel(?string $reason = null): bool
    {
        return $this->update([
            'request_status' => 'cancelled',
            'cancellation_reason' => $reason,
        ]);
    }

    /**
     * Schedule the consultation.
     *
     * @param string $scheduledFor
     * @param string|null $location
     * @param int|null $durationMinutes
     * @return bool
     */
    public function schedule(string $scheduledFor, ?string $location = null, ?int $durationMinutes = null): bool
    {
        $data = [
            'scheduled_for' => $scheduledFor,
            'request_status' => 'accepted',
        ];

        if ($location) {
            $data['location'] = $location;
        }

        if ($durationMinutes) {
            $data['duration_minutes'] = $durationMinutes;
        }

        return $this->update($data);
    }

    /**
     * Add consultant notes.
     *
     * @param string $notes
     * @return bool
     */
    public function addConsultantNotes(string $notes): bool
    {
        $currentNotes = $this->consultant_notes;
        $newNotes = $currentNotes 
            ? $currentNotes . "\n\n" . now()->format('Y-m-d H:i') . " - " . $notes
            : now()->format('Y-m-d H:i') . " - " . $notes;

        return $this->update(['consultant_notes' => $newNotes]);
    }

    /**
     * Get the priority display text.
     *
     * @return string
     */
    public function getPriorityTextAttribute(): string
    {
        return [
            'routine' => 'Routine',
            'urgent' => 'Urgent',
            'emergent' => 'Emergent',
        ][$this->priority] ?? $this->priority;
    }

    /**
     * Get the consultation type display text.
     *
     * @return string
     */
    public function getConsultationTypeTextAttribute(): string
    {
        return [
            'in_person' => 'In Person',
            'telemedicine' => 'Telemedicine',
            'urgent' => 'Urgent',
            'elective' => 'Elective',
            'emergency' => 'Emergency',
        ][$this->consultation_type] ?? $this->consultation_type;
    }

    /**
     * Get the status display text.
     *
     * @return string
     */
    public function getStatusTextAttribute(): string
    {
        return [
            'pending' => 'Pending',
            'accepted' => 'Accepted',
            'declined' => 'Declined',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ][$this->request_status] ?? $this->request_status;
    }

    /**
     * Get the status color for UI display.
     *
     * @return string
     */
    public function getStatusColorAttribute(): string
    {
        return [
            'pending' => 'warning',
            'accepted' => 'info',
            'declined' => 'danger',
            'completed' => 'success',
            'cancelled' => 'secondary',
        ][$this->request_status] ?? 'secondary';
    }

    /**
     * Get the priority color for UI display.
     *
     * @return string
     */
    public function getPriorityColorAttribute(): string
    {
        return [
            'routine' => 'info',
            'urgent' => 'warning',
            'emergent' => 'danger',
        ][$this->priority] ?? 'secondary';
    }

    /**
     * Check if the consultation is overdue.
     *
     * @return bool
     */
    public function isOverdue(): bool
    {
        if (!$this->isAccepted() && !$this->isPending()) {
            return false;
        }

        if ($this->scheduled_for && $this->scheduled_for->isPast()) {
            return true;
        }

        // If pending for more than 48 hours
        if ($this->isPending() && $this->requested_at->diffInHours(now()) > 48) {
            return true;
        }

        return false;
    }

    /**
     * Get the response time in hours.
     *
     * @return float|null
     */
    public function getResponseTimeAttribute(): ?float
    {
        if (!$this->responded_at) {
            return null;
        }

        return round($this->requested_at->diffInHours($this->responded_at), 2);
    }

    /**
     * Get the completion time in hours.
     *
     * @return float|null
     */
    public function getCompletionTimeAttribute(): ?float
    {
        if (!$this->completed_at) {
            return null;
        }

        $startTime = $this->responded_at ?? $this->requested_at;
        return round($startTime->diffInHours($this->completed_at), 2);
    }
}