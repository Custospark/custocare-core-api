<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Model
{
    use HasFactory, SoftDeletes, HasUuids;

    /**
     * The primary key for the model.
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
        'facility_id',
        'department_uuid',
        'department_code',
        'department_name',
        'department_type',
        'parent_department_id',
        'department_head_staff_id',
        'bed_count',
        'treatment_room_count',
        'max_concurrent_capacity',
        'building',
        'floor',
        'wing_section',
        'operating_hours',
        'accepts_walk_ins',
        'requires_appointment',
        'average_wait_time_minutes',
        'status',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'department_uuid' => 'string',
        'bed_count' => 'integer',
        'treatment_room_count' => 'integer',
        'max_concurrent_capacity' => 'integer',
        'accepts_walk_ins' => 'boolean',
        'requires_appointment' => 'boolean',
        'average_wait_time_minutes' => 'integer',
        'operating_hours' => 'array',
        'metadata' => 'array',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the route key for the model.
     *
     * @return string
     */
    public function getRouteKeyName(): string
    {
        return 'department_uuid';
    }

    /**
     * Relationships
     */

    /**
     * Get the facility that owns the department.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function facility()
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * Get the parent department.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function parentDepartment()
    {
        return $this->belongsTo(Department::class, 'parent_department_id');
    }

    /**
     * Get the child departments.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function childDepartments()
    {
        return $this->hasMany(Department::class, 'parent_department_id');
    }

    /**
     * Get the department head staff member.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function departmentHead()
    {
        return $this->belongsTo(Staff::class, 'department_head_staff_id');
    }

    /**
     * Scope methods
     */

    /**
     * Scope a query to only include active departments.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include departments of a specific type.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('department_type', $type);
    }

    /**
     * Scope a query to only include departments in a specific facility.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $facilityId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeInFacility($query, int $facilityId)
    {
        return $query->where('facility_id', $facilityId);
    }

    /**
     * Helper methods
     */

    /**
     * Check if department has available capacity.
     *
     * @return bool
     */
    public function hasAvailableCapacity(): bool
    {
        // This would typically check current occupancy vs max_concurrent_capacity
        // For now, return true if max capacity is not reached (placeholder logic)
        return true;
    }

    /**
     * Get operating hours as a formatted string.
     *
     * @return string|null
     */
    public function getFormattedOperatingHours(): ?string
    {
        if (empty($this->operating_hours)) {
            return null;
        }

        // Simple formatting - in reality you'd have more complex logic
        return json_encode($this->operating_hours, JSON_PRETTY_PRINT);
    }
}