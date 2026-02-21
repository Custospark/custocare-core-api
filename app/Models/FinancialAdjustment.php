<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinancialAdjustment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'adjustment_uuid',
        'facility_id',
        'billing_cycle_id',
        'visit_id',
        'patient_id',
        'adjustment_type',
        'adjustment_reason',
        'reason_notes',
        'original_amount',
        'adjustment_amount',
        'remaining_amount',
        'patient_refund_amount',
        'insurance_refund_amount',
        'refund_methods',
        'affected_line_items',
        'restore_inventory',
        'inventory_restored',
        'status',
        'approved_at',
        'completed_at',
        'requested_by_staff_id',
        'approved_by_staff_id',
        'reference_number',
        'original_billing_snapshot',
        'metadata',
    ];

    protected $casts = [
        'original_amount' => 'decimal:2',
        'adjustment_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'patient_refund_amount' => 'decimal:2',
        'insurance_refund_amount' => 'decimal:2',
        'refund_methods' => 'array',
        'affected_line_items' => 'array',
        'restore_inventory' => 'boolean',
        'inventory_restored' => 'array',
        'original_billing_snapshot' => 'array',
        'metadata' => 'array',
        'approved_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // Relationships
    public function billingCycle()
    {
        return $this->belongsTo(BillingCycle::class);
    }

    public function visit()
    {
        return $this->belongsTo(Visit::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function requestedBy()
    {
        return $this->belongsTo(Staff::class, 'requested_by_staff_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(Staff::class, 'approved_by_staff_id');
    }
     public function requestedByStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'requested_by_staff_id', 'id');
    }
}