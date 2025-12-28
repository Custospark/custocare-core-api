<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;

class DataResidencyRule extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'region_code',
        'region_name',
        'data_category',
        'allowed_storage_regions',
        'allowed_processing_regions',
        'allowed_backup_regions',
        'prohibited_regions',
        'encryption_requirements',
        'encryption_at_rest_required',
        'encryption_in_transit_required',
        'encryption_in_use_required',
        'cross_border_transfer_approval_required',
        'approval_authority',
        'transfer_mechanisms',
        'minimum_retention_period_years',
        'maximum_retention_period_years',
        'retention_basis',
        'right_to_erasure_applicable',
        'erasure_exceptions',
        'erasure_response_time_days',
        'breach_notification_hours',
        'notification_authorities',
        'applicable_regulations',
        'regulation_summary',
        'legal_reference_url',
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
        'allowed_storage_regions' => 'array',
        'allowed_processing_regions' => 'array',
        'allowed_backup_regions' => 'array',
        'prohibited_regions' => 'array',
        'encryption_requirements' => 'array',
        'encryption_at_rest_required' => 'boolean',
        'encryption_in_transit_required' => 'boolean',
        'encryption_in_use_required' => 'boolean',
        'cross_border_transfer_approval_required' => 'boolean',
        'approval_authority' => 'array',
        'transfer_mechanisms' => 'array',
        'right_to_erasure_applicable' => 'boolean',
        'erasure_exceptions' => 'array',
        'notification_authorities' => 'array',
        'applicable_regulations' => 'array',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Data category options with descriptions
     */
    public const DATA_CATEGORIES = [
        'clinical_records' => 'Clinical Records',
        'financial_data' => 'Financial Data',
        'identity_information' => 'Identity Information',
        'audit_logs' => 'Audit Logs',
        'research_data' => 'Research Data',
        'genomic_data' => 'Genomic Data',
    ];

    /**
     * Status options
     */
    public const STATUSES = [
        'active' => 'Active',
        'under_review' => 'Under Review',
        'superseded' => 'Superseded',
    ];

    /**
     * Retention basis options
     */
    public const RETENTION_BASIS = [
        'legal_requirement' => 'Legal Requirement',
        'business_need' => 'Business Need',
        'consent_based' => 'Consent Based',
    ];

    /**
     * Relationship with staff who created the rule
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function createdBy()
    {
        return $this->belongsTo(Staff::class, 'created_by_staff_id');
    }

    /**
     * Scope for active rules
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
                    ->where(function ($q) {
                        $q->whereNull('effective_to')
                          ->orWhere('effective_to', '>=', now()->toDateString());
                    })
                    ->where('effective_from', '<=', now()->toDateString());
    }

    /**
     * Scope for rules by data category
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $category
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByDataCategory($query, string $category)
    {
        return $query->where('data_category', $category);
    }

    /**
     * Scope for rules by region
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $regionCode
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByRegion($query, string $regionCode)
    {
        return $query->where('region_code', $regionCode);
    }

    /**
     * Check if the rule is currently effective
     *
     * @return bool
     */
    public function isEffective(): bool
    {
        $today = now()->toDateString();
        
        return $this->status === 'active' &&
               $this->effective_from <= $today &&
               (is_null($this->effective_to) || $this->effective_to >= $today);
    }

    /**
     * Get the display name for the rule
     *
     * @return string
     */
    public function getDisplayNameAttribute(): string
    {
        return "{$this->region_name} - " . 
               (self::DATA_CATEGORIES[$this->data_category] ?? $this->data_category);
    }

    /**
     * Check if cross-border transfer is allowed for a specific region
     *
     * @param string $regionCode
     * @return bool
     */
    public function allowsCrossBorderTransferTo(string $regionCode): bool
    {
        if (empty($this->prohibited_regions)) {
            return true;
        }

        return !in_array($regionCode, $this->prohibited_regions);
    }

    /**
     * Get all regions where storage is allowed
     *
     * @return array
     */
    public function getAllowedStorageRegionsAttribute($value): array
    {
        $regions = json_decode($value, true) ?? [];
        return array_unique($regions);
    }

    /**
     * Validation rule for region code format
     *
     * @return string
     */
    public static function getRegionCodeValidationRule(): string
    {
        return 'required|string|max:10|regex:/^[A-Z]{2}(-[A-Z0-9]{2,7})?$/';
    }
}