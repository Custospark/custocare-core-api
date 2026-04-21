<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PrescriptionItem Model
 * 
 * @property int $id
 * @property int $prescription_id
 * @property string $medication_name
 * @property string|null $brand_name
 * @property string|null $strength
 * @property string $dosage_form
 * @property float $dosage_quantity
 * @property string $dosage_unit
 * @property string $frequency
 * @property int $duration_value
 * @property string $duration_unit
 * @property float $total_quantity
 * @property string $route
 * @property string|null $instructions
 * @property bool $as_needed
 * @property string|null $as_needed_reason
 * @property string $administration_instructions
 * @property string $refills
 * @property string|null $refill_instructions
 * @property string|null $medication_type
 * @property string|null $monitoring_required
 * @property string|null $common_side_effects
 * @property string|null $clinical_reasoning
 * @property string|null $substitution_instructions
 * @property string $substitution
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class PrescriptionItem extends Model
{
    use SoftDeletes;

    protected $table = 'prescription_items';

    protected $fillable = [
        'prescription_id',
        'medication_name',
        'brand_name',
        'strength',
        'dosage_form',
        'dosage_quantity',
        'dosage_unit',
        'frequency',
        'duration_value',
        'duration_unit',
        'route',
        'instructions',
        'as_needed',
        'as_needed_reason',
        'administration_instructions',
        'refills',
        'refill_instructions',
        'medication_type',
        'monitoring_required',
        'common_side_effects',
        'clinical_reasoning',
        'substitution_instructions',
        'substitution',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'dosage_quantity' => 'float',
        'total_quantity' => 'float',
        'duration_value' => 'integer',
        'as_needed' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ─── Relationships ────────────────────────────────────────────────

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class, 'prescription_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ─── Accessors ────────────────────────────────────────────────────

    public function getFullNameAttribute(): string
    {
        $name = $this->medication_name;
        
        if ($this->strength) {
            $name .= " {$this->strength}";
        }
        
        if ($this->brand_name) {
            $name .= " ({$this->brand_name})";
        }
        
        return $name;
    }

    public function getDurationTextAttribute(): string
    {
        return "{$this->duration_value} {$this->duration_unit}";
    }

    public function getDosageTextAttribute(): string
    {
        return "{$this->dosage_quantity} {$this->dosage_unit}";
    }

    // ─── Helper Methods ───────────────────────────────────────────────

    public function getPatientInstructions(): string
    {
        $instructions = [];
        
        // Dosage instruction
        $instructions[] = "Take {$this->dosage_quantity} {$this->dosage_unit}";
        
        // Route
        $instructions[] = strtolower($this->route);
        
        // Frequency
        $instructions[] = strtolower($this->frequency);
        
        // Special instructions
        if ($this->administration_instructions !== 'No special instructions') {
            $instructions[] = strtolower($this->administration_instructions);
        }
        
        // Duration
        $instructions[] = "for {$this->duration_value} {$this->duration_unit}";
        
        // As needed
        if ($this->as_needed && $this->as_needed_reason) {
            $instructions[] = "Take only when {$this->as_needed_reason}";
        }
        
        // Custom instructions
        if ($this->instructions) {
            $instructions[] = $this->instructions;
        }
        
        return ucfirst(implode('. ', $instructions)) . '.';
    }

    public function getRefillInstructionsText(): string
    {
        if ($this->refills === '0 refills - One time only') {
            return 'No refills authorized.';
        }
        
        if ($this->refill_instructions) {
            return $this->refill_instructions;
        }
        
        return "{$this->refills} authorized. Contact pharmacy for refill.";
    }
}