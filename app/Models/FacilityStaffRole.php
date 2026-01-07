<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;

class FacilityStaffRole extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'assignment_uuid',
        'facility_id',
        'staff_invitation_id',
        'staff_id',
        'role_code',
        'module_code',
        'department_ids',
        'is_primary_facility',
        'privileges_bitmask',
        'accessible_patient_populations',
        'prescribing_authority_at_facility',
        'shift_schedule',
        'shift_type',
        'hours_per_week',
        'effective_from',
        'effective_to',
        'assignment_status',
        'credentialing_completed_at',
        'credentialed_by_staff_id',
        'privileging_approved_at',
        'next_reappointment_date',
        'patients_treated_at_facility',
        'facility_satisfaction_score',
        'created_by_staff_id',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'assignment_uuid' => 'string',
        'module_code' => 'array',
        'department_ids' => 'array',
        'is_primary_facility' => 'boolean',
        'privileges_bitmask' => 'array',
        'accessible_patient_populations' => 'array',
        'prescribing_authority_at_facility' => 'array',
        'shift_schedule' => 'array',
        'hours_per_week' => 'integer',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'credentialing_completed_at' => 'datetime',
        'privileging_approved_at' => 'datetime',
        'next_reappointment_date' => 'datetime',
        'patients_treated_at_facility' => 'integer',
        'facility_satisfaction_score' => 'decimal:2',
        'metadata' => 'array',
        'deleted_at' => 'datetime',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array<string>
     */
    protected $dates = [
        'effective_from',
        'effective_to',
    ];

    /**
     * Role codes as constants for easy reference
     */
    public const ROLE_CODES = [
        'attending_physician',
        'resident_physician',
        'consulting_physician',
        'surgeon',
        'anesthesiologist',
        'nurse_practitioner',
        'physician_assistant',
        'registered_nurse',
        'charge_nurse',
        'nurse_manager',
        'pharmacist',
        'pharmacy_technician',
        'radiologist',
        'radiologic_technician',
        'laboratory_scientist',
        'respiratory_therapist',
        'physical_therapist',
        'occupational_therapist',
        'social_worker',
        'case_manager',
        'receptionist',
        'medical_assistant',
        'facility-administrator',
        'department_manager',
        'quality_coordinator',
        'infection_control',
        'it_support'
    ];

    /**
     * Assignment statuses as constants
     */
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ON_LEAVE = 'on_leave';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_TERMINATED = 'terminated';

    /**
     * Shift types as constants
     */
    public const SHIFT_DAY = 'day';
    public const SHIFT_NIGHT = 'night';
    public const SHIFT_ROTATING = 'rotating';
    public const SHIFT_ON_CALL = 'on_call';
    public const SHIFT_FLEXIBLE = 'flexible';

    /**
     * Get the facility associated with this role assignment
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function facility()
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * Get the staff member associated with this role assignment
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    /**
     * Get the staff member who created this assignment
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_staff_id');
    }

    /**
     * Get the staff member who performed credentialing
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function credentialedBy()
    {
        return $this->belongsTo(User::class, 'credentialed_by_staff_id');
    }

    /**
     * Scope to get active assignments
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('assignment_status', self::STATUS_ACTIVE);
    }

    /**
     * Scope to get assignments effective on a specific date
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $date
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeEffectiveOn($query, string $date)
    {
        return $query->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')
                  ->orWhere('effective_to', '>=', $date);
            });
    }

    /**
     * Check if assignment is currently active
     *
     * @return bool
     */
    public function isCurrentlyActive(): bool
    {
        $today = now()->format('Y-m-d');
        
        return $this->assignment_status === self::STATUS_ACTIVE &&
               $this->effective_from <= $today &&
               ($this->effective_to === null || $this->effective_to >= $today);
    }

    /**
     * Check if assignment is for primary facility
     *
     * @return bool
     */
    public function isPrimaryFacility(): bool
    {
        return (bool) $this->is_primary_facility;
    }

    /**
     * Increment patient count
     *
     * @param int $count
     * @return bool
     */
    public function incrementPatientsTreated(int $count = 1): bool
    {
        return $this->increment('patients_treated_at_facility', $count);
    }
}