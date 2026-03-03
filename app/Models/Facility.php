<?php

namespace App\Models;

use App\Support\HealthcareIdGenerator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Subscription;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class Facility
 * 
 * Healthcare facility registry model representing medical facilities in the system.
 * Reference data optimized for CDN distribution and caching-first strategy.
 * 
 * @property int $id
 * @property string $facility_uuid
 * @property string $facility_code
 * @property string $facility_name
 * @property string $legal_entity_name
 * @property string|null $tax_id_encrypted
 * @property string $facility_type
 * @property string $facility_tier
 * @property int|null $bed_capacity
 * @property array|null $accreditations
 * @property string $address_line1
 * @property string|null $address_line2
 * @property string $city
 * @property string $state_province
 * @property string $postal_code
 * @property string $country_code
 * @property float|null $latitude
 * @property float|null $longitude
 * @property string $timezone
 * @property string $main_phone
 * @property string|null $emergency_phone
 * @property string|null $fax
 * @property string|null $email
 * @property string|null $website
 * @property array $operating_hours
 * @property array|null $emergency_services_hours
 * @property bool $is_24_7
 * @property int|null $parent_organization_id
 * @property array|null $affiliated_facility_ids
 * @property array|null $referral_network_facility_ids
 * @property string|null $health_system_name
 * @property string|null $license_number
 * @property string|null $license_issuing_authority
 * @property string|null $license_expiry_date
 * @property array|null $regulatory_identifiers
 * @property bool $participates_in_medicare
 * @property bool $participates_in_medicaid
 * @property array $available_services
 * @property array|null $specialty_services
 * @property array|null $equipment_inventory_summary
 * @property bool $has_emergency_department
 * @property bool $has_trauma_center
 * @property int|null $trauma_center_level
 * @property bool $has_intensive_care
 * @property bool $has_neonatal_icu
 * @property bool $has_cardiac_cath_lab
 * @property string $data_residency_region
 * @property string $primary_database_shard
 * @property array|null $replica_shard_locations
 * @property float|null $average_wait_time_minutes
 * @property float|null $patient_satisfaction_score
 * @property int|null $monthly_patient_volume
 * @property string $operational_status
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property int|null $created_by_staff_id
 * @property int|null $updated_by_staff_id
 * @property array|null $metadata
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *  * --------------------------------------------------------------------------
 * 🆕 Financial Configuration
 * --------------------------------------------------------------------------
 * @property string $currency
 * @property bool $tax_enabled
 * @property string|null $tax_name
 * @property float|null $tax_rate
 *
 * --------------------------------------------------------------------------
 * 🆕 Branding
 * --------------------------------------------------------------------------
 * @property string|null $facility_logo_path
 * @property string|null $primary_brand_color
 * @property string|null $secondary_brand_color
 */
