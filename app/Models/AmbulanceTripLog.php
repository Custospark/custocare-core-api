<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AmbulanceTripLog extends Model
{
    use HasFactory;

    protected $table = 'ambulance_trip_logs';

    protected $fillable = [
        'trip_id',
        'event_type',
        'description',
        'recorded_at',
        'recorded_by_staff_id',
        'metadata',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function trip(): BelongsTo
    {
        return $this->belongsTo(AmbulanceTrip::class, 'trip_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'recorded_by_staff_id');
    }
}
