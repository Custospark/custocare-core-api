<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Auth;

class Prescription extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'prescription_uuid',
        'facility_id',
        'visit_id',
        'patient_id',
        'prescribing_provider_staff_id',
        'prescriber_npi',
        'prescriber_dea_number_encrypted',
        'inventory_item_id',
        'medication_name',
        'generic_name',
        'ndc_code',
        'controlled_substance_schedule',
        'dosage_strength',
        'dosage_form',
        'route',
        'sig_instructions',
        'pharmacist_notes',
        'quantity_prescribed',
        'quantity_unit',
        'refills_allowed',
        'refills_remaining',
        'days_supply',
        'diagnosis_codes',
        'clinical_indication',
        'drug_allergy_check_results',
        'drug_interaction_check_results',
        'prescribed_at',
        'valid_from',
        'valid_to',
        'do_not_fill_before',
        'requires_prior_authorization',
        'prior_authorization_number',
        'prior_auth_status',
        'is_electronic_prescription',
        'erx_message_id',
        'transmitted_at',
        'transmit_to_pharmacy',
        'pharmacy_ncpdp_id',
        'dispense_status',
        'is_high_risk_medication',
        'safety_monitoring_required',
        'special_instructions',
        'status',
        'status_reason',
        'discontinued_at',
        'discontinued_by_staff_id',
        'created_by_staff_id',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'prescribed_at' => 'datetime',
        'valid_from' => 'date',
        'valid_to' => 'date',
        'do_not_fill_before' => 'date',
        'transmitted_at' => 'datetime',
        'discontinued_at' => 'datetime',
        'requires_prior_authorization' => 'boolean',
        'is_electronic_prescription' => 'boolean',
        'is_high_risk_medication' => 'boolean',
        'quantity_prescribed' => 'decimal:2',
        'refills_allowed' => 'integer',
        'refills_remaining' => 'integer',
        'days_supply' => 'integer',
        'diagnosis_codes' => 'array',
        'drug_allergy_check_results' => 'array',
        'drug_interaction_check_results' => 'array',
        'safety_monitoring_required' => 'array',
        'metadata' => 'array',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'prescriber_dea_number_encrypted',
        'deleted_at',
    ];

    /**
     * Get the route key for the model.
     *
     * @return string
     */
    public function getRouteKeyName(): string
    {
        return 'prescription_uuid';
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($prescription) {
            if (empty($prescription->prescription_uuid)) {
                $prescription->prescription_uuid = (string) \Illuminate\Support\Str::uuid();
            }
            
            if (empty($prescription->prescribed_at)) {
                $prescription->prescribed_at = now();
            }
            
            if (empty($prescription->created_by_staff_id) && auth::check()) {
                $prescription->created_by_staff_id = auth::id();
            }
        });
    }

    /**
     * Relationship with Patient
     *
     * @return BelongsTo
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Relationship with Visit
     *
     * @return BelongsTo
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /**
     * Relationship with prescribing provider (Staff)
     *
     * @return BelongsTo
     */
    public function prescribingProvider(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'prescribing_provider_staff_id');
    }

    /**
     * Relationship with staff who discontinued the prescription
     *
     * @return BelongsTo
     */
    public function discontinuedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'discontinued_by_staff_id');
    }

    /**
     * Relationship with inventory item
     *
     * @return BelongsTo
     */
    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    /**
     * Relationship with creator staff
     *
     * @return BelongsTo
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by_staff_id');
    }

    /**
     * Relationship with Facility
     *
     * @return BelongsTo
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * Accessor for decrypted DEA number (with proper authorization checks in service layer)
     *
     * @return Attribute
     */
    protected function prescriberDeaNumber(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => !empty($attributes['prescriber_dea_number_encrypted']) 
                ? Crypt::decryptString($attributes['prescriber_dea_number_encrypted']) 
                : null,
            set: fn ($value) => [
                'prescriber_dea_number_encrypted' => !empty($value) ? Crypt::encryptString($value) : null,
            ],
        );
    }

    /**
     * Check if prescription is active
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->status === 'active' && 
               $this->valid_from <= now() && 
               $this->valid_to >= now();
    }

    /**
     * Check if prescription is refillable
     *
     * @return bool
     */
    public function isRefillable(): bool
    {
        return $this->refills_remaining > 0 && 
               $this->isActive() && 
               $this->status !== 'discontinued' &&
               $this->status !== 'cancelled';
    }

    /**
     * Check if prescription is a controlled substance
     *
     * @return bool
     */
    public function isControlledSubstance(): bool
    {
        return in_array($this->controlled_substance_schedule, ['I', 'II', 'III', 'IV', 'V']);
    }

    /**
     * Scope for active prescriptions
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
                    ->where('valid_from', '<=', now())
                    ->where('valid_to', '>=', now());
    }

    /**
     * Scope for prescriptions needing transmission
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeNeedsTransmission($query)
    {
        return $query->where('is_electronic_prescription', true)
                    ->whereNull('transmitted_at')
                    ->where('status', 'active')
                    ->where('dispense_status', 'pending');
    }

    /**
     * Scope for high-risk medications
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeHighRisk($query)
    {
        return $query->where('is_high_risk_medication', true);
    }
}