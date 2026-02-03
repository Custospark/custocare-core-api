<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;

class FacilitySpace extends Model
{
    protected $fillable = [
        'facility_id',
        'name',
        'type',
        'floor',
        'building',
        'capacity',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'capacity' => 'integer',
    ];

    protected $appends = [
        'is_occupied',
        'occupancy_count',
    ];

    // Relationships
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'facility_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(StaffSpaceAssignment::class, 'space_id');
    }

    public function activeAssignments(): HasMany
    {
        return $this->hasMany(StaffSpaceAssignment::class, 'space_id')
            ->whereNull('released_at');
    }

    public function currentAssignment(): HasOne
    {
        return $this->hasOne(StaffSpaceAssignment::class, 'space_id')
            ->whereNull('released_at')
            ->latest('assigned_at');
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOccupied(Builder $query): Builder
    {
        return $query->whereHas('activeAssignments');
    }

    public function scopeUnoccupied(Builder $query): Builder
    {
        return $query->whereDoesntHave('activeAssignments');
    }

    public function scopeForFacility(Builder $query, int $facilityId): Builder
    {
        return $query->where('facility_id', $facilityId);
    }

    public function scopeByType(Builder $query, ?string $type): Builder
    {
        if ($type) {
            return $query->where('type', $type);
        }
        return $query;
    }

    public function scopeByFloor(Builder $query, ?string $floor): Builder
    {
        if ($floor) {
            return $query->where('floor', $floor);
        }
        return $query;
    }

    public function scopeByBuilding(Builder $query, ?string $building): Builder
    {
        if ($building) {
            return $query->where('building', $building);
        }
        return $query;
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if ($search) {
            $searchTerm = trim($search);
            return $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                  ->orWhere('type', 'like', "%{$searchTerm}%")
                  ->orWhere('building', 'like', "%{$searchTerm}%")
                  ->orWhere('floor', 'like', "%{$searchTerm}%");
            });
        }
        return $query;
    }

    // Computed Attributes
    public function getIsOccupiedAttribute(): bool
    {
        if ($this->relationLoaded('activeAssignments')) {
            return $this->activeAssignments->isNotEmpty();
        }
        
        if ($this->relationLoaded('currentAssignment')) {
            return $this->currentAssignment !== null;
        }

        return $this->activeAssignments()->exists();
    }

    public function getOccupancyCountAttribute(): int
    {
        if ($this->relationLoaded('activeAssignments')) {
            return $this->activeAssignments->count();
        }

        return $this->activeAssignments()->count();
    }

    // Helper Methods
    public function isAvailable(): bool
    {
        return $this->is_active && !$this->is_occupied;
    }

    public function hasCapacity(): bool
    {
        if (!$this->capacity) {
            return true; // No capacity limit
        }

        return $this->occupancy_count < $this->capacity;
    }

    public function canAssignStaff(): bool
    {
        return $this->is_active && $this->hasCapacity();
    }
}
