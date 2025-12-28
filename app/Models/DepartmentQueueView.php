<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepartmentQueueView extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'facility_id',
        'department_id',
        'queue_type',
        'patients_waiting_count',
        'patients_in_treatment_count',
        'total_active_patients',
        'average_wait_minutes',
        'median_wait_minutes',
        'longest_wait_minutes',
        'longest_waiting_visit_id',
        'next_patient_ids',
        'critical_patients',
        'staff_available_count',
        'staff_total_count',
        'available_staff_ids',
        'capacity_percentage',
        'bed_utilization_percentage',
        'capacity_status',
        'predicted_wait_times',
        'predicted_next_available_at',
        'snapshot_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'next_patient_ids' => 'array',
        'critical_patients' => 'array',
        'available_staff_ids' => 'array',
        'predicted_wait_times' => 'array',
        'snapshot_at' => 'datetime',
        'predicted_next_available_at' => 'datetime',
    ];

    /**
     * Queue type constants for easy reference
     */
    public const QUEUE_TYPES = [
        'triage',
        'consultation',
        'procedures',
        'diagnostic_imaging',
        'laboratory',
        'pharmacy',
        'discharge',
    ];

    /**
     * Capacity status constants
     */
    public const CAPACITY_STATUSES = [
        'normal',
        'busy',
        'critical',
        'at_capacity',
    ];

    /**
     * Get the facility that owns the department queue view.
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * Get the department that owns the department queue view.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Check if department is at critical capacity
     */
    public function isCritical(): bool
    {
        return $this->capacity_status === 'critical' || $this->capacity_status === 'at_capacity';
    }

    /**
     * Check if wait times are excessive (over 60 minutes)
     */
    public function hasExcessiveWaitTimes(): bool
    {
        return $this->average_wait_minutes > 60 || $this->longest_wait_minutes > 120;
    }

    /**
     * Get capacity level as a percentage (0-100)
     */
    public function getCapacityLevelAttribute(): int
    {
        return $this->capacity_percentage ?? 
               ($this->bed_utilization_percentage ?? 
               (int) round(($this->total_active_patients / $this->staff_available_count) * 100));
    }

    /**
     * Scope for current snapshots (last 30 seconds)
     */
    public function scopeCurrent($query)
    {
        return $query->where('snapshot_at', '>=', now()->subSeconds(30));
    }

    /**
     * Scope for specific queue type
     */
    public function scopeOfType($query, string $queueType)
    {
        return $query->where('queue_type', $queueType);
    }

    /**
     * Scope for critical departments
     */
    public function scopeCritical($query)
    {
        return $query->whereIn('capacity_status', ['critical', 'at_capacity']);
    }
}