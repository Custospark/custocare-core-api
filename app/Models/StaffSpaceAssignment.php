<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function scopeActive($query)
    {
        return $query->whereNull('released_at');
    }
}
