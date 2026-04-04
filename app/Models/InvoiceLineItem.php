<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

 
class InvoiceLineItem extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'line_item_uuid',
        'billing_cycle_id',
        'inventory_item_id',
        'service_catalog_id',
        'visit_id',
        'service_version_id',
        'service_version_snapshot',
        'service_code',
        'service_description',
        'quantity',
        'unit_of_measure',
        'unit_price_at_time',
        'line_total_amount',
        'applied_discount_percentage',
        'discount_amount',
        'adjustment_amount',
        'adjustment_reason',
        'net_amount',
        'department_id',
        'staff_performed_id',
        'service_performed_at',
        'service_duration_minutes',
        'diagnosis_codes',
        'medical_necessity_notes',
        'modifier_codes',
        'revenue_code',
        'procedure_code',
        'insurance_specific_codes',
        'preauthorization_number',
        'requires_review',
        'coding_reviewed',
        'reviewed_by_staff_id',
        'reviewed_at',
        'line_item_status',
        'audit_trail_hash',
        'created_by_staff_id',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'service_version_snapshot' => 'array',
        'diagnosis_codes' => 'array',
        'modifier_codes' => 'array',
        'insurance_specific_codes' => 'array',
        'requires_review' => 'boolean',
        'coding_reviewed' => 'boolean',
        'service_performed_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'quantity' => 'decimal:2',
        'unit_price_at_time' => 'decimal:2',
        'line_total_amount' => 'decimal:2',
        'applied_discount_percentage' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'adjustment_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'metadata' => 'array',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'service_performed_at',
        'reviewed_at',
    ];

    /**
     * Status enumeration for type safety
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_BILLED = 'billed';
    public const STATUS_PAID = 'paid';
    public const STATUS_DENIED = 'denied';
    public const STATUS_ADJUSTED = 'adjusted';
    public const STATUS_WRITTEN_OFF = 'written_off';

    /**
     * Default status for new line items
     */
    public const DEFAULT_STATUS = self::STATUS_PENDING;

    /**
     * Boot the model
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->line_item_uuid)) {
                $model->line_item_uuid = (string) \Illuminate\Support\Str::uuid();
            }
            
            // Generate audit trail hash for data integrity
            $model->audit_trail_hash = $model->generateAuditTrailHash();
        });

        static::updating(function ($model) {
            // Recalculate audit trail hash on update
            $model->audit_trail_hash = $model->generateAuditTrailHash();
        });
    }

    /**
     * Generate SHA-256 audit trail hash for tamper detection
     */
    public function generateAuditTrailHash(): string
    {
        $data = json_encode([
            'service_version_snapshot' => $this->service_version_snapshot,
            'unit_price_at_time' => $this->unit_price_at_time,
            'quantity' => $this->quantity,
            'discount_amount' => $this->discount_amount,
            'adjustment_amount' => $this->adjustment_amount,
            'net_amount' => $this->net_amount,
            'service_performed_at' => $this->service_performed_at?->toIso8601String(),
            'diagnosis_codes' => $this->diagnosis_codes,
            'modifier_codes' => $this->modifier_codes,
            'procedure_code' => $this->procedure_code,
            'metadata' => $this->metadata,
        ]);

        return hash('sha256', $data);
    }

    /**
     * Check if audit trail hash is valid (data integrity check)
     */
    public function isAuditTrailValid(): bool
    {
        return $this->audit_trail_hash === $this->generateAuditTrailHash();
    }

    /**
     * Relationship with billing cycle
     */
    public function billingCycle(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\BillingCycle::class);
    }

    /**
     * Relationship with service version
     */
    public function serviceVersion(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\ServiceVersion::class);
    }

    /**
     * Relationship with staff who performed the service
     */
    public function staffPerformed(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Staff::class, 'staff_performed_id');
    }

    /**
     * Relationship with reviewing staff
     */
    public function reviewedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Staff::class, 'reviewed_by_staff_id');
    }

    /**
     * Relationship with creator staff
     */
    public function createdBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Staff::class, 'created_by_staff_id');
    }

    /**
     * Scope for items requiring review
     */
    public function scopeRequiresReview($query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('requires_review', true);
    }

    /**
     * Scope by status
     */
    public function scopeByStatus($query, string $status): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('line_item_status', $status);
    }

    /**
     * Scope by date range
     */
    public function scopeBetweenDates($query, \Carbon\Carbon $startDate, \Carbon\Carbon $endDate): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereBetween('service_performed_at', [$startDate, $endDate]);
    }

    /**
     * Scope by billing cycle
     */
    public function scopeByBillingCycle($query, int $billingCycleId): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('billing_cycle_id', $billingCycleId);
    }


    /**
     * Check if line item is billable
     */
    public function isBillable(): bool
    {
        return in_array($this->line_item_status, [
            self::STATUS_APPROVED,
            self::STATUS_BILLED,
            self::STATUS_PAID,
        ]);
    }

    /**
     * Check if line item requires coding review
     */
    public function requiresCodingReview(): bool
    {
        return $this->requires_review && !$this->coding_reviewed;
    }

    /**
     * Mark as reviewed
     */
    public function markAsReviewed(int $reviewerId, ?\Carbon\Carbon $reviewedAt = null): bool
    {
        $this->coding_reviewed = true;
        $this->reviewed_by_staff_id = $reviewerId;
        $this->reviewed_at = $reviewedAt ?? now();
        
        return $this->save();
    }

    /**
     * Update status with validation
     */
    public function updateStatus(string $status, ?string $reason = null): bool
    {
        if (!in_array($status, [
            self::STATUS_PENDING,
            self::STATUS_APPROVED,
            self::STATUS_BILLED,
            self::STATUS_PAID,
            self::STATUS_DENIED,
            self::STATUS_ADJUSTED,
            self::STATUS_WRITTEN_OFF,
        ])) {
            return false;
        }

        $this->line_item_status = $status;
        
        if ($reason && $status === self::STATUS_ADJUSTED) {
            $this->adjustment_reason = $reason;
        }

        return $this->save();
    }
}