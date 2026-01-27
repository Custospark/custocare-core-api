<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Staff extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'staff_uuid',
        'user_id',
        'employee_id',
        'professional_title',
        'professional_license_number_encrypted',
        'professional_license_number_hash',
        'license_issuing_state',
        'license_issuing_country',
        'license_expiry_date',
        'specialization_codes',
        'board_certifications',
        'additional_certifications',
        'npi_number',
        'dea_number_encrypted',
        'dea_expiry_date',
        'employment_status',
        'employment_type',
        'hire_date',
        'termination_date',
        'termination_reason',
        'clinical_privileges',
        'prescribing_authority',
        'can_supervise_trainees',
        'can_order_controlled_substances',
        'can_sign_death_certificates',
        'global_role_level',
        'reports_to_staff_id',
        'default_schedule',
        'max_concurrent_patients',
        'average_appointment_duration_minutes',
        'accepts_new_patients',
        'patient_satisfaction_score',
        'total_patients_treated',
        'quality_metrics',
        'last_peer_review_date',
        'last_competency_assessment_date',
        'background_check_completed',
        'background_check_date',
        'drug_screening_completed',
        'drug_screening_date',
        'immunization_records',
        'tb_test_records',
        'hipaa_training_completed',
        'hipaa_training_date',
        'hipaa_training_expiry',
        'work_phone_encrypted',
        'work_email_encrypted',
        'emergency_contact_encrypted',
        'system_permissions',
        'accessible_facility_ids',
        'accessible_department_ids',
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
        'specialization_codes' => 'array',
        'board_certifications' => 'array',
        'additional_certifications' => 'array',
        'clinical_privileges' => 'array',
        'prescribing_authority' => 'array',
        'default_schedule' => 'array',
        'quality_metrics' => 'array',
        'immunization_records' => 'array',
        'tb_test_records' => 'array',
        'emergency_contact_encrypted' => 'array',
        'system_permissions' => 'array',
        'accessible_facility_ids' => 'array',
        'accessible_department_ids' => 'array',
        'metadata' => 'array',
        'can_supervise_trainees' => 'boolean',
        'can_order_controlled_substances' => 'boolean',
        'can_sign_death_certificates' => 'boolean',
        'accepts_new_patients' => 'boolean',
        'background_check_completed' => 'boolean',
        'drug_screening_completed' => 'boolean',
        'hipaa_training_completed' => 'boolean',
        'patient_satisfaction_score' => 'decimal:2',
        'license_expiry_date' => 'date',
        'dea_expiry_date' => 'date',
        'hire_date' => 'date',
        'termination_date' => 'date',
        'background_check_date' => 'date',
        'drug_screening_date' => 'date',
        'hipaa_training_date' => 'date',
        'hipaa_training_expiry' => 'date',
        'last_peer_review_date' => 'datetime',
        'last_competency_assessment_date' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'professional_license_number_encrypted',
        'professional_license_number_hash',
        'dea_number_encrypted',
        'work_phone_encrypted',
        'work_email_encrypted',
        'emergency_contact_encrypted',
    ];

    /**
     * Get the user associated with the staff.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function facilityStaffRoles()
    {
        return $this->hasMany(FacilityStaffRole::class, 'staff_id');
}

    /**
     * Get the supervisor this staff reports to.
     */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'reports_to_staff_id');
    }

    /**
     * Get staff members who report to this staff.
     */
    public function subordinates(): HasMany
    {
        return $this->hasMany(Staff::class, 'reports_to_staff_id');
    }

    /**
     * Get the staff member who created this record.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by_staff_id');
    }

    /**
     * Get the staff member who last updated this record.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'updated_by_staff_id');
    }

    /**
     * Scope a query to only include active staff.
     */
    public function scopeActive($query)
    {
        return $query->where('employment_status', 'active');
    }

    /**
     * Scope a query to only include staff with valid licenses.
     */
    public function scopeWithValidLicense($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('license_expiry_date')
              ->orWhere('license_expiry_date', '>', now());
        });
    }

    /**
     * Check if staff has expired license.
     */
    public function hasExpiredLicense(): bool
    {
        return $this->license_expiry_date && $this->license_expiry_date < now();
    }

    /**
     * Check if staff has expired DEA registration.
     */
    public function hasExpiredDEA(): bool
    {
        return $this->dea_expiry_date && $this->dea_expiry_date < now();
    }

    /**
     * Check if staff can prescribe based on credentials.
     */
    public function canPrescribe(): bool
    {
        return $this->can_order_controlled_substances && !empty($this->prescribing_authority);
    }
}