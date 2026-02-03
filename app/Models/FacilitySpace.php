<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FacilitySpace extends Model
{
    protected $fillable = [
        'facility_id',
        'name',
        'type',
        'floor',
        'building',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'facility_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(StaffSpaceAssignment::class, 'space_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function currentAssignment(): HasOne
    {
        return $this->hasOne(StaffSpaceAssignment::class, 'space_id')
            ->whereNull('released_at')
            ->latest('assigned_at');
    }

    /**
     * Only spaces that have NO active assignment (released_at IS NULL).
     */
    public function scopeUnoccupied($query)
    {
        return $query->whereDoesntHave('assignments', function ($q) {
            $q->whereNull('released_at');
        });
    }
}
