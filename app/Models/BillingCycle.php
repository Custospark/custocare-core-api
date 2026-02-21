<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BillingCycle extends Model
{
    use HasFactory, SoftDeletes;

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
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'billing_cycle_uuid',
        'facility_id',
        'visit_id',
        'patient_id',
        'cycle_type',
        'period_start',
        'period_end',
        'days_in_cycle',
        'total_amount_charged',
        'total_adjustments',
        'net_amount',
        'primary_insurance_claim_number',
        'insurance_covered_amount',
        'insurance_adjustment_amount',
        'insurance_payment_received',
        'insurance_claim_submitted_at',
        'insurance_payment_received_at',
        'patient_responsibility_amount',
        'patient_copay_amount',
        'patient_deductible_amount',
        'patient_coinsurance_amount',
        'patient_payment_received',
        'discount_applied',
        'discount_reason',
        'contractual_adjustment',
        'charity_care_adjustment',
        'bad_debt_adjustment',
        'tax_details',
        'total_tax_amount',
        'billing_status',
        'billed_at',
        'payment_due_date',
        'days_outstanding',
        'statement_count',
        'last_statement_sent_at',
        'sent_to_collections_at',
        'collections_agency',
        'is_disputed',
        'dispute_reason',
        'dispute_opened_at',
        'dispute_resolved_at',
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
        'billing_cycle_uuid' => 'string',
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'total_amount_charged' => 'decimal:2',
        'total_adjustments' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'insurance_covered_amount' => 'decimal:2',
        'insurance_adjustment_amount' => 'decimal:2',
        'insurance_payment_received' => 'decimal:2',
        'insurance_claim_submitted_at' => 'datetime',
        'insurance_payment_received_at' => 'datetime',
        'patient_responsibility_amount' => 'decimal:2',
        'patient_copay_amount' => 'decimal:2',
        'patient_deductible_amount' => 'decimal:2',
        'patient_coinsurance_amount' => 'decimal:2',
        'patient_payment_received' => 'decimal:2',
        'discount_applied' => 'decimal:2',
        'contractual_adjustment' => 'decimal:2',
        'charity_care_adjustment' => 'decimal:2',
        'bad_debt_adjustment' => 'decimal:2',
        'tax_details' => 'array',
        'total_tax_amount' => 'decimal:2',
        'billed_at' => 'datetime',
        'payment_due_date' => 'datetime',
        'last_statement_sent_at' => 'datetime',
        'sent_to_collections_at' => 'datetime',
        'is_disputed' => 'boolean',
        'dispute_opened_at' => 'datetime',
        'dispute_resolved_at' => 'datetime',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'period_start',
        'period_end',
        'insurance_claim_submitted_at',
        'insurance_payment_received_at',
        'billed_at',
        'payment_due_date',
        'last_statement_sent_at',
        'sent_to_collections_at',
        'dispute_opened_at',
        'dispute_resolved_at',
        'deleted_at',
    ];

    /**
     * Get the route key for the model.
     *
     * @return integer
     */
    public function getRouteKeyName():int
    {
        return 'id';
    }

    /**
     * Relationship with Visit model
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function visit(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }
    public function financialAdjustments()
    {
        return $this->hasMany(FinancialAdjustment::class);
    }

    public function getEffectiveNetAmountAttribute()
    {
        $totalAdjustments = $this->financialAdjustments()
            ->where('status', 'completed')
            ->sum('adjustment_amount');
        
        return max(0, $this->net_amount - $totalAdjustments);
    }

    /**
     * Billing cycle having many invoice line items.
     */
       public function lineItems()
    {
        
        return $this->hasMany(InvoiceLineItem::class, 'billing_cycle_id');
    }

    /**
     * Relationship with Patient model
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function patient(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Relationship with Facility model
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function facility(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * Relationship with Staff who created the billing cycle
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function createdBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by_staff_id');
    }

    /**
     * Relationship with Staff who updated the billing cycle
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function updatedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Staff::class, 'updated_by_staff_id');
    }

    /**
     * Scope to filter by billing status
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string|array $status
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByStatus($query, $status): \Illuminate\Database\Eloquent\Builder
    {
        if (is_array($status)) {
            return $query->whereIn('billing_status', $status);
        }
        
        return $query->where('billing_status', $status);
    }

    /**
     * Scope to filter by cycle type
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string|array $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByCycleType($query, $type): \Illuminate\Database\Eloquent\Builder
    {
        if (is_array($type)) {
            return $query->whereIn('cycle_type', $type);
        }
        
        return $query->where('cycle_type', $type);
    }

    /**
     * Scope to filter by facility
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $facilityId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByFacility($query, int $facilityId): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('facility_id', $facilityId);
    }

    /**
     * Scope to filter by patient
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $patientId
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function scopeByPatient($query, int $patientId): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('patient_id', $patientId);
    }

    /**
     * Scope to filter by visit
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $visitId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByVisit($query, int $visitId): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('visit_id', $visitId);
    }

    /**
     * Scope to get overdue billing cycles
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOverdue($query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('payment_due_date', '<', now())
            ->whereNotIn('billing_status', ['paid_in_full', 'written_off', 'charity_care']);
    }

    /**
     * Scope to get disputed billing cycles
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDisputed($query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_disputed', true)
            ->whereNull('dispute_resolved_at');
    }

    /**
     * Calculate and update the net amount
     *
     * @return $this
     */
    public function calculateNetAmount(): self
    {
        $this->net_amount = max(0, $this->total_amount_charged - $this->total_adjustments);
        return $this;
    }

    /**
     * Check if billing cycle is fully paid
     *
     * @return bool
     */
    public function isFullyPaid(): bool
    {
        $totalPaid = $this->insurance_payment_received + $this->patient_payment_received;
        return $totalPaid >= $this->net_amount;
    }

    /**
     * Check if billing cycle is overdue
     *
     * @return bool
     */
    public function isOverdue(): bool
    {
        if (!$this->payment_due_date) {
            return false;
        }
        
        return $this->payment_due_date < now() && !$this->isFullyPaid();
    }
}