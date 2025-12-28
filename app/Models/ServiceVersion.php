<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * ServiceVersion Model
 * 
 * Represents versioned pricing and terms for services to ensure historical billing accuracy.
 * This model tracks changes in pricing, coverage, and billing rules over time.
 * 
 * @property int $id
 * @property string $version_uuid
 * @property int $service_catalog_id
 * @property int|null $facility_id
 * @property string $version_number
 * @property string $valid_from
 * @property string|null $valid_to
 * @property bool $is_current
 * @property string $currency_code
 * @property float $base_price_amount
 * @property float|null $facility_markup_percentage
 * @property float $final_price_amount
 * @property array|null $insurance_coverage_rates
 * @property bool $requires_preauthorization
 * @property array|null $preauthorization_criteria
 * @property int|null $preauth_processing_days
 * @property bool $is_billable
 * @property string $billing_method
 * @property float $minimum_billable_units
 * @property float|null $maximum_billable_units
 * @property array|null $bundled_service_ids
 * @property array|null $allowed_modifiers
 * @property array|null $modifier_price_adjustments
 * @property string|null $documentation_requirements
 * @property string|null $medical_necessity_criteria
 * @property array|null $required_diagnosis_codes
 * @property float|null $direct_cost
 * @property float|null $indirect_cost
 * @property float|null $target_margin_percentage
 * @property array $version_snapshot
 * @property string $version_hash
 * @property string|null $change_notes
 * @property int|null $created_by_staff_id
 * @property array|null $metadata
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class ServiceVersion extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'service_versions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'version_uuid',
        'service_catalog_id',
        'facility_id',
        'version_number',
        'valid_from',
        'valid_to',
        'is_current',
        'currency_code',
        'base_price_amount',
        'facility_markup_percentage',
        'final_price_amount',
        'insurance_coverage_rates',
        'requires_preauthorization',
        'preauthorization_criteria',
        'preauth_processing_days',
        'is_billable',
        'billing_method',
        'minimum_billable_units',
        'maximum_billable_units',
        'bundled_service_ids',
        'allowed_modifiers',
        'modifier_price_adjustments',
        'documentation_requirements',
        'medical_necessity_criteria',
        'required_diagnosis_codes',
        'direct_cost',
        'indirect_cost',
        'target_margin_percentage',
        'version_snapshot',
        'version_hash',
        'change_notes',
        'created_by_staff_id',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'version_uuid' => 'string',
        'is_current' => 'boolean',
        'base_price_amount' => 'decimal:2',
        'facility_markup_percentage' => 'decimal:2',
        'final_price_amount' => 'decimal:2',
        'insurance_coverage_rates' => 'array',
        'requires_preauthorization' => 'boolean',
        'preauthorization_criteria' => 'array',
        'is_billable' => 'boolean',
        'minimum_billable_units' => 'decimal:2',
        'maximum_billable_units' => 'decimal:2',
        'bundled_service_ids' => 'array',
        'allowed_modifiers' => 'array',
        'modifier_price_adjustments' => 'array',
        'required_diagnosis_codes' => 'array',
        'direct_cost' => 'decimal:2',
        'indirect_cost' => 'decimal:2',
        'target_margin_percentage' => 'decimal:2',
        'version_snapshot' => 'array',
        'metadata' => 'array',
        'valid_from' => 'date:Y-m-d',
        'valid_to' => 'date:Y-m-d',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'version_hash',
    ];

    /**
     * The "booted" method of the model.
     * Ensures version_uuid is generated on creation.
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::creating(function (ServiceVersion $serviceVersion) {
            if (empty($serviceVersion->version_uuid)) {
                $serviceVersion->version_uuid = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    /**
     * Get the service catalog that owns this version.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function serviceCatalog(): BelongsTo
    {
        return $this->belongsTo(ServiceCatalog::class, 'service_catalog_id');
    }

    /**
     * Get the facility that this version is specific to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'facility_id');
    }

    /**
     * Get the staff member who created this version.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by_staff_id');
    }

    /**
     * Scope to get current versions.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    /**
     * Scope to get versions valid for a specific date.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $date Date in Y-m-d format
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeValidOnDate($query, string $date)
    {
        return $query->where('valid_from', '<=', $date)
                     ->where(function ($q) use ($date) {
                         $q->where('valid_to', '>=', $date)
                           ->orWhereNull('valid_to');
                     });
    }

    /**
     * Scope to get versions by service catalog.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $serviceCatalogId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByServiceCatalog($query, int $serviceCatalogId)
    {
        return $query->where('service_catalog_id', $serviceCatalogId);
    }

    /**
     * Scope to get versions by facility.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int|null $facilityId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByFacility($query, ?int $facilityId)
    {
        return $query->where('facility_id', $facilityId);
    }

    /**
     * Calculate the facility markup amount.
     *
     * @return float
     */
    public function calculateFacilityMarkupAmount(): float
    {
        if (empty($this->facility_markup_percentage)) {
            return 0;
        }
        
        return ($this->base_price_amount * $this->facility_markup_percentage) / 100;
    }

    /**
     * Check if this version is currently valid.
     *
     * @return bool
     */
    public function isCurrentlyValid(): bool
    {
        $today = now()->format('Y-m-d');
        
        return $this->valid_from <= $today && 
               ($this->valid_to === null || $this->valid_to >= $today);
    }

    /**
     * Accessor for display price with currency.
     *
     * @return \Illuminate\Database\Eloquent\Casts\Attribute
     */
    protected function displayPrice(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->currency_code . ' ' . number_format($this->final_price_amount, 2)
        );
    }

    /**
     * Accessor for insurance coverage summary.
     *
     * @return \Illuminate\Database\Eloquent\Casts\Attribute
     */
    protected function insuranceCoverageSummary(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (empty($this->insurance_coverage_rates)) {
                    return [];
                }
                
                return collect($this->insurance_coverage_rates)
                    ->map(function ($rate, $type) {
                        return [
                            'type' => $type,
                            'coverage_percentage' => $rate,
                            'patient_portion' => $this->final_price_amount * ((100 - $rate) / 100)
                        ];
                    })
                    ->values()
                    ->toArray();
            }
        );
    }
}