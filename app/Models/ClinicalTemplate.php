<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ClinicalTemplate Model
 * 
 * @property int $id
 * @property int $facility_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string $category
 * @property string|null $default_diagnosis
 * @property string|null $default_notes
 * @property string|null $patient_instructions
 * @property array|null $default_medications
 * @property int $usage_count
 * @property bool $is_active
 * @property string $visibility
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class ClinicalTemplate extends Model
{
    use SoftDeletes;

    protected $table = 'clinical_templates';

    protected $fillable = [
        'facility_id',
        'name',
        'slug',
        'description',
        'category',
        'default_diagnosis',
        'default_notes',
        'patient_instructions',
        'default_medications',
        'usage_count',
        'is_active',
        'visibility',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'default_medications' => 'array',
        'is_active' => 'boolean',
        'usage_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ─── Relationships ────────────────────────────────────────────────

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'facility_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class, 'clinical_template_id');
    }

    // ─── Accessors & Mutators ────────────────────────────────────────

    public function getDefaultMedicationsAttribute($value): array
    {
        if (is_null($value)) {
            return [];
        }
        
        if (is_string($value)) {
            return json_decode($value, true) ?? [];
        }
        
        return $value ?? [];
    }

    // ─── Scopes ───────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByFacility($query, int $facilityId)
    {
        return $query->where('facility_id', $facilityId);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeByVisibility($query, string $visibility)
    {
        return $query->where('visibility', $visibility);
    }

    // ─── Helper Methods ───────────────────────────────────────────────

    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }

    public function getFormattedMedications(): array
    {
        $medications = $this->default_medications;
        
        foreach ($medications as &$med) {
            $med['total_quantity'] = $this->calculateTotalQuantity($med);
        }
        
        return $medications;
    }

    private function calculateTotalQuantity(array $medication): float
    {
        $frequencyMultiplier = $this->getFrequencyMultiplier($medication['frequency'] ?? 'Once daily');
        
        // Add fallback for duration_unit - default to 'Day(s)' if missing
        $durationUnit = $medication['duration_unit'] ?? $medication['durationUnit'] ?? 'Day(s)';
        $durationInDays = $this->convertToDays($medication['duration_value'] ?? 1, $durationUnit);
        
        return $frequencyMultiplier * $durationInDays * ($medication['dosage_quantity'] ?? 1);
    }

    private function getFrequencyMultiplier(string $frequency): float
    {
        $frequencyMap = [
            'Once daily' => 1,
            'Twice daily' => 2,
            'Three times daily' => 3,
            'Four times daily' => 4,
            'Every 2 hours' => 12,
            'Every 3 hours' => 8,
            'Every 4 hours' => 6,
            'Every 6 hours' => 4,
            'Every 8 hours' => 3,
            'Every 12 hours' => 2,
            'Every 24 hours' => 1,
            'At bedtime' => 1,
            'Before meals' => 3,
            'After meals' => 3,
            'Once weekly' => 1/7,
            'Twice weekly' => 2/7,
            'Once monthly' => 1/30,
            'Every other day' => 0.5,
        ];
        
        foreach ($frequencyMap as $key => $multiplier) {
            if (str_contains($frequency, $key)) {
                return $multiplier;
            }
        }
        
        return 1;
    }

    private function convertToDays(int $value, string $unit): float
    {
        return match($unit) {
            'Day(s)' => $value,
            'Week(s)' => $value * 7,
            'Month(s)' => $value * 30,
            'Year(s)' => $value * 365,
            default => $value,
        };
    }
}