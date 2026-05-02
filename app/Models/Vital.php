<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vital extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'vitals';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'facility_id',
        'visit_id',
        'patient_id',
        'staff_id',
        // Core Vital Signs
        'temperature',
        'temperature_unit',
        'heart_rate',
        'respiratory_rate',
        'systolic_bp',
        'diastolic_bp',
        'bp_position',
        'bp_location',
        // Advanced Vitals
        'oxygen_saturation',
        'oxygen_flow_rate',
        'oxygen_delivery_device',
        'height',
        'height_unit',
        'weight',
        'weight_unit',
        'bmi',
        'pain_score',
        'pain_scale_type',
        'pain_location',
        // Pediatric Vitals
        'head_circumference',
        'length',
        // Measurement Context
        'measured_at',
        'measurement_method',
        'device_id',
        'consciousness_level',
        'general_appearance',
        // Custom Fields
        'custom_fields',
        'percentiles',
        // Flagging & Alerts
        'flag_status',
        'clinical_alert',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'temperature' => 'float',
        'heart_rate' => 'float',
        'respiratory_rate' => 'float',
        'systolic_bp' => 'float',
        'diastolic_bp' => 'float',
        'oxygen_saturation' => 'float',
        'oxygen_flow_rate' => 'integer',
        'height' => 'float',
        'weight' => 'float',
        'bmi' => 'float',
        'pain_score' => 'float',
        'head_circumference' => 'float',
        'length' => 'float',
        'measured_at' => 'datetime',
        'custom_fields' => 'array',
        'percentiles' => 'array',
        'flag_status' => 'array',
    ];

    /**
     * The attributes that should have default values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'temperature_unit' => 'celsius',
        'height_unit' => 'cm',
        'weight_unit' => 'kg',
        'pain_scale_type' => 'numeric',
    ];

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Get the facility that owns the vital record.
     *
     * @return BelongsTo
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'facility_id');
    }

    /**
     * Get the visit associated with this vital record.
     *
     * @return BelongsTo
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class, 'visit_id');
    }

    /**
     * Get the patient who owns this vital record.
     *
     * @return BelongsTo
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    /**
     * Get the staff member who recorded these vitals.
     *
     * @return BelongsTo
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * Scope a query to only include vitals for a specific patient.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $patientId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForPatient($query, int $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    /**
     * Scope a query to only include vitals for a specific visit.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $visitId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForVisit($query, int $visitId)
    {
        return $query->where('visit_id', $visitId);
    }

    /**
     * Scope a query to only include vitals for a specific facility.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $facilityId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForFacility($query, int $facilityId)
    {
        return $query->where('facility_id', $facilityId);
    }

    /**
     * Scope a query to only include vitals with abnormal flags.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAbnormal($query)
    {
        return $query->whereJsonContains('flag_status->warning', true)
            ->orWhereNotNull('clinical_alert');
    }

    /**
     * Scope a query to only include vitals with critical alerts.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCritical($query)
    {
        return $query->whereNotNull('clinical_alert');
    }

    /**
     * Scope a query for vitals measured after a specific date.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $date
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeMeasuredAfter($query, string $date)
    {
        return $query->where('measured_at', '>=', $date);
    }

    /**
     * Scope a query for vitals measured before a specific date.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $date
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeMeasuredBefore($query, string $date)
    {
        return $query->where('measured_at', '<=', $date);
    }

    /**
     * Scope a query for vitals within a date range.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $startDate
     * @param string $endDate
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeMeasuredBetween($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('measured_at', [$startDate, $endDate]);
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Calculate BMI from height and weight.
     *
     * @return float|null
     */
    public function calculateBmi(): ?float
    {
        if (!$this->height || !$this->weight) {
            return null;
        }

        $heightInMeters = $this->height_unit === 'cm' ? $this->height / 100 : $this->height * 0.0254;
        $weightInKg = $this->weight_unit === 'kg' ? $this->weight : $this->weight * 0.453592;

        if ($heightInMeters <= 0) {
            return null;
        }

        return round($weightInKg / ($heightInMeters * $heightInMeters), 2);
    }

    /**
     * Update BMI automatically.
     *
     * @return void
     */
    public function updateBmi(): void
    {
        $bmi = $this->calculateBmi();
        if ($bmi !== null) {
            $this->bmi = $bmi;
        }
    }

    /**
     * Get the BMI category.
     *
     * @return string|null
     */
    public function getBmiCategoryAttribute(): ?string
    {
        if ($this->bmi === null) {
            return null;
        }

        if ($this->bmi < 18.5) {
            return 'Underweight';
        }
        if ($this->bmi < 25) {
            return 'Normal weight';
        }
        if ($this->bmi < 30) {
            return 'Overweight';
        }
        if ($this->bmi < 35) {
            return 'Obese Class I';
        }
        if ($this->bmi < 40) {
            return 'Obese Class II';
        }
        return 'Obese Class III';
    }

    /**
     * Get the mean arterial pressure (MAP).
     *
     * @return float|null
     */
    public function getMapAttribute(): ?float
    {
        if (!$this->systolic_bp || !$this->diastolic_bp) {
            return null;
        }

        return round($this->diastolic_bp + (($this->systolic_bp - $this->diastolic_bp) / 3), 2);
    }

    /**
     * Get the pulse pressure.
     *
     * @return float|null
     */
    public function getPulsePressureAttribute(): ?float
    {
        if (!$this->systolic_bp || !$this->diastolic_bp) {
            return null;
        }

        return round($this->systolic_bp - $this->diastolic_bp, 2);
    }

    /**
     * Check if blood pressure is hypertensive.
     *
     * @return bool
     */
    public function isHypertensive(): bool
    {
        if (!$this->systolic_bp || !$this->diastolic_bp) {
            return false;
        }

        return $this->systolic_bp >= 140 || $this->diastolic_bp >= 90;
    }

    /**
     * Check if patient has fever.
     *
     * @return bool
     */
    public function hasFever(): bool
    {
        if (!$this->temperature) {
            return false;
        }

        $tempInCelsius = $this->temperature_unit === 'celsius' 
            ? $this->temperature 
            : ($this->temperature - 32) * 5 / 9;

        return $tempInCelsius >= 38.0;
    }

    /**
     * Check if patient is hypothermic.
     *
     * @return bool
     */
    public function isHypothermic(): bool
    {
        if (!$this->temperature) {
            return false;
        }

        $tempInCelsius = $this->temperature_unit === 'celsius' 
            ? $this->temperature 
            : ($this->temperature - 32) * 5 / 9;

        return $tempInCelsius < 35.0;
    }

    /**
     * Check if oxygen saturation is low.
     *
     * @return bool
     */
    public function isHypoxic(): bool
    {
        if (!$this->oxygen_saturation) {
            return false;
        }

        return $this->oxygen_saturation < 90;
    }

    /**
     * Check if tachycardia (fast heart rate).
     *
     * @return bool
     */
    public function isTachycardic(): bool
    {
        if (!$this->heart_rate) {
            return false;
        }

        return $this->heart_rate > 100;
    }

    /**
     * Check if bradycardia (slow heart rate).
     *
     * @return bool
     */
    public function isBradycardic(): bool
    {
        if (!$this->heart_rate) {
            return false;
        }

        return $this->heart_rate < 60;
    }

    /**
     * Check if tachypnea (fast breathing).
     *
     * @return bool
     */
    public function isTachypneic(): bool
    {
        if (!$this->respiratory_rate) {
            return false;
        }

        return $this->respiratory_rate > 20;
    }

    /**
     * Generate clinical alerts based on abnormal values.
     *
     * @return string|null
     */
    public function generateClinicalAlert(): ?string
    {
        $alerts = [];

        if ($this->hasFever()) {
            $alerts[] = 'Fever detected';
        }
        if ($this->isHypothermic()) {
            $alerts[] = 'Hypothermia detected';
        }
        if ($this->isHypoxic()) {
            $alerts[] = 'Low oxygen saturation';
        }
        if ($this->isTachycardic()) {
            $alerts[] = 'Tachycardia';
        }
        if ($this->isBradycardic()) {
            $alerts[] = 'Bradycardia';
        }
        if ($this->isTachypneic()) {
            $alerts[] = 'Tachypnea';
        }
        if ($this->isHypertensive()) {
            $alerts[] = 'Hypertension';
        }
        if ($this->systolic_bp < 90) {
            $alerts[] = 'Hypotension';
        }
        if ($this->pain_score && $this->pain_score >= 7) {
            $alerts[] = 'Severe pain reported';
        }

        return empty($alerts) ? null : implode('; ', $alerts);
    }

    /**
     * Update flag status based on vital signs.
     *
     * @return array
     */
    public function updateFlagStatus(): array
    {
        $flags = [];

        // Temperature flags
        if ($this->temperature) {
            $tempInCelsius = $this->temperature_unit === 'celsius' 
                ? $this->temperature 
                : ($this->temperature - 32) * 5 / 9;
            
            if ($tempInCelsius >= 39.0) {
                $flags['temperature'] = 'critical_high';
                $flags['warning'] = true;
            } elseif ($tempInCelsius >= 38.0) {
                $flags['temperature'] = 'high';
            } elseif ($tempInCelsius <= 35.0) {
                $flags['temperature'] = 'critical_low';
                $flags['warning'] = true;
            } elseif ($tempInCelsius <= 36.0) {
                $flags['temperature'] = 'low';
            } else {
                $flags['temperature'] = 'normal';
            }
        }

        // Blood pressure flags
        if ($this->systolic_bp && $this->diastolic_bp) {
            if ($this->systolic_bp >= 180 || $this->diastolic_bp >= 120) {
                $flags['bp'] = 'hypertensive_crisis';
                $flags['warning'] = true;
            } elseif ($this->systolic_bp >= 140 || $this->diastolic_bp >= 90) {
                $flags['bp'] = 'hypertensive';
            } elseif ($this->systolic_bp < 90) {
                $flags['bp'] = 'hypotensive';
                $flags['warning'] = true;
            } else {
                $flags['bp'] = 'normal';
            }
        }

        // Heart rate flags
        if ($this->heart_rate) {
            if ($this->heart_rate > 120) {
                $flags['heart_rate'] = 'critical_high';
                $flags['warning'] = true;
            } elseif ($this->heart_rate > 100) {
                $flags['heart_rate'] = 'high';
            } elseif ($this->heart_rate < 50) {
                $flags['heart_rate'] = 'critical_low';
                $flags['warning'] = true;
            } elseif ($this->heart_rate < 60) {
                $flags['heart_rate'] = 'low';
            } else {
                $flags['heart_rate'] = 'normal';
            }
        }

        // Oxygen saturation flags
        if ($this->oxygen_saturation) {
            if ($this->oxygen_saturation < 85) {
                $flags['oxygen_saturation'] = 'critical';
                $flags['warning'] = true;
            } elseif ($this->oxygen_saturation < 90) {
                $flags['oxygen_saturation'] = 'low';
                $flags['warning'] = true;
            } elseif ($this->oxygen_saturation < 94) {
                $flags['oxygen_saturation'] = 'borderline';
            } else {
                $flags['oxygen_saturation'] = 'normal';
            }
        }

        // Respiratory rate flags
        if ($this->respiratory_rate) {
            if ($this->respiratory_rate > 30) {
                $flags['respiratory_rate'] = 'critical_high';
                $flags['warning'] = true;
            } elseif ($this->respiratory_rate > 20) {
                $flags['respiratory_rate'] = 'high';
            } elseif ($this->respiratory_rate < 10) {
                $flags['respiratory_rate'] = 'critical_low';
                $flags['warning'] = true;
            } elseif ($this->respiratory_rate < 12) {
                $flags['respiratory_rate'] = 'low';
            } else {
                $flags['respiratory_rate'] = 'normal';
            }
        }

        // Pain score flags
        if ($this->pain_score) {
            if ($this->pain_score >= 8) {
                $flags['pain_score'] = 'severe';
                $flags['warning'] = true;
            } elseif ($this->pain_score >= 4) {
                $flags['pain_score'] = 'moderate';
            } else {
                $flags['pain_score'] = 'mild';
            }
        }

        $this->flag_status = $flags;
        return $flags;
    }

    /**
     * Get a formatted string of vital signs.
     *
     * @return string
     */
    public function getFormattedVitalsAttribute(): string
    {
        $parts = [];

        if ($this->temperature) {
            $unit = $this->temperature_unit === 'celsius' ? '°C' : '°F';
            $parts[] = "Temp: {$this->temperature}{$unit}";
        }

        if ($this->heart_rate) {
            $parts[] = "HR: {$this->heart_rate} bpm";
        }

        if ($this->respiratory_rate) {
            $parts[] = "RR: {$this->respiratory_rate}/min";
        }

        if ($this->systolic_bp && $this->diastolic_bp) {
            $parts[] = "BP: {$this->systolic_bp}/{$this->diastolic_bp} mmHg";
        }

        if ($this->oxygen_saturation) {
            $parts[] = "SpO2: {$this->oxygen_saturation}%";
        }

        if ($this->pain_score) {
            $parts[] = "Pain: {$this->pain_score}/10";
        }

        return implode(' | ', $parts);
    }
}