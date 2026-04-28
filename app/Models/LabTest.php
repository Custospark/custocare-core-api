<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LabTest extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'lab_tests';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'test_uuid',
        'name',
        'code',
        'template_id',
        'facility_id',
        'is_shared',
        'category',
        'description',
        'is_active',
        'requires_fasting',
        'turnaround_time_hours',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'test_uuid' => 'string',
        'is_shared' => 'boolean',
        'is_active' => 'boolean',
        'requires_fasting' => 'boolean',
        'turnaround_time_hours' => 'integer',
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
            if (empty($model->test_uuid)) {
                $model->test_uuid = (string) \Illuminate\Support\Str::uuid();
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
            'test_uuid' => 'nullable|uuid|unique:lab_tests,test_uuid',
            'name' => 'required|string|max:150',
            'code' => 'nullable|string|max:50',
            'template_id' => 'required|exists:lab_templates,id',
            'facility_id' => 'nullable|exists:facilities,id',
            'is_shared' => 'boolean',
            'category' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'requires_fasting' => 'boolean',
            'turnaround_time_hours' => 'nullable|integer|min:0|max:65535',
            'metadata' => 'nullable|array',
        ];
    }

    /**
     * Get the template that this test belongs to.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(LabTemplate::class, 'template_id');
    }

    /**
     * Get the facility that owns this test.
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'facility_id');
    }

    /**
     * Get the request items for this test.
     */
    public function requestItems(): HasMany
    {
        return $this->hasMany(LabRequestItem::class, 'lab_test_id');
    }

    /**
     * Scope a query to only include active tests.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include tests by facility.
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
     * Scope a query to only include tests of specific category.
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope a query to only include tests that require fasting.
     */
    public function scopeRequiresFasting($query)
    {
        return $query->where('requires_fasting', true);
    }

    /**
     * Scope a query to only include shared tests.
     */
    public function scopeShared($query)
    {
        return $query->where('is_shared', true);
    }

    /**
     * Check if test is shared globally.
     */
    public function isShared(): bool
    {
        return $this->is_shared === true;
    }

    /**
     * Check if test is active.
     */
    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    /**
     * Check if test requires fasting.
     */
    public function requiresFasting(): bool
    {
        return $this->requires_fasting === true;
    }

    /**
     * Activate the test.
     */
    public function activate(): bool
    {
        $this->is_active = true;
        return $this->save();
    }

    /**
     * Deactivate the test.
     */
    public function deactivate(): bool
    {
        $this->is_active = false;
        return $this->save();
    }

    /**
     * Get formatted turnaround time.
     */
    public function getFormattedTurnaroundTimeAttribute(): ?string
    {
        if (!$this->turnaround_time_hours) {
            return null;
        }

        if ($this->turnaround_time_hours < 24) {
            return $this->turnaround_time_hours . ' hour(s)';
        }

        $days = floor($this->turnaround_time_hours / 24);
        $hours = $this->turnaround_time_hours % 24;
        
        if ($hours > 0) {
            return $days . ' day(s) and ' . $hours . ' hour(s)';
        }
        
        return $days . ' day(s)';
    }
}