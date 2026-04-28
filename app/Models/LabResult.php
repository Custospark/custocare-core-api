<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabResult extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'lab_results';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'result_uuid',
        'lab_request_item_id',
        'template_field_id',
        'value',
        'unit',
        'numeric_value',
        'flag',
        'reference_min',
        'reference_max',
        'interpretation',
        'comments',
        'recorded_by_staff_id',
        'verified_by_staff_id',
        'verified_at',
        'recorded_at',
        'updated_at_value',
        'is_abnormal_flagged',
        'is_critical_alert_sent',
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
        'result_uuid' => 'string',
        'numeric_value' => 'decimal:4',
        'reference_min' => 'decimal:4',
        'reference_max' => 'decimal:4',
        'flag' => 'string',
        'verified_at' => 'datetime',
        'recorded_at' => 'datetime',
        'updated_at_value' => 'datetime',
        'is_abnormal_flagged' => 'boolean',
        'is_critical_alert_sent' => 'boolean',
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
     * Valid flag values.
     */
    public const FLAGS = [
        'normal',
        'low',
        'high',
        'critical',
        'abnormal',
        'pending'
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->result_uuid)) {
                $model->result_uuid = (string) \Illuminate\Support\Str::uuid();
            }
            
            if (empty($model->recorded_at)) {
                $model->recorded_at = now();
            }
        });

        static::updating(function ($model) {
            if ($model->isDirty('value')) {
                $model->updated_at_value = now();
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
            'result_uuid' => 'nullable|uuid|unique:lab_results,result_uuid',
            'lab_request_item_id' => 'required|exists:lab_request_items,id',
            'template_field_id' => 'required|exists:lab_template_fields,id',
            'value' => 'nullable|string',
            'unit' => 'nullable|string|max:50',
            'numeric_value' => 'nullable|numeric',
            'flag' => 'required|in:' . implode(',', self::FLAGS),
            'reference_min' => 'nullable|numeric',
            'reference_max' => 'nullable|numeric',
            'interpretation' => 'nullable|string',
            'comments' => 'nullable|string',
            'recorded_by_staff_id' => 'nullable|exists:staff,id',
            'verified_by_staff_id' => 'nullable|exists:staff,id',
            'verified_at' => 'nullable|date',
            'recorded_at' => 'nullable|date',
            'updated_at_value' => 'nullable|date',
            'is_abnormal_flagged' => 'boolean',
            'is_critical_alert_sent' => 'boolean',
            'created_by_staff_id' => 'nullable|exists:staff,id',
            'updated_by_staff_id' => 'nullable|exists:staff,id',
            'metadata' => 'nullable|array',
        ];
    }

    /**
     * Get the lab request item that this result belongs to.
     */
    public function labRequestItem(): BelongsTo
    {
        return $this->belongsTo(LabRequestItem::class, 'lab_request_item_id');
    }

    /**
     * Get the template field that this result is for.
     */
    public function templateField(): BelongsTo
    {
        return $this->belongsTo(LabTemplateField::class, 'template_field_id');
    }

    /**
     * Get the staff who recorded this result.
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'recorded_by_staff_id');
    }

    /**
     * Get the staff who verified this result.
     */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'verified_by_staff_id');
    }

    /**
     * Get the staff who created this result.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by_staff_id');
    }

    /**
     * Get the staff who updated this result.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'updated_by_staff_id');
    }

    /**
     * Scope a query to only include pending results.
     */
    public function scopePending($query)
    {
        return $query->where('flag', 'pending');
    }

    /**
     * Scope a query to only include normal results.
     */
    public function scopeNormal($query)
    {
        return $query->where('flag', 'normal');
    }

    /**
     * Scope a query to only include abnormal results.
     */
    public function scopeAbnormal($query)
    {
        return $query->whereIn('flag', ['abnormal', 'high', 'low']);
    }

    /**
     * Scope a query to only include critical results.
     */
    public function scopeCritical($query)
    {
        return $query->where('flag', 'critical');
    }

    /**
     * Scope a query to only include verified results.
     */
    public function scopeVerified($query)
    {
        return $query->whereNotNull('verified_at');
    }

    /**
     * Scope a query to only include unverified results.
     */
    public function scopeUnverified($query)
    {
        return $query->whereNull('verified_at');
    }

    /**
     * Scope a query to only include results with abnormal flag.
     */
    public function scopeAbnormalFlagged($query)
    {
        return $query->where('is_abnormal_flagged', true);
    }

    /**
     * Scope a query by date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('recorded_at', [$startDate, $endDate]);
    }

    /**
     * Check if result is pending.
     */
    public function isPending(): bool
    {
        return $this->flag === 'pending';
    }

    /**
     * Check if result is normal.
     */
    public function isNormal(): bool
    {
        return $this->flag === 'normal';
    }

    /**
     * Check if result is low.
     */
    public function isLow(): bool
    {
        return $this->flag === 'low';
    }

    /**
     * Check if result is high.
     */
    public function isHigh(): bool
    {
        return $this->flag === 'high';
    }

    /**
     * Check if result is critical.
     */
    public function isCritical(): bool
    {
        return $this->flag === 'critical';
    }

    /**
     * Check if result is abnormal.
     */
    public function isAbnormal(): bool
    {
        return in_array($this->flag, ['abnormal', 'high', 'low']);
    }

    /**
     * Check if result is verified.
     */
    public function isVerified(): bool
    {
        return !is_null($this->verified_at);
    }

    /**
     * Verify the result.
     */
    public function verify(int $verifiedByStaffId): bool
    {
        $this->verified_by_staff_id = $verifiedByStaffId;
        $this->verified_at = now();
        return $this->save();
    }

    /**
     * Mark critical alert as sent.
     */
    public function markCriticalAlertSent(): bool
    {
        $this->is_critical_alert_sent = true;
        return $this->save();
    }

    /**
     * Update flag based on numeric value and reference ranges.
     */
    public function updateFlagFromValue(): bool
    {
        if ($this->numeric_value === null) {
            $this->flag = 'pending';
            return $this->save();
        }

        $value = (float) $this->numeric_value;
        $min = $this->reference_min !== null ? (float) $this->reference_min : null;
        $max = $this->reference_max !== null ? (float) $this->reference_max : null;

        // Check for critical values (clinical judgment, can be customized)
        if ($this->is_critical_value($value, $min, $max)) {
            $this->flag = 'critical';
            $this->is_abnormal_flagged = true;
            return $this->save();
        }

        // Check for high values
        if ($max !== null && $value > $max) {
            $this->flag = 'high';
            $this->is_abnormal_flagged = true;
            return $this->save();
        }

        // Check for low values
        if ($min !== null && $value < $min) {
            $this->flag = 'low';
            $this->is_abnormal_flagged = true;
            return $this->save();
        }

        // Within normal range
        $this->flag = 'normal';
        $this->is_abnormal_flagged = false;
        return $this->save();
    }

    /**
     * Check if value is critical.
     */
    protected function is_critical_value(float $value, ?float $min, ?float $max): bool
    {
        // Critical value thresholds (50% below min or 50% above max)
        if ($min !== null && $value < ($min * 0.5)) {
            return true;
        }
        
        if ($max !== null && $value > ($max * 1.5)) {
            return true;
        }
        
        return false;
    }

    /**
     * Get formatted value with unit.
     */
    public function getFormattedValueAttribute(): string
    {
        $formatted = $this->value ?? '';
        
        if ($this->unit && $formatted) {
            $formatted .= ' ' . $this->unit;
        }
        
        return $formatted;
    }

    /**
     * Get reference range as string.
     */
    public function getReferenceRangeAttribute(): ?string
    {
        if ($this->reference_min !== null && $this->reference_max !== null) {
            return $this->reference_min . ' - ' . $this->reference_max . ' ' . ($this->unit ?? '');
        }
        
        if ($this->reference_min !== null) {
            return '≥ ' . $this->reference_min . ' ' . ($this->unit ?? '');
        }
        
        if ($this->reference_max !== null) {
            return '≤ ' . $this->reference_max . ' ' . ($this->unit ?? '');
        }
        
        return null;
    }

    /**
     * Get flag label.
     */
    public function getFlagLabelAttribute(): string
    {
        return ucfirst($this->flag);
    }

    /**
     * Get flag badge color.
     */
    public function getFlagBadgeColorAttribute(): string
    {
        return match($this->flag) {
            'normal' => 'success',
            'low' => 'info',
            'high' => 'warning',
            'abnormal' => 'warning',
            'critical' => 'danger',
            'pending' => 'secondary',
            default => 'secondary',
        };
    }

    /**
     * Get flag icon.
     */
    public function getFlagIconAttribute(): string
    {
        return match($this->flag) {
            'normal' => 'check-circle',
            'low' => 'arrow-down-circle',
            'high' => 'arrow-up-circle',
            'abnormal' => 'alert-circle',
            'critical' => 'alert-triangle',
            'pending' => 'clock',
            default => 'help-circle',
        };
    }

    /**
     * Check if result needs verification.
     */
    public function needsVerification(): bool
    {
        return $this->is_abnormal_flagged && !$this->isVerified();
    }

    /**
     * Get age of result in hours.
     */
    public function getAgeInHoursAttribute(): ?float
    {
        if (!$this->recorded_at) {
            return null;
        }
        
        return $this->recorded_at->diffInHours(now());
    }

    /**
     * Get verification delay in hours.
     */
    public function getVerificationDelayHoursAttribute(): ?float
    {
        if (!$this->recorded_at || !$this->verified_at) {
            return null;
        }
        
        return $this->recorded_at->diffInHours($this->verified_at);
    }
}