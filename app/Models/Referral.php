<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Referral extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'referral_uuid',
        'patient_id',
        'facility_id',
        'referring_staff_id',
        'receiving_staff_id',
        'referral_type',
        'referral_reason',
        'clinical_notes',
        'external_referral_id',
        'status',
        'priority',
        'referral_date',
        'response_date',
        'completed_date',
        'expiry_date',
        'metadata',
        'created_by_staff_id',
        'updated_by_staff_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'referral_uuid' => 'string',
        'patient_id' => 'integer',
        'facility_id' => 'integer',
        'referring_staff_id' => 'integer',
        'receiving_staff_id' => 'integer',
        'referral_date' => 'datetime',
        'response_date' => 'datetime',
        'completed_date' => 'datetime',
        'expiry_date' => 'datetime',
        'metadata' => 'array',
        'created_by_staff_id' => 'integer',
        'updated_by_staff_id' => 'integer',
        'referral_type' => 'string',
        'status' => 'string',
        'priority' => 'string',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'deleted_at',
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($referral) {
            if (empty($referral->referral_uuid)) {
                $referral->referral_uuid = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    /**
     * Get the patient associated with the referral.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get the facility associated with the referral.
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * Get the staff member who referred the patient.
     */
    public function referringStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'referring_staff_id');
    }

    /**
     * Get the staff member who received the referral.
     */
    public function receivingStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'receiving_staff_id');
    }

    /**
     * Get the staff member who created the referral.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by_staff_id');
    }

    /**
     * Get the staff member who last updated the referral.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'updated_by_staff_id');
    }

    /**
     * Check if referral is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if referral is accepted.
     */
    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    /**
     * Check if referral is rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Check if referral is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if referral is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Check if referral is expired.
     */
    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    /**
     * Accept the referral.
     */
    public function accept(int $receivingStaffId): bool
    {
        return $this->update([
            'status' => 'accepted',
            'receiving_staff_id' => $receivingStaffId,
            'response_date' => now(),
        ]);
    }

    /**
     * Reject the referral.
     */
    public function reject(?string $reason = null): bool
    {
        $data = [
            'status' => 'rejected',
            'response_date' => now(),
        ];

        if ($reason) {
            $data['metadata'] = array_merge(
                $this->metadata ?? [],
                ['rejection_reason' => $reason]
            );
        }

        return $this->update($data);
    }

    /**
     * Complete the referral.
     */
    public function complete(): bool
    {
        return $this->update([
            'status' => 'completed',
            'completed_date' => now(),
        ]);
    }

    /**
     * Cancel the referral.
     */
    public function cancel(?string $reason = null): bool
    {
        $data = [
            'status' => 'cancelled',
        ];

        if ($reason) {
            $data['metadata'] = array_merge(
                $this->metadata ?? [],
                ['cancellation_reason' => $reason]
            );
        }

        return $this->update($data);
    }

    /**
     * Get the referral type display text.
     */
    public function getReferralTypeTextAttribute(): string
    {
        return [
            'internal' => 'Internal',
            'external' => 'External',
        ][$this->referral_type] ?? $this->referral_type;
    }

    /**
     * Get the status display text.
     */
    public function getStatusTextAttribute(): string
    {
        return [
            'pending' => 'Pending',
            'accepted' => 'Accepted',
            'rejected' => 'Rejected',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ][$this->status] ?? $this->status;
    }

    /**
     * Get the priority display text.
     */
    public function getPriorityTextAttribute(): string
    {
        return [
            'low' => 'Low',
            'medium' => 'Medium',
            'high' => 'High',
            'urgent' => 'Urgent',
        ][$this->priority] ?? $this->priority;
    }

    /**
     * Scope a query to only include referrals for a specific patient.
     */
    public function scopeForPatient($query, int $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    /**
     * Scope a query to only include referrals for a specific facility.
     */
    public function scopeForFacility($query, int $facilityId)
    {
        return $query->where('facility_id', $facilityId);
    }

    /**
     * Scope a query to only include referrals with a specific status.
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include referrals with a specific priority.
     */
    public function scopeWithPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope a query to only include internal referrals.
     */
    public function scopeInternal($query)
    {
        return $query->where('referral_type', 'internal');
    }

    /**
     * Scope a query to only include external referrals.
     */
    public function scopeExternal($query)
    {
        return $query->where('referral_type', 'external');
    }
}