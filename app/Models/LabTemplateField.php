<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LabTemplateField extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'lab_template_fields';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'field_uuid',
        'template_id',
        'name',
        'code',
        'data_type',
        'unit',
        'reference_min',
        'reference_max',
        'display_order',
        'is_required',
        'is_active',
        'is_critical',
        'clinical_notes',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'field_uuid' => 'string',
        'reference_min' => 'decimal:4',
        'reference_max' => 'decimal:4',
        'display_order' => 'integer',
        'is_required' => 'boolean',
        'is_active' => 'boolean',
        'is_critical' => 'boolean',
        'metadata' => 'array',
        'deleted_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'deleted_at',
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->field_uuid)) {
                $model->field_uuid = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    /**
     * Get the validation rules for the model.
     *
     * @return array<string, string>
     */
    public static function rules(): array
    {
        return [
            'field_uuid' => 'nullable|uuid|unique:lab_template_fields,field_uuid',
            'template_id' => 'required|exists:lab_templates,id',
            'name' => 'required|string|max:150',
            'code' => 'nullable|string|max:50',
            'data_type' => 'required|in:number,text,boolean,select',
            'unit' => 'nullable|string|max:50',
            'reference_min' => 'nullable|numeric',
            'reference_max' => 'nullable|numeric',
            'display_order' => 'integer|min:0|max:65535',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'is_critical' => 'boolean',
            'clinical_notes' => 'nullable|string',
            'metadata' => 'nullable|array',
        ];
    }

    /**
     * Get the template that this field belongs to.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(LabTemplate::class, 'template_id');
    }

    /**
     * Get the results for this field.
     */
    public function results(): HasMany
    {
        return $this->hasMany(LabResult::class, 'template_field_id');
    }

    /**
     * Scope a query to only include active fields.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include required fields.
     */
    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    /**
     * Scope a query to only include critical fields.
     */
    public function scopeCritical($query)
    {
        return $query->where('is_critical', true);
    }

    /**
     * Scope a query to only include fields of specific data type.
     */
    public function scopeOfDataType($query, string $dataType)
    {
        return $query->where('data_type', $dataType);
    }

    /**
     * Scope a query to order by display order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order', 'asc');
    }

    /**
     * Check if field is active.
     */
    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    /**
     * Check if field is required.
     */
    public function isRequired(): bool
    {
        return $this->is_required === true;
    }

    /**
     * Check if field is critical.
     */
    public function isCritical(): bool
    {
        return $this->is_critical === true;
    }

    /**
     * Check if field is a number type.
     */
    public function isNumberType(): bool
    {
        return $this->data_type === 'number';
    }

    /**
     * Check if field is a text type.
     */
    public function isTextType(): bool
    {
        return $this->data_type === 'text';
    }

    /**
     * Check if field is a boolean type.
     */
    public function isBooleanType(): bool
    {
        return $this->data_type === 'boolean';
    }

    /**
     * Check if field is a select type.
     */
    public function isSelectType(): bool
    {
        return $this->data_type === 'select';
    }

    /**
     * Activate the field.
     */
    public function activate(): bool
    {
        $this->is_active = true;
        return $this->save();
    }

    /**
     * Deactivate the field.
     */
    public function deactivate(): bool
    {
        $this->is_active = false;
        return $this->save();
    }

    /**
     * Get reference range as formatted string.
     */
    public function getFormattedReferenceRangeAttribute(): ?string
    {
        if ($this->reference_min !== null && $this->reference_max !== null) {
            return $this->reference_min . ' - ' . $this->reference_max . ' ' . ($this->unit ?? '');
        }
        
        if ($this->reference_min !== null) {
            return '≥ ' . $this->reference_min . ' ' . ($this->unit ?? '');
        }
        
        if ($this->reference_max !== null) {
            return '≤ ' . $this->reference_max . ' ' . ($this->unit ?? '');
        }
        
        return null;
    }

    /**
     * Validate if a value is within reference range.
     */
    public function isValueInReferenceRange($value): ?bool
    {
        // Only works for numeric values
        if (!$this->isNumberType() || !is_numeric($value)) {
            return null;
        }
        
        $numericValue = (float) $value;
        
        $minCheck = ($this->reference_min === null) || ($numericValue >= $this->reference_min);
        $maxCheck = ($this->reference_max === null) || ($numericValue <= $this->reference_max);
        
        return $minCheck && $maxCheck;
    }

    /**
     * Determine flag based on value.
     */
    public function determineFlag($value): string
    {
        // For non-numeric fields, return normal by default
        if (!$this->isNumberType() || !is_numeric($value)) {
            return 'normal';
        }
        
        $numericValue = (float) $value;
        
        // Check for critical values first
        if ($this->is_critical) {
            // Critical threshold logic - can be customized based on clinical rules
            if ($this->reference_min !== null && $numericValue < ($this->reference_min * 0.5)) {
                return 'critical';
            }
            if ($this->reference_max !== null && $numericValue > ($this->reference_max * 1.5)) {
                return 'critical';
            }
        }
        
        // Check for abnormal values
        if ($this->reference_min !== null && $numericValue < $this->reference_min) {
            return 'low';
        }
        
        if ($this->reference_max !== null && $numericValue > $this->reference_max) {
            return 'high';
        }
        
        return 'normal';
    }
}