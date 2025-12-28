<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @OA\Schema(
 *     schema="ServiceCatalog",
 *     type="object",
 *     required={"service_code", "code_system", "service_name", "service_category", "applicable_region", "effective_from"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="service_uuid", type="string", format="uuid", example="123e4567-e89b-12d3-a456-426614174000"),
 *     @OA\Property(property="service_code", type="string", maxLength=50, example="99213"),
 *     @OA\Property(property="code_system", type="string", enum={"cpt", "hcpcs", "icd_10_pcs", "cdt", "local_custom"}),
 *     @OA\Property(property="service_name", type="string", maxLength=300, example="Office or other outpatient visit"),
 *     @OA\Property(property="service_description", type="string", nullable=true),
 *     @OA\Property(property="alternate_names", type="array", nullable=true, @OA\Items(type="string")),
 *     @OA\Property(property="service_category", type="string", enum={"evaluation_management", "diagnostic_imaging", "laboratory_test", "surgical_procedure", "medical_procedure", "therapy_session", "preventive_care", "vaccination", "medication_administration", "emergency_service", "consultation", "anesthesia", "pathology", "radiology", "facility_fee"}),
 *     @OA\Property(property="service_subcategories", type="array", nullable=true, @OA\Items(type="string")),
 *     @OA\Property(property="department_specialty", type="string", maxLength=100, nullable=true, example="Cardiology"),
 *     @OA\Property(property="regulatory_approval_status", type="object", nullable=true),
 *     @OA\Property(property="required_certifications", type="array", nullable=true, @OA\Items(type="string")),
 *     @OA\Property(property="minimum_required_credentials", type="array", nullable=true, @OA\Items(type="string")),
 *     @OA\Property(property="required_equipment", type="array", nullable=true, @OA\Items(type="string")),
 *     @OA\Property(property="required_facility_capabilities", type="array", nullable=true, @OA\Items(type="string")),
 *     @OA\Property(property="default_duration_minutes", type="integer", nullable=true, example=30),
 *     @OA\Property(property="typical_indications", type="array", nullable=true, @OA\Items(type="string")),
 *     @OA\Property(property="contraindications", type="array", nullable=true, @OA\Items(type="string")),
 *     @OA\Property(property="prerequisites", type="array", nullable=true, @OA\Items(type="string")),
 *     @OA\Property(property="commonly_paired_services", type="array", nullable=true, @OA\Items(type="string")),
 *     @OA\Property(property="risk_level", type="string", enum={"low", "moderate", "high", "critical"}, default="low"),
 *     @OA\Property(property="requires_informed_consent", type="boolean", default=false),
 *     @OA\Property(property="consent_form_template", type="string", maxLength=200, nullable=true),
 *     @OA\Property(property="applicable_region", type="string", maxLength=10, example="US"),
 *     @OA\Property(property="approved_countries", type="array", nullable=true, @OA\Items(type="string")),
 *     @OA\Property(property="state_specific_regulations", type="object", nullable=true),
 *     @OA\Property(property="status", type="string", enum={"active", "inactive", "deprecated", "under_review"}, default="active"),
 *     @OA\Property(property="effective_from", type="string", format="date", example="2024-01-01"),
 *     @OA\Property(property="effective_to", type="string", format="date", nullable=true),
 *     @OA\Property(property="created_by_staff_id", type="integer", nullable=true),
 *     @OA\Property(property="metadata", type="object", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true)
 * )
 */
class ServiceCatalog extends Model
{
    use HasFactory, SoftDeletes, HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'service_catalogs';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'int';

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
        'service_uuid',
        'service_code',
        'code_system',
        'service_name',
        'service_description',
        'alternate_names',
        'service_category',
        'service_subcategories',
        'department_specialty',
        'regulatory_approval_status',
        'required_certifications',
        'minimum_required_credentials',
        'required_equipment',
        'required_facility_capabilities',
        'default_duration_minutes',
        'typical_indications',
        'contraindications',
        'prerequisites',
        'commonly_paired_services',
        'risk_level',
        'requires_informed_consent',
        'consent_form_template',
        'applicable_region',
        'approved_countries',
        'state_specific_regulations',
        'status',
        'effective_from',
        'effective_to',
        'created_by_staff_id',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'alternate_names' => 'array',
        'service_subcategories' => 'array',
        'regulatory_approval_status' => 'array',
        'required_certifications' => 'array',
        'minimum_required_credentials' => 'array',
        'required_equipment' => 'array',
        'required_facility_capabilities' => 'array',
        'typical_indications' => 'array',
        'contraindications' => 'array',
        'prerequisites' => 'array',
        'commonly_paired_services' => 'array',
        'approved_countries' => 'array',
        'state_specific_regulations' => 'array',
        'requires_informed_consent' => 'boolean',
        'default_duration_minutes' => 'integer',
        'created_by_staff_id' => 'integer',
        'metadata' => 'array',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'deleted_at' => 'datetime',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array<int, string>
     */
    protected $dates = [
        'effective_from',
        'effective_to',
        'deleted_at',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'risk_level' => 'low',
        'requires_informed_consent' => false,
        'status' => 'active',
    ];

    /**
     * Get the route key for the model.
     *
     * @return string
     */
    public function getRouteKeyName(): string
    {
        return 'service_uuid';
    }

    /**
     * Scope a query to only include active services.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include services effective on a given date.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $date
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
     * Scope a query to only include services by code system.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $codeSystem
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByCodeSystem($query, string $codeSystem)
    {
        return $query->where('code_system', $codeSystem);
    }

    /**
     * Scope a query to only include services by category.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $category
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('service_category', $category);
    }

    /**
     * Check if the service is currently effective.
     *
     * @param  string|null  $date
     * @return bool
     */
    public function isEffective(?string $date = null): bool
    {
        $date = $date ?: now()->toDateString();
        
        if ($this->effective_from > $date) {
            return false;
        }
        
        if ($this->effective_to && $this->effective_to < $date) {
            return false;
        }
        
        return true;
    }

    /**
     * Check if the service requires specific credentials.
     *
     * @return bool
     */
    public function hasRequiredCredentials(): bool
    {
        return !empty($this->minimum_required_credentials);
    }

    /**
     * Get the consent requirement status.
     *
     * @return bool
     */
    public function requiresConsent(): bool
    {
        return $this->requires_informed_consent;
    }

    /**
     * Relationship with staff who created the service catalog entry.
     * Note: This assumes a Staff model exists in the application.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function createdBy()
    {
        return $this->belongsTo(\App\Models\Staff::class, 'created_by_staff_id');
    }
}