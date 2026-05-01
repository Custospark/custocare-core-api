<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LabRequest extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'lab_requests';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'request_uuid',
        'visit_id',
        'patient_id',
        'facility_id',
        'requested_by_staff_id',
        'priority',
        'status',
        'clinical_notes',
        'diagnosis_context',
        'requested_at',
        'collected_at',
        'completed_at',
        'reviewed_at',
        'reviewed_by_staff_id',
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
        'request_uuid' => 'string',
        'priority' => 'string',
        'status' => 'string',
        'diagnosis_context' => 'array',
        'requested_at' => 'datetime',
        'collected_at' => 'datetime',
        'completed_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'metadata' => 'array',
        'deleted_at' => 'datetime',
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
     * Valid priority values.
     */
    public const PRIORITIES = [
        'routine',
        'urgent',
        'stat'
    ];

    /**
     * Valid status values.
     */
    public const STATUSES = [
        'pending',
        'in_progress',
        'completed',
        'reviewed',
        'cancelled'
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->request_uuid)) {
                $model->request_uuid = (string) \Illuminate\Support\Str::uuid();
            }
            
            if (empty($model->requested_at)) {
                $model->requested_at = now();
            }
        });
    }

    /**
     * Get the validation rules for the model.
     *
     * @return array<string, string>
     */
    public static function rules(): array
    {
        return [
            'request_uuid' => 'nullable|uuid|unique:lab_requests,request_uuid',
            'visit_id' => 'required|exists:visits,id',
            'patient_id' => 'required|exists:patients,id',
            'facility_id' => 'required|exists:facilities,id',
            'requested_by_staff_id' => 'nullable|exists:staff,id',
            'priority' => 'required|in:' . implode(',', self::PRIORITIES),
            'status' => 'required|in:' . implode(',', self::STATUSES),
            'clinical_notes' => 'nullable|string',
            'diagnosis_context' => 'nullable|array',
            'requested_at' => 'nullable|date',
            'collected_at' => 'nullable|date',
            'completed_at' => 'nullable|date',
            'reviewed_at' => 'nullable|date',
            'reviewed_by_staff_id' => 'nullable|exists:staff,id',
            'cancellation_reason' => 'nullable|string',
            'cancelled_at' => 'nullable|date',
            'created_by_staff_id' => 'nullable|exists:staff,id',
            'updated_by_staff_id' => 'nullable|exists:staff,id',
            'metadata' => 'nullable|array',
        ];
    }

    /**
     * Get the visit associated with this lab request.
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class, 'visit_id');
    }

    /**
     * Get the patient associated with this lab request.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    /**
     * Get the facility associated with this lab request.
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'facility_id');
    }

    /**
     * Get the staff who requested this lab request.
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'requested_by_staff_id');
    }

    /**
     * Get the staff who reviewed this lab request.
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'reviewed_by_staff_id');
    }

    /**
     * Get the staff who created this lab request.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by_staff_id');
    }

    /**
     * Get the staff who updated this lab request.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'updated_by_staff_id');
    }

    /**
     * Get the items for this lab request.
     */
    public function items(): HasMany
    {
        return $this->hasMany(LabRequestItem::class, 'lab_request_id');
    }

    /**
     * Scope a query to only include pending requests.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include in-progress requests.
     */
    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    /**
     * Scope a query to only include completed requests.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope a query to only include reviewed requests.
     */
    public function scopeReviewed($query)
    {
        return $query->where('status', 'reviewed');
    }

    /**
     * Scope a query to only include cancelled requests.
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Scope a query to only include routine priority requests.
     */
    public function scopeRoutine($query)
    {
        return $query->where('priority', 'routine');
    }

    /**
     * Scope a query to only include urgent priority requests.
     */
    public function scopeUrgent($query)
    {
        return $query->where('priority', 'urgent');
    }

    /**
     * Scope a query to only include stat priority requests.
     */
    public function scopeStat($query)
    {
        return $query->where('priority', 'stat');
    }

    /**
     * Scope a query by facility.
     */
    public function scopeByFacility($query, int $facilityId)
    {
        return $query->where('facility_id', $facilityId);
    }

    /**
     * Scope a query by patient.
     */
    public function scopeByPatient($query, int $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    /**
     * Scope a query by date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('requested_at', [$startDate, $endDate]);
    }

    /**
     * Check if request is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if request is in progress.
     */
    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    /**
     * Check if request is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if request is reviewed.
     */
    public function isReviewed(): bool
    {
        return $this->status === 'reviewed';
    }

    /**
     * Check if request is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Check if request is stat priority.
     */
    public function isStat(): bool
    {
        return $this->priority === 'stat';
    }

    /**
     * Check if request is urgent.
     */
    public function isUrgent(): bool
    {
        return $this->priority === 'urgent';
    }

    /**
     * Check if request is routine.
     */
    public function isRoutine(): bool
    {
        return $this->priority === 'routine';
    }

    /**
     * Mark request as in progress.
     */
    public function markInProgress(): bool
    {
        $this->status = 'in_progress';
        return $this->save();
    }

    /**
     * Mark request as completed.
     */
    public function markCompleted(): bool
    {
        $this->status = 'completed';
        $this->completed_at = now();
        return $this->save();
    }

    /**
     * Mark request as reviewed.
     */
    public function markReviewed(int $reviewedByStaffId): bool
    {
        $this->status = 'reviewed';
        $this->reviewed_at = now();
        $this->reviewed_by_staff_id = $reviewedByStaffId;
        return $this->save();
    }

    /**
     * Cancel the request.
     */
    public function cancel(string $reason, ?int $cancelledByStaffId = null): bool
    {
        $this->status = 'cancelled';
        $this->cancellation_reason = $reason;
        $this->cancelled_at = now();
        
        if ($cancelledByStaffId) {
            $this->updated_by_staff_id = $cancelledByStaffId;
        }
        
        return $this->save();
    }

    /**
     * Check if all items are completed.
     */
    public function areAllItemsCompleted(): bool
    {
        return $this->items()->whereNotIn('status', ['completed', 'verified', 'cancelled'])->count() === 0;
    }

   
   /**
     * Get progress percentage (excluding cancelled items).
     */
    public function getProgressPercentageAttribute(): int
    {
        $totalItems = $this->items()->where('status', '!=', 'cancelled')->count();
        
        if ($totalItems === 0) {
            return 0;
        }
        
        $completedItems = $this->items()
            ->whereIn('status', ['completed', 'verified'])
            ->where('status', '!=', 'cancelled')
            ->count();
        
        return (int) round(($completedItems / $totalItems) * 100);
    }

    /**
     * Get priority label.
     */
    public function getPriorityLabelAttribute(): string
    {
        return ucfirst($this->priority);
    }

    /**
     * Get status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return ucfirst(str_replace('_', ' ', $this->status));
    }

    /**
     * Get priority badge color.
     */
    public function getPriorityBadgeColorAttribute(): string
    {
        return match($this->priority) {
            'stat' => 'danger',
            'urgent' => 'warning',
            'routine' => 'info',
            default => 'secondary',
        };
    }

    /**
     * Get status badge color.
     */
    public function getStatusBadgeColorAttribute(): string
    {
        return match($this->status) {
            'pending' => 'warning',
            'in_progress' => 'info',
            'completed' => 'success',
            'reviewed' => 'primary',
            'cancelled' => 'danger',
            default => 'secondary',
        };
    }
}