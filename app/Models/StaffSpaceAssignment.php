<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class StaffSpaceAssignment extends Model
{
    protected $fillable = [
        'staff_id',
        'facility_id',
        'space_id',
        'assigned_at',
        'released_at',
        'assigned_by_user_id',
        'released_by_user_id',
        'note',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    protected $appends = [
        'is_active',
        'duration_minutes',
    ];

    // Relationships
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'facility_id');
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(FacilitySpace::class, 'space_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by_user_id');
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('released_at');
    }

    public function scopeReleased(Builder $query): Builder
    {
        return $query->whereNotNull('released_at');
    }

    public function scopeForStaff(Builder $query, int $staffId): Builder
    {
        return $query->where('staff_id', $staffId);
    }

    public function scopeForFacility(Builder $query, int $facilityId): Builder
    {
        return $query->where('facility_id', $facilityId);
    }

    public function scopeForSpace(Builder $query, int $spaceId): Builder
    {
        return $query->where('space_id', $spaceId);
    }

    public function scopeAssignedBetween(Builder $query, Carbon $start, Carbon $end): Builder
    {
        return $query->whereBetween('assigned_at', [$start, $end]);
    }

    // Computed Attributes
    public function getIsActiveAttribute(): bool
    {
        return $this->released_at === null;
    }

    public function getDurationMinutesAttribute(): ?int
    {
        if (!$this->assigned_at) {
            return null;
        }

        $end = $this->released_at ?? now();
        return $this->assigned_at->diffInMinutes($end);
    }

    // Helper Methods
    public function release(?int $byUserId = null): bool
    {
        if ($this->released_at) {
            return false; // Already released
        }

        return $this->update([
            'released_at' => now(),
            'released_by_user_id' => $byUserId,
        ]);
    }

    public function isActiveFor(int $staffId, int $facilityId): bool
    {
        return $this->staff_id === $staffId
            && $this->facility_id === $facilityId
            && $this->is_active;
    }
}
