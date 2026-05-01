<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LabRequestItem extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'lab_request_items';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'item_uuid',
        'lab_request_id',
        'lab_test_id',
        'status',
        'sample_type',
        'sample_identifier',
        'collected_at',
        'collected_by_staff_id',
        'started_at',
        'completed_at',
        'verified_by_staff_id',
        'verified_at',
        'result_flag',
        'notes',
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
        'item_uuid' => 'string',
        'status' => 'string',
        'collected_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'verified_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'result_flag' => 'string',
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
     * Valid status values.
     */
    public const STATUSES = [
        'pending',
        'sample_collected',
        'in_progress',
        'completed',
        'verified',
        'cancelled'
    ];

    /**
     * Valid result flag values.
     */
    public const RESULT_FLAGS = [
        'normal',
        'abnormal',
        'critical',
        'pending'
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->item_uuid)) {
                $model->item_uuid = (string) \Illuminate\Support\Str::uuid();
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
            'item_uuid' => 'nullable|uuid|unique:lab_request_items,item_uuid',
            'lab_request_id' => 'required|exists:lab_requests,id',
            'lab_test_id' => 'required|exists:lab_tests,id',
            'status' => 'required|in:' . implode(',', self::STATUSES),
            'sample_type' => 'nullable|string|max:100',
            'sample_identifier' => 'nullable|string|max:100',
            'collected_at' => 'nullable|date',
            'collected_by_staff_id' => 'nullable|exists:staff,id',
            'started_at' => 'nullable|date',
            'completed_at' => 'nullable|date',
            'verified_by_staff_id' => 'nullable|exists:staff,id',
            'verified_at' => 'nullable|date',
            'result_flag' => 'required|in:' . implode(',', self::RESULT_FLAGS),
            'notes' => 'nullable|string',
            'cancellation_reason' => 'nullable|string',
            'cancelled_at' => 'nullable|date',
            'created_by_staff_id' => 'nullable|exists:staff,id',
            'updated_by_staff_id' => 'nullable|exists:staff,id',
            'metadata' => 'nullable|array',
        ];
    }

    /**
     * Get the lab request that this item belongs to.
     */
    public function labRequest(): BelongsTo
    {
        return $this->belongsTo(LabRequest::class, 'lab_request_id');
    }

    /**
     * Get the lab test for this item.
     */
    public function labTest(): BelongsTo
    {
        return $this->belongsTo(LabTest::class, 'lab_test_id');
    }

    /**
     * Get the staff who collected the sample.
     */
    public function collectedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'collected_by_staff_id');
    }

    /**
     * Get the staff who verified the results.
     */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'verified_by_staff_id');
    }

    /**
     * Get the staff who created this item.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by_staff_id');
    }

    /**
     * Get the staff who updated this item.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'updated_by_staff_id');
    }

    /**
     * Get the results for this item.
     */
    public function results(): HasMany
    {
        return $this->hasMany(LabResult::class, 'lab_request_item_id');
    }

    /**
     * Get the primary result (for simple tests).
     */
    public function primaryResult(): HasOne
    {
        return $this->hasOne(LabResult::class, 'lab_request_item_id')->oldestOfMany();
    }

    /**
     * Scope a query to only include pending items.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include items with collected samples.
     */
    public function scopeSampleCollected($query)
    {
        return $query->where('status', 'sample_collected');
    }

    /**
     * Scope a query to only include in-progress items.
     */
    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    /**
     * Scope a query to only include completed items.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope a query to only include verified items.
     */
    public function scopeVerified($query)
    {
        return $query->where('status', 'verified');
    }

    /**
     * Scope a query to only include cancelled items.
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Scope a query by result flag.
     */
    public function scopeWithResultFlag($query, string $flag)
    {
        return $query->where('result_flag', $flag);
    }

    /**
     * Scope a query to only include abnormal or critical results.
     */
    public function scopeAbnormalOrCritical($query)
    {
        return $query->whereIn('result_flag', ['abnormal', 'critical']);
    }

    /**
     * Check if item is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if sample is collected.
     */
    public function isSampleCollected(): bool
    {
        return $this->status === 'sample_collected';
    }

    /**
     * Check if item is in progress.
     */
    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    /**
     * Check if item is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if item is verified.
     */
    public function isVerified(): bool
    {
        return $this->status === 'verified';
    }

    /**
     * Check if item is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Check if result is normal.
     */
    public function isResultNormal(): bool
    {
        return $this->result_flag === 'normal';
    }

    /**
     * Check if result is abnormal.
     */
    public function isResultAbnormal(): bool
    {
        return $this->result_flag === 'abnormal';
    }

    /**
     * Check if result is critical.
     */
    public function isResultCritical(): bool
    {
        return $this->result_flag === 'critical';
    }

    /**
     * Mark sample as collected.
     */
    public function markSampleCollected(int $collectedByStaffId, ?string $sampleIdentifier = null): bool
    {
        $this->status = 'sample_collected';
        $this->collected_at = now();
        $this->collected_by_staff_id = $collectedByStaffId;
        $this->updated_by_staff_id = $collectedByStaffId;
        
        if ($sampleIdentifier) {
            $this->sample_identifier = $sampleIdentifier;
        }
        
        return $this->save();
    }

    /**
     * Mark item as in progress.
     */
    public function markInProgress(): bool
    {
        $this->status = 'in_progress';
        $this->started_at = now();
        return $this->save();
    }

    /**
     * Mark item as completed.
     */
    public function markCompleted(): bool
    {
        $this->status = 'completed';
        $this->completed_at = now();
        return $this->save();
    }

    /**
     * Mark item as verified.
     */
    public function markVerified(int $verifiedByStaffId): bool
    {
        $this->status = 'verified';
        $this->verified_at = now();
        $this->verified_by_staff_id = $verifiedByStaffId;
        return $this->save();
    }

    /**
     * Cancel the item.
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
     * Update result flag based on actual results.
     */
    public function updateResultFlagFromResults(): bool
    {
        $results = $this->results;
        
        if ($results->isEmpty()) {
            $this->result_flag = 'pending';
            return $this->save();
        }
        
        // Check for critical results
        if ($results->contains('flag', 'critical')) {
            $this->result_flag = 'critical';
            return $this->save();
        }
        
        // Check for abnormal results
        if ($results->contains('flag', 'abnormal') || 
            $results->contains('flag', 'high') || 
            $results->contains('flag', 'low')) {
            $this->result_flag = 'abnormal';
            return $this->save();
        }
        
        // Check if all results are normal
        if ($results->every(fn($result) => $result->flag === 'normal')) {
            $this->result_flag = 'normal';
            return $this->save();
        }
        
        $this->result_flag = 'pending';
        return $this->save();
    }

    /**
     * Check if all results are verified.
     */
    public function areAllResultsVerified(): bool
    {
        return $this->results()->whereNull('verified_at')->count() === 0;
    }

    /**
     * Get status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return ucfirst(str_replace('_', ' ', $this->status));
    }

    /**
     * Get result flag label.
     */
    public function getResultFlagLabelAttribute(): string
    {
        return ucfirst($this->result_flag);
    }

    /**
     * Get status badge color.
     */
    public function getStatusBadgeColorAttribute(): string
    {
        return match($this->status) {
            'pending' => 'secondary',
            'sample_collected' => 'info',
            'in_progress' => 'primary',
            'completed' => 'success',
            'verified' => 'success',
            'cancelled' => 'danger',
            default => 'secondary',
        };
    }

    /**
     * Get result flag badge color.
     */
    public function getResultFlagBadgeColorAttribute(): string
    {
        return match($this->result_flag) {
            'normal' => 'success',
            'abnormal' => 'warning',
            'critical' => 'danger',
            'pending' => 'secondary',
            default => 'secondary',
        };
    }

    /**
     * Get turnaround time in minutes.
     */
    public function getTurnaroundTimeMinutesAttribute(): ?int
    {
        if (!$this->started_at || !$this->completed_at) {
            return null;
        }
        
        return $this->started_at->diffInMinutes($this->completed_at);
    }

    /**
     * Get collection to completion time in minutes.
     */
    public function getCollectionToCompletionMinutesAttribute(): ?int
    {
        if (!$this->collected_at || !$this->completed_at) {
            return null;
        }
        
        return $this->collected_at->diffInMinutes($this->completed_at);
    }
}