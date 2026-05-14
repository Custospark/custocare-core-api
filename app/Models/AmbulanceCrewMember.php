<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AmbulanceCrewMember extends Model
{
    use HasFactory;

    protected $table = 'ambulance_crew_members';

    protected $fillable = [
        'ambulance_id',
        'staff_id',
        'role',
        'is_primary_driver',
        'certification_expiry',
        'active',
        'assigned_at',
        'unassigned_at',
        'metadata',
    ];

    protected $casts = [
        'is_primary_driver' => 'boolean',
        'active' => 'boolean',
        'certification_expiry' => 'date',
        'assigned_at' => 'datetime',
        'unassigned_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function ambulance(): BelongsTo
    {
        return $this->belongsTo(Ambulance::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
