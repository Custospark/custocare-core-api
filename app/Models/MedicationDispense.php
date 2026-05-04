<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;

class MedicationDispense extends Model
{
    use HasFactory, HasUuids;

    /**
     * Underlying table (migrations may not be applied on all environments; use Schema::hasTable before querying).
     */
    protected $table = 'medication_dispenses';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'dispense_uuid',
        'facility_id',
        'visit_id',
        'prescription_id',
        'patient_id',
        'prescription_details_snapshot',
        'dispensed_inventory_ledger_id',
        'quantity_dispensed',
        'quantity_unit',
        'lot_number',
        'expiry_date',
        'dispensed_by_staff_id',
        'dispensed_at',
        'checked_by_staff_id',
        'checked_at',
        'pharmacist_notes',
        'patient_counseling_provided',
        'medication_guide_provided',
        'patient_education_topics',
        'patient_questions_addressed',
        'dispensed_instructions',
        'followup_instructions',
        'warning_labels_applied',
        'safety_checks_performed',
        'all_safety_checks_passed',
        'safety_check_overrides',
        'override_justification',
        'delivery_method',
        'picked_up_at',
        'picked_up_by_name',
        'pickup_id_verified',
        'copay_collected',
        'total_cost_to_patient',
        'insurance_payment',
        'status',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'prescription_details_snapshot' => 'array',
        'quantity_dispensed' => 'decimal:2',
        'expiry_date' => 'date',
        'dispensed_at' => 'datetime',
        'checked_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'patient_counseling_provided' => 'boolean',
        'medication_guide_provided' => 'boolean',
        'warning_labels_applied' => 'array',
        'safety_checks_performed' => 'array',
        'all_safety_checks_passed' => 'boolean',
        'safety_check_overrides' => 'array',
        'copay_collected' => 'decimal:2',
        'total_cost_to_patient' => 'decimal:2',
        'insurance_payment' => 'decimal:2',
        'metadata' => 'array',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        // No hidden attributes for now
    ];

    /**
     * Get the route key for the model.
     *
     * @return string
     */
    public function getRouteKeyName()
    {
        return 'dispense_uuid';
    }

    /**
     * Relationship with prescription.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function prescription()
    {
        return $this->belongsTo(Prescription::class);
    }

    /**
     * Relationship with patient.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Relationship with facility.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function facility()
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * Relationship with dispensing staff.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function dispensingStaff()
    {
        return $this->belongsTo(Staff::class, 'dispensed_by_staff_id');
    }

    /**
     * Relationship with checking pharmacist.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function checkingPharmacist()
    {
        return $this->belongsTo(Staff::class, 'checked_by_staff_id');
    }

    /**
     * Relationship with inventory ledger entry.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function inventoryLedger()
    {
        return $this->belongsTo(InventoryLedger::class, 'dispensed_inventory_ledger_id');
    }

    /**
     * Relationship with visit if applicable.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function visit()
    {
        return $this->belongsTo(Visit::class);
    }

    /**
     * Scope for dispensed status.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDispensed($query)
    {
        return $query->where('status', 'dispensed');
    }

    /**
     * Scope for not picked up status.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeNotPickedUp($query)
    {
        return $query->where('status', 'not_picked_up');
    }

    /**
     * Scope for a specific facility.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $facilityId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForFacility($query, $facilityId)
    {
        return $query->where('facility_id', $facilityId);
    }

    /**
     * Scope for a specific patient.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $patientId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForPatient($query, $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    /**
     * Check if dispense has been verified (4-eyes principle).
     *
     * @return bool
     */
    public function isVerified(): bool
    {
        return !is_null($this->checked_by_staff_id) && !is_null($this->checked_at);
    }

    /**
     * Check if patient counseling was provided.
     *
     * @return bool
     */
    public function wasCounselingProvided(): bool
    {
        return (bool) $this->patient_counseling_provided;
    }

    /**
     * Check if all safety checks passed.
     *
     * @return bool
     */
    public function passedAllSafetyChecks(): bool
    {
        return (bool) $this->all_safety_checks_passed;
    }

    /**
     * Get the safety checks that were performed.
     *
     * @return array
     */
    public function getSafetyChecksPerformed(): array
    {
        return $this->safety_checks_performed ?? [];
    }

    /**
     * Check if this dispense has been picked up.
     *
     * @return bool
     */
    public function isPickedUp(): bool
    {
        return !is_null($this->picked_up_at);
    }
}