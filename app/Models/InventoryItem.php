<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryItem extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'item_uuid',
        'item_code',
        'item_name',
        'facility_id',
        'item_description',
        'item_category',
        'item_subcategory',
        'generic_name',
        'brand_name',
        'ndc_code',
        'drug_class',
        'controlled_substance_schedule',
        'active_ingredients',
        'dosage_form',
        'strength',
        'route_of_administration',
        'manufacturer',
        'manufacturer_item_number',
        'supplier',
        'unit_of_measure',
        'package_quantity',
        'packaging_type',
        'unit_cost',
        'average_wholesale_price',
        'currency_code',
        'storage_requirements',
        'requires_refrigeration',
        'requires_controlled_access',
        'storage_location_type',
        'requires_prescription',
        'regulatory_approvals',
        'fda_approval_number',
        'is_hazardous',
        'safety_warnings',
        'contraindications',
        'special_handling_instructions',
        'is_billable',
        'track_by_lot',
        'track_by_serial',
        'reorder_point',
        'reorder_quantity',
        'safety_stock_level',
        'max_stock_level',
        'status',
        'created_by_staff_id',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    /**
     * Reasonable defaults applied before hitting the database.
     * Keeps direct ::create() callers safe even if a service
     * forgets to fill an optional/system field.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'item_category' => 'other',
        'unit_of_measure' => 'each',
        'package_quantity' => 1,
        'currency_code' => 'UGX',
        'status' => 'active',
        'requires_refrigeration' => false,
        'requires_controlled_access' => false,
        'requires_prescription' => false,
        'is_hazardous' => false,
        'is_billable' => true,
        'track_by_lot' => false,
        'track_by_serial' => false,
    ];

    protected $casts = [
        'item_uuid' => 'string',
        'active_ingredients' => 'array',
        'storage_requirements' => 'array',
        'regulatory_approvals' => 'array',
        'safety_warnings' => 'array',
        'contraindications' => 'array',
        'metadata' => 'array',
        'unit_cost' => 'decimal:2',
        'average_wholesale_price' => 'decimal:2',
        'requires_refrigeration' => 'boolean',
        'requires_controlled_access' => 'boolean',
        'requires_prescription' => 'boolean',
        'is_hazardous' => 'boolean',
        'is_billable' => 'boolean',
        'track_by_lot' => 'boolean',
        'track_by_serial' => 'boolean',
        'package_quantity' => 'integer',
        'reorder_point' => 'integer',
        'reorder_quantity' => 'integer',
        'safety_stock_level' => 'integer',
        'max_stock_level' => 'integer',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'deleted_at',
    ];

    protected static function booted(): void
    {
        // Defense-in-depth: never allow a null/blank item_code to reach
        // the DB NOT NULL column (1048). Import service already auto-generates,
        // this covers single-create and any direct ::create() callers.
        static::creating(function (self $item) {
            if (!isset($item->item_code) || trim((string) $item->item_code) === '') {
                $item->item_code = 'INVT-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            }
        });
    }

    /**
     * Get the route key for the model.
     *
     * @return string
     */
    public function getRouteKeyName()
    {
        return 'item_uuid';
    }

    /**
     * Scope a query to only include active items.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include medications.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeMedications($query)
    {
        return $query->where('item_category', 'medication');
    }

    /**
     * Scope a query to only include controlled substances.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeControlledSubstances($query)
    {
        return $query->whereNotNull('controlled_substance_schedule');
    }

    /**
     * Check if item is a controlled substance.
     *
     * @return bool
     */
    public function isControlledSubstance(): bool
    {
        return !is_null($this->controlled_substance_schedule) && 
               $this->controlled_substance_schedule !== 'non_controlled';
    }

    /**
     * Check if item requires special handling.
     *
     * @return bool
     */
    public function requiresSpecialHandling(): bool
    {
        return $this->is_hazardous || 
               $this->requires_refrigeration || 
               $this->requires_controlled_access || 
               !empty($this->special_handling_instructions);
    }

    /**
     * Get the creator of this inventory item.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function createdBy()
    {
        return $this->belongsTo(Staff::class, 'created_by_staff_id');
    }
}