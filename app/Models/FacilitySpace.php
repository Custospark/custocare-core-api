<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
}
