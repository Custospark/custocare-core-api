<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Patient extends Model
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
        'user_id',
        'patient_uuid',
        'medical_record_number_hash',
        'medical_record_number_encrypted',
        'previous_mrn_list_encrypted',
        'date_of_birth',
        'biological_sex',
        'gender_identity',
        'blood_type',
        'ethnicity',
        'genetic_markers',
        'emergency_contact_chain_encrypted',
        'known_allergies',
        'chronic_conditions',
        'active_medications',
        'is_organ_donor',
        'advance_directives',
        'acuity_baseline',
        'risk_factors',
        'requires_isolation',
        'isolation_type',
        'default_consent_level',
        'privacy_flags',
        'research_participation_allowed',
        'data_sharing_allowed',
        'primary_insurance_provider',
        'primary_insurance_id_encrypted',
        'secondary_insurance_provider',
        'secondary_insurance_id_encrypted',
        'payment_responsibility',
        'primary_care_provider_staff_id',
        'primary_care_facility_id',
        'last_wellness_visit_at',
        'next_scheduled_appointment_at',
        'portal_access_enabled',
        'portal_terms_accepted_at',
        'preferred_language',
        'preferred_communication_method',
        'status',
        'deceased_at',
        'merged_into_patient_id',
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
        'date_of_birth' => 'date',
        'genetic_markers' => 'array',
        'emergency_contact_chain_encrypted' => 'array',
        'known_allergies' => 'array',
        'chronic_conditions' => 'array',
        'active_medications' => 'array',
        'advance_directives' => 'array',
        'risk_factors' => 'array',
        'privacy_flags' => 'array',
        'requires_isolation' => 'boolean',
        'is_organ_donor' => 'boolean',
        'research_participation_allowed' => 'boolean',
        'data_sharing_allowed' => 'boolean',
        'portal_access_enabled' => 'boolean',
        'portal_terms_accepted_at' => 'datetime',
        'last_wellness_visit_at' => 'datetime',
        'next_scheduled_appointment_at' => 'datetime',
        'deceased_at' => 'datetime',
        'acuity_baseline' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'medical_record_number_hash',
        'medical_record_number_encrypted',
        'previous_mrn_list_encrypted',
        'primary_insurance_id_encrypted',
        'secondary_insurance_id_encrypted',
        'emergency_contact_chain_encrypted',
    ];

    /**
     * Get the user associated with the patient.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the age of the patient in years.
     */
    public function age(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->date_of_birth->age,
        );
    }

    /**
     * Check if patient has full consent level.
     */
    public function hasFullConsent(): bool
    {
        return $this->default_consent_level === 'full';
    }

    /**
     * Check if patient is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if patient is deceased.
     */
    public function isDeceased(): bool
    {
        return $this->status === 'deceased';
    }

    /**
     * Scope a query to only include active patients.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include patients with specific blood type.
     */
    public function scopeWithBloodType($query, string $bloodType)
    {
        return $query->where('blood_type', $bloodType);
    }

    /**
     * Scope a query to only include patients requiring isolation.
     */
    public function scopeRequiringIsolation($query)
    {
        return $query->where('requires_isolation', true);
    }

    /**
     * Get the primary care provider staff member.
     */
    public function primaryCareProvider()
    {
        return $this->belongsTo(Staff::class, 'primary_care_provider_staff_id');
    }

    /**
     * Get the primary care facility.
     */
    public function primaryCareFacility()
    {
        return $this->belongsTo(Facility::class, 'primary_care_facility_id');
    }
}