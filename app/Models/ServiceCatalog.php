<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceCatalog extends Model
{
    use HasFactory, SoftDeletes;

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
        'facility_id',
        'currency_code',
        'price_amount',
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
        return 'id';
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
    // In ServiceCatalog model
public function scopeActive($query)
{
    return $query->where('status', 'active');
}

public function scopeEffectiveOn($query, string $date)
{
    return $query->where('effective_from', '<=', $date)
        ->where(function ($q) use ($date) {
            $q->where('effective_to', '>=', $date)
              ->orWhereNull('effective_to');
        });
}

public function scopeByCodeSystem($query, string $codeSystem)
{
    return $query->where('code_system', $codeSystem);
}

public function scopeByCategory($query, string $category)
{
    return $query->where('service_category', $category);
}
}