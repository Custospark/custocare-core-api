<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacilityOwner extends Model
{
    protected $fillable = [
        'facility_id',
        'staff_id',
        'is_primary_owner',
    ];

    protected $casts = [
        'is_primary_owner' => 'boolean',
    ];

    /**
     * Facility this ownership belongs to
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * Staff who owns the facility
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