class Facility extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'facility_uuid',
        'facility_code',
        'facility_name',
        'legal_entity_name',
        'tax_id_encrypted',
        'facility_type',
        'facility_tier',
        'bed_capacity',
        'accreditations',
        'nature_of_facility',
        'address_line1',
        'address_line2',
        'city',
        'state_province',
        'postal_code',
        'country_code',
        'latitude',
        'longitude',
        'timezone',
        'main_phone',
        'emergency_phone',
        'fax',
        'email',
        'website',
        'operating_hours',
        'emergency_services_hours',
        'is_24_7',
        'parent_organization_id',
        'affiliated_facility_ids',
        'referral_network_facility_ids',
        'health_system_name',
        'license_number',
        'license_issuing_authority',
        'license_expiry_date',
        'regulatory_identifiers',
        'participates_in_medicare',
        'participates_in_medicaid',
        'available_services',
        'specialty_services',
        'equipment_inventory_summary',
        'has_emergency_department',
        'has_trauma_center',
        'trauma_center_level',
        'has_intensive_care',
        'has_neonatal_icu',
        'has_cardiac_cath_lab',
        'data_residency_region',
        'primary_database_shard',
        'replica_shard_locations',
        'average_wait_time_minutes',
        'patient_satisfaction_score',
        'monthly_patient_volume',
        'operational_status',
        'created_by_staff_id',
        'updated_by_staff_id',
        'metadata',
                /*
        |--------------------------------------------------------------------------
        | 🆕 Facility Financial Configuration
        |--------------------------------------------------------------------------
        */
        'currency',
        'tax_enabled',
        'tax_name',
        'tax_rate',

        /*
        |--------------------------------------------------------------------------
        | 🆕 Facility Branding
        |--------------------------------------------------------------------------
        */
        'facility_logo_path',
        'primary_brand_color',
        'secondary_brand_color',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'facility_uuid' => 'string',
        'is_24_7' => 'boolean',
        'bed_capacity' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
        'participates_in_medicare' => 'boolean',
        'participates_in_medicaid' => 'boolean',
        'has_emergency_department' => 'boolean',
        'has_trauma_center' => 'boolean',
        'trauma_center_level' => 'integer',
        'has_intensive_care' => 'boolean',
        'has_neonatal_icu' => 'boolean',
        'has_cardiac_cath_lab' => 'boolean',
        'average_wait_time_minutes' => 'float',
        'patient_satisfaction_score' => 'float',
        'monthly_patient_volume' => 'integer',
        'accreditations' => 'array',
        'operating_hours' => 'array',
        'emergency_services_hours' => 'array',
        'affiliated_facility_ids' => 'array',
        'referral_network_facility_ids' => 'array',
        'regulatory_identifiers' => 'array',
        'available_services' => 'array',
        'specialty_services' => 'array',
        'equipment_inventory_summary' => 'array',
        'replica_shard_locations' => 'array',
        'license_expiry_date' => 'date',
        'metadata' => 'array',
        'deleted_at' => 'datetime',
        'tax_enabled' => 'boolean',
        'tax_rate' => 'float',
    ];

    /**
     * Get the validation rules for the model.
     *
     * @return array<string, string>
     */
    public static function rules(): array
    {
        return [
            'facility_uuid' => 'required|uuid|unique:facilities,facility_uuid',
            'facility_code' => 'required|string|max:50|unique:facilities,facility_code',
            'facility_name' => 'required|string|max:200',
            'legal_entity_name' => 'required|string|max:200',
            'tax_id_encrypted' => 'nullable|string|max:512',
            'facility_type' => 'required|in:hospital,clinic,urgent_care,emergency_department,ambulatory_surgery_center,diagnostic_center,rehabilitation_center,long_term_care,hospice,community_health_center,specialty_center,telehealth_hub',
            'facility_tier' => 'required|in:tertiary,secondary,primary,specialized',
            'bed_capacity' => 'nullable|integer|min:0|max:65535',
            'accreditations' => 'nullable|array',
            'address_line1' => 'required|string|max:200',
            'address_line2' => 'nullable|string|max:200',
            'city' => 'required|string|max:100',
            'state_province' => 'required|string|max:100',
            'postal_code' => 'required|string|max:20',
            'country_code' => 'required|string|size:3',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'timezone' => 'required|string|max:50',
            'main_phone' => 'required|string|max:50',
            'emergency_phone' => 'nullable|string|max:50',
            'fax' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:200',
            'website' => 'nullable|url|max:255',
            'operating_hours' => 'required|array',
            'emergency_services_hours' => 'nullable|array',
            'is_24_7' => 'boolean',
            'parent_organization_id' => 'nullable|exists:organizations,id',
            'affiliated_facility_ids' => 'nullable|array',
            'referral_network_facility_ids' => 'nullable|array',
            'health_system_name' => 'nullable|string|max:200',
            'license_number' => 'nullable|string|max:100',
            'license_issuing_authority' => 'nullable|string|max:200',
            'license_expiry_date' => 'nullable|date',
            'regulatory_identifiers' => 'nullable|array',
            'participates_in_medicare' => 'boolean',
            'participates_in_medicaid' => 'boolean',
            'available_services' => 'required|array',
            'specialty_services' => 'nullable|array',
            'equipment_inventory_summary' => 'nullable|array',
            'has_emergency_department' => 'boolean',
            'has_trauma_center' => 'boolean',
            'trauma_center_level' => 'nullable|integer|between:1,5',
            'has_intensive_care' => 'boolean',
            'has_neonatal_icu' => 'boolean',
            'has_cardiac_cath_lab' => 'boolean',
            'data_residency_region' => 'required|string|max:10',
            'primary_database_shard' => 'required|string|max:50',
            'replica_shard_locations' => 'nullable|array',
            'average_wait_time_minutes' => 'nullable|numeric|min:0|max:999.99',
            'patient_satisfaction_score' => 'nullable|numeric|min:0|max:5',
            'monthly_patient_volume' => 'nullable|integer|min:0',
            'operational_status' => 'required|in:fully_operational,limited_services,emergency_only,temporarily_closed,permanently_closed,under_construction',
            'created_by_staff_id' => 'nullable|exists:staff,id',
            'updated_by_staff_id' => 'nullable|exists:staff,id',
            'metadata' => 'nullable|array',
                        /*
            |--------------------------------------------------------------------------
            | 🆕 Financial Configuration Validation
            |--------------------------------------------------------------------------
            */
            'currency' => 'required|string|size:3',
            'tax_enabled' => 'boolean',
            'tax_name' => 'nullable|string|max:50',
            'tax_rate' => 'nullable|numeric|min:0|max:100',

            /*
            |--------------------------------------------------------------------------
            | 🆕 Branding Validation
            |--------------------------------------------------------------------------
            */
            'facility_logo_path' => 'nullable|string|max:512',
            'primary_brand_color' => [
                'nullable',
                'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'
            ],
            'secondary_brand_color' => [
                'nullable',
                'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'
            ],
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($facility) {
            if (empty($facility->facility_uuid)) {
                $facility->facility_uuid = HealthcareIdGenerator::generate('facility');
            }
        });
    }

    /**
     * Get the paren tFacility  relationship.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function parentOrganization(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'parent_organization_id');
    }

    /**
     * Get the staff member who created this facility.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by_staff_id');
    }

    /**
     * Get the staff member who last updated this facility.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'updated_by_staff_id');
    }

    /**
     * Scope a query to only include facilities in a specific region.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $countryCode
     * @param string|null $stateProvince
     * @param string|null $city
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeInRegion($query, string $countryCode, ?string $stateProvince = null, ?string $city = null)
    {
        $query->where('country_code', $countryCode);
        
        if ($stateProvince) {
            $query->where('state_province', $stateProvince);
        }
        
        if ($city) {
            $query->where('city', $city);
        }
        
        return $query;
    }

    /**
     * Scope a query to only include facilities of a specific type.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('facility_type', $type);
    }

    /**
     * Scope a query to only include facilities with a specific operational status.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $status
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('operational_status', $status);
    }

    /**
     * Scope a query to only include facilities with emergency department.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithEmergencyDepartment($query)
    {
        return $query->where('has_emergency_department', true);
    }

    /**
     * Check if the facility is fully operational.
     *
     * @return bool
     */
    public function isFullyOperational(): bool
    {
        return $this->operational_status === 'fully_operational';
    }

    /**
     * Check if the facility is closed.
     *
     * @return bool
     */
    public function isClosed(): bool
    {
        return in_array($this->operational_status, ['temporarily_closed', 'permanently_closed']);
    }

    /**
     * Get the full address as a single string.
     *
     * @return string
     */
    public function getFullAddressAttribute(): string
    {
        $address = $this->address_line1;
        
        if ($this->address_line2) {
            $address .= ', ' . $this->address_line2;
        }
        
        $address .= ', ' . $this->city;
        $address .= ', ' . $this->state_province;
        $address .= ' ' . $this->postal_code;
        $address .= ', ' . $this->country_code;
        
        return $address;
    }

    /**
     * Get the coordinates as an array.
     *
     * @return array|null
     */
    public function getCoordinatesAttribute(): ?array
    {
        if ($this->latitude && $this->longitude) {
            return [
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
            ];
        }
        
        return null;
    }

/**
 * The facility's current (latest) subscription.
 */
public function subscription(): HasOne
{
    return $this->hasOne(Subscription::class)->latestOfMany();
}

/**
 * All subscriptions this facility has ever had.
 */
public function subscriptions(): HasMany
{
    return $this->hasMany(Subscription::class);
}

/**
 * All payments made by this facility.
 */
public function payments(): HasMany
{
    return $this->hasMany(Payment::class);
}

}