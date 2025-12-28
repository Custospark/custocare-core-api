<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\InventoryLedger
 *
 * @property int $id
 * @property string $transaction_uuid
 * @property int $facility_id
 * @property int $inventory_item_id
 * @property string $transaction_type
 * @property float $quantity_change
 * @property float $balance_after_transaction
 * @property string $unit_of_measure
 * @property string|null $lot_number
 * @property string|null $serial_number
 * @property \Illuminate\Support\Carbon|null $expiry_date
 * @property \Illuminate\Support\Carbon|null $manufacture_date
 * @property float|null $unit_cost_at_transaction
 * @property float|null $total_cost
 * @property int|null $reference_visit_id
 * @property int|null $reference_prescription_id
 * @property int|null $reference_purchase_order_id
 * @property int|null $transfer_from_facility_id
 * @property int|null $transfer_to_facility_id
 * @property string $transaction_cause
 * @property string|null $transaction_notes
 * @property string|null $reference_document_number
 * @property int|null $performed_by_staff_id
 * @property int|null $verified_by_staff_id
 * @property \Illuminate\Support\Carbon|null $verified_at
 * @property string|null $storage_location
 * @property int|null $department_id
 * @property \Illuminate\Support\Carbon $transaction_timestamp
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property string $transaction_hash
 * @property array|null $metadata
 * @property-read \App\Models\Facility|null $facility
 * @property-read \App\Models\InventoryItem|null $inventoryItem
 * @property-read \App\Models\Staff|null $performedByStaff
 * @property-read \App\Models\Visit|null $referenceVisit
 * @property-read \App\Models\Staff|null $verifiedByStaff
 */
class InventoryLedger extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'inventory_ledger';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'transaction_uuid',
        'facility_id',
        'inventory_item_id',
        'transaction_type',
        'quantity_change',
        'balance_after_transaction',
        'unit_of_measure',
        'lot_number',
        'serial_number',
        'expiry_date',
        'manufacture_date',
        'unit_cost_at_transaction',
        'total_cost',
        'reference_visit_id',
        'reference_prescription_id',
        'reference_purchase_order_id',
        'transfer_from_facility_id',
        'transfer_to_facility_id',
        'transaction_cause',
        'transaction_notes',
        'reference_document_number',
        'performed_by_staff_id',
        'verified_by_staff_id',
        'verified_at',
        'storage_location',
        'department_id',
        'transaction_timestamp',
        'transaction_hash',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'transaction_timestamp' => 'datetime',
        'expiry_date' => 'date',
        'manufacture_date' => 'date',
        'verified_at' => 'datetime',
        'quantity_change' => 'decimal:2',
        'balance_after_transaction' => 'decimal:2',
        'unit_cost_at_transaction' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'metadata' => 'array',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @deprecated Use the "casts" property
     * @var array<string>
     */
    protected $dates = [
        'transaction_timestamp',
        'expiry_date',
        'manufacture_date',
        'verified_at',
    ];

    /**
     * Get the facility associated with the ledger entry.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * Get the inventory item associated with the ledger entry.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    /**
     * Get the visit associated with the ledger entry.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function referenceVisit(): BelongsTo
    {
        return $this->belongsTo(Visit::class, 'reference_visit_id');
    }

    /**
     * Get the staff member who performed the transaction.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function performedByStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'performed_by_staff_id');
    }

    /**
     * Get the staff member who verified the transaction.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function verifiedByStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'verified_by_staff_id');
    }

    /**
     * Scope a query to only include positive quantity changes (incoming).
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeIncoming($query)
    {
        return $query->where('quantity_change', '>', 0);
    }

    /**
     * Scope a query to only include negative quantity changes (outgoing).
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOutgoing($query)
    {
        return $query->where('quantity_change', '<', 0);
    }

    /**
     * Scope a query by facility.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $facilityId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByFacility($query, int $facilityId)
    {
        return $query->where('facility_id', $facilityId);
    }

    /**
     * Scope a query by inventory item.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $inventoryItemId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByInventoryItem($query, int $inventoryItemId)
    {
        return $query->where('inventory_item_id', $inventoryItemId);
    }

    /**
     * Scope a query by transaction type.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string|array $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByTransactionType($query, $type)
    {
        if (is_array($type)) {
            return $query->whereIn('transaction_type', $type);
        }
        
        return $query->where('transaction_type', $type);
    }

    /**
     * Get the net quantity change for a given item and facility.
     *
     * @param int $facilityId
     * @param int $inventoryItemId
     * @return float
     */
    public static function getCurrentBalance(int $facilityId, int $inventoryItemId): float
    {
        $latestEntry = static::where('facility_id', $facilityId)
            ->where('inventory_item_id', $inventoryItemId)
            ->latest('transaction_timestamp')
            ->first();
        
        return $latestEntry ? (float) $latestEntry->balance_after_transaction : 0.0;
    }
}