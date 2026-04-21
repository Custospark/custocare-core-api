<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Prescription Model
 * 
 * @property int $id
 * @property int $facility_id
 * @property int $patient_id
 * @property int|null $visit_id
 * @property int|null $clinical_template_id
 * @property string $prescription_number
 * @property string $prescription_date
 * @property string|null $valid_until
 * @property string $status
 * @property string $prescription_type
 * @property string $priority
 * @property string|null $diagnosis
 * @property string|null $clinical_notes
 * @property string|null $special_instructions
 * @property string|null $allergy_check
 * @property string|null $allergy_notes
 * @property int $prescribed_by
 * @property string $prescriber_type
 * @property string|null $prescriber_license
 * @property string|null $prescriber_contact
 * @property string $prescription_format
 * @property string|null $dispensed_at
 * @property string|null $dispensed_by_name
 * @property string|null $dispensed_pharmacy
 * @property string $dispensing_location
 * @property string|null $cancelled_at
 * @property int|null $cancelled_by
 * @property string|null $cancellation_reason
 * @property string|null $cancellation_notes
 * @property string|null $patient_education_notes
 * @property string|null $follow_up_instructions
 * @property string|null $follow_up_date
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class Prescription extends Model
{
    use SoftDeletes;

    protected $table = 'prescriptions';

    protected $fillable = [
        'facility_id',
        'patient_id',
        'visit_id',
        'clinical_template_id',
        'prescription_number',
        'prescription_date',
        'valid_until',
        'status',
        'prescription_type',
        'priority',
        'diagnosis',
        'clinical_notes',
        'special_instructions',
        'allergy_check',
        'allergy_notes',
        'prescribed_by',
        'prescriber_type',
        'prescriber_license',
        'prescriber_contact',
        'prescription_format',
        'dispensed_at',
        'dispensed_by_name',
        'dispensed_pharmacy',
        'dispensing_location',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'cancellation_notes',
        'patient_education_notes',
        'follow_up_instructions',
        'follow_up_date',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'prescription_date' => 'date',
        'valid_until' => 'date',
        'dispensed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'follow_up_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ─── Relationships ────────────────────────────────────────────────

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'facility_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class, 'visit_id');
    }

    public function clinicalTemplate(): BelongsTo
    {
        return $this->belongsTo(ClinicalTemplate::class, 'clinical_template_id');
    }

    public function prescribedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prescribed_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PrescriptionItem::class, 'prescription_id');
    }

    // ─── Accessors ────────────────────────────────────────────────────

    public function getTotalItemsCountAttribute(): int
    {
        return $this->items()->count();
    }

    public function getTotalQuantityAttribute(): float
    {
        return $this->items()->sum('total_quantity');
    }

    public function isActive(): bool
    {
        return $this->status === 'Active - Ready for Dispensing';
    }

    public function isExpired(): bool
    {
        if (!$this->valid_until) {
            return false;
        }
        
        return now()->gt($this->valid_until);
    }

    public function canBeDispensed(): bool
    {
        return $this->isActive() && !$this->isExpired();
    }

    // ─── Helper Methods ───────────────────────────────────────────────

    public function getBillingItems(): array
    {
        return $this->items->map(function ($item) {
            return [
                'prescription_item_id' => $item->id,
                'medication_name' => $item->medication_name,
                'brand_name' => $item->brand_name,
                'strength' => $item->strength,
                'dosage_form' => $item->dosage_form,
                'total_quantity' => $item->total_quantity,
                'dosage_quantity' => $item->dosage_quantity,
                'dosage_unit' => $item->dosage_unit,
                'frequency' => $item->frequency,
                'duration' => "{$item->duration_value} {$item->duration_unit}",
                'instructions' => $item->instructions,
                'route' => $item->route,
            ];
        })->toArray();
    }

    public function applyTemplate(ClinicalTemplate $template, int $userId): void
    {
        $this->diagnosis = $template->default_diagnosis;
        $this->clinical_notes = $template->default_notes;
        $this->patient_education_notes = $template->patient_instructions;
        $this->clinical_template_id = $template->id;
        $this->updated_by = $userId;
        
        $template->incrementUsage();
    }

    public function markAsDispensed(?string $pharmacyName = null, ?string $dispensedByName = null): void
    {
        $this->status = 'Fully Dispensed';
        $this->dispensed_at = now();
        $this->dispensed_pharmacy = $pharmacyName;
        $this->dispensed_by_name = $dispensedByName;
        $this->dispensing_location = $pharmacyName ? 'Dispensed at External Pharmacy' : 'Dispensed at Our Facility';
        $this->save();
    }

    public function cancel(string $reason, int $cancelledByUserId, ?string $notes = null): void
    {
        $this->status = 'Cancelled - No Longer Valid';
        $this->cancelled_at = now();
        $this->cancelled_by = $cancelledByUserId;
        $this->cancellation_reason = $reason;
        $this->cancellation_notes = $notes;
        $this->save();
    }

    // ─── Scopes ───────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'Active - Ready for Dispensing');
    }

    public function scopeByPatient($query, int $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    public function scopeByFacility($query, int $facilityId)
    {
        return $query->where('facility_id', $facilityId);
    }

    public function scopeByDateRange($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('prescription_date', [$startDate, $endDate]);
    }

    public function scopeReadyForBilling($query)
    {
        return $query->whereIn('status', ['Active - Ready for Dispensing', 'Partially Dispensed'])
                     ->where(function ($q) {
                         $q->whereNull('valid_until')
                           ->orWhere('valid_until', '>=', now());
                     });
    }

    public function scopeExpired($query)
    {
        return $query->whereNotNull('valid_until')
                     ->where('valid_until', '<', now())
                     ->where('status', '!=', 'Expired - Past Valid Date');
    }
}