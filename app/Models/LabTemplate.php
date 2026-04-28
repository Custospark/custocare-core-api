<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabTemplate extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'lab_templates';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'template_uuid',
        'name',
        'description',
        'facility_id',
        'is_shared',
        'structure_type',
        'is_active',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'template_uuid' => 'string',
        'is_shared' => 'boolean',
        'is_active' => 'boolean',
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
            if (empty($model->template_uuid)) {
                $model->template_uuid = (string) \Illuminate\Support\Str::uuid();
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
            'template_uuid' => 'nullable|uuid|unique:lab_templates,template_uuid',
            'name' => 'required|string|max:150',
            'description' => 'nullable|string',
            'facility_id' => 'nullable|exists:facilities,id',
            'is_shared' => 'boolean',
            'structure_type' => 'required|in:standard,simple,panel',
            'is_active' => 'boolean',
            'metadata' => 'nullable|array',
        ];
    }

    /**
     * Get the facility that owns this template.
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'facility_id');
    }

    /**
     * Get the tests that belong to this template.
     */
    public function tests(): HasMany
    {
        return $this->hasMany(LabTest::class, 'template_id');
    }

    /**
     * Get the fields that belong to this template.
     */
    public function fields(): HasMany
    {
        return $this->hasMany(LabTemplateField::class, 'template_id');
    }

    /**
     * Scope a query to only include active templates.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include templates by facility.
     */
    public function scopeByFacility($query, ?int $facilityId)
    {
        if ($facilityId) {
            return $query->where(function ($q) use ($facilityId) {
                $q->where('facility_id', $facilityId)
                  ->orWhere('is_shared', true);
            });
        }
        
        return $query->where('is_shared', true);
    }

    /**
     * Scope a query to only include templates of specific structure type.
     */
    public function scopeOfType($query, string $structureType)
    {
        return $query->where('structure_type', $structureType);
    }

    /**
     * Scope a query to only include shared templates.
     */
    public function scopeShared($query)
    {
        return $query->where('is_shared', true);
    }

    /**
     * Check if template is shared globally.
     */
    public function isShared(): bool
    {
        return $this->is_shared === true;
    }

    /**
     * Check if template is active.
     */
    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    /**
     * Check if template is a standard type.
     */
    public function isStandard(): bool
    {
        return $this->structure_type === 'standard';
    }

    /**
     * Check if template is a simple type.
     */
    public function isSimple(): bool
    {
        return $this->structure_type === 'simple';
    }

    /**
     * Check if template is a panel type.
     */
    public function isPanel(): bool
    {
        return $this->structure_type === 'panel';
    }

    /**
     * Activate the template.
     */
    public function activate(): bool
    {
        $this->is_active = true;
        return $this->save();
    }

    /**
     * Deactivate the template.
     */
    public function deactivate(): bool
    {
        $this->is_active = false;
        return $this->save();
    }
}