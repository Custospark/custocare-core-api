<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;

class VisitRoute extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'facility_id',
        'visit_id',
        'from_department_id',
        'to_department_id',
        'routing_reason',
        'routing_notes',
        'routing_staff_id',
        'queue_position_at_move',
        'estimated_wait_minutes',
        'actual_wait_minutes',
        'routed_at',
        'arrived_at_department',
        'departed_department',
        'actual_transfer_duration_minutes',
        'handoff_summary',
        'sending_staff_id',
        'receiving_staff_id',
        'handoff_acknowledged',
        'handoff_acknowledged_at',
        'transport_method',
        'requires_escort',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'routed_at' => 'datetime',
        'arrived_at_department' => 'datetime',
        'departed_department' => 'datetime',
        'handoff_acknowledged_at' => 'datetime',
        'handoff_acknowledged' => 'boolean',
        'requires_escort' => 'boolean',
        'metadata' => 'array',
        'queue_position_at_move' => 'integer',
        'estimated_wait_minutes' => 'integer',
        'actual_wait_minutes' => 'integer',
        'actual_transfer_duration_minutes' => 'integer',
    ];

    /**
     * Get the routing reason as a human-readable label.
     */
    protected function routingReasonLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => match($this->routing_reason) {
                'initial_assignment' => 'Initial Assignment',
                'specialist_consultation' => 'Specialist Consultation',
                'diagnostic_imaging' => 'Diagnostic Imaging',
                'laboratory_tests' => 'Laboratory Tests',
                'surgical_procedure' => 'Surgical Procedure',
                'capacity_management' => 'Capacity Management',
                'escalation_of_care' => 'Escalation of Care',
                'de_escalation_of_care' => 'De-escalation of Care',
                'patient_request' => 'Patient Request',
                'admission_to_inpatient' => 'Admission to Inpatient',
                'discharge_processing' => 'Discharge Processing',
                default => ucfirst(str_replace('_', ' ', $this->routing_reason)),
            }
        );
    }

    /**
     * Get the transport method as a human-readable label.
     */
    protected function transportMethodLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->transport_method 
                ? ucfirst($this->transport_method)
                : null
        );
    }

    /**
     * Calculate the total duration in minutes.
     */
    protected function totalDurationMinutes(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!$this->arrived_at_department || !$this->departed_department) {
                    return null;
                }
                
                return $this->arrived_at_department->diffInMinutes($this->departed_department);
            }
        );
    }

    /**
     * Relationship with the facility.
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * Relationship with the visit.
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /**
     * Relationship with the source department.
     */
    public function fromDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'from_department_id');
    }

    /**
     * Relationship with the destination department.
     */
    public function toDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'to_department_id');
    }

    /**
     * Relationship with routing staff.
     */
    public function routingStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'routing_staff_id');
    }

    /**
     * Relationship with sending staff.
     */
    public function sendingStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sending_staff_id');
    }

    /**
     * Relationship with receiving staff.
     */
    public function receivingStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiving_staff_id');
    }

    /**
     * Scope for active routes (not yet arrived or not yet departed).
     */
    public function scopeActive($query)
    {
        return $query->where(function($q) {
            $q->whereNull('arrived_at_department')
              ->orWhereNull('departed_department');
        });
    }

    /**
     * Scope for routes requiring handoff acknowledgment.
     */
    public function scopePendingHandoff($query)
    {
        return $query->where('handoff_acknowledged', false)
                     ->whereNotNull('handoff_summary');
    }

    /**
     * Scope for routes within a date range.
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('routed_at', [$startDate, $endDate]);
    }

    /**
     * Check if the route is currently active.
     */
    public function isActive(): bool
    {
        return is_null($this->arrived_at_department) || is_null($this->departed_department);
    }

    /**
     * Check if the route is complete.
     */
    public function isComplete(): bool
    {
        return !is_null($this->arrived_at_department) && !is_null($this->departed_department);
    }

    /**
     * Mark the handoff as acknowledged.
     */
    public function acknowledgeHandoff(int $staffId): bool
    {
        return $this->update([
            'handoff_acknowledged' => true,
            'handoff_acknowledged_at' => now(),
            'receiving_staff_id' => $staffId,
        ]);
    }

    /**
     * Update arrival time and calculate actual wait.
     */
    public function markAsArrived(): bool
    {
        $arrivedAt = now();
        
        $data = ['arrived_at_department' => $arrivedAt];
        
        if ($this->routed_at) {
            $data['actual_wait_minutes'] = $arrivedAt->diffInMinutes($this->routed_at);
        }
        
        return $this->update($data);
    }

    /**
     * Update departure time and calculate transfer duration.
     */
    public function markAsDeparted(): bool
    {
        $departedAt = now();
        
        $data = ['departed_department' => $departedAt];
        
        if ($this->arrived_at_department) {
            $data['actual_transfer_duration_minutes'] = $departedAt->diffInMinutes($this->arrived_at_department);
        }
        
        return $this->update($data);
    }
}