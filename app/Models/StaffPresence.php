<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffPresence extends Model
{
    protected $table = 'staff_presences';

    protected $fillable = [
        'staff_id',
        'facility_id',
        'status',
        'started_at',
        'ended_at',
        'updated_by',
        'updated_by_user_id',
        'note',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'facility_id');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('ended_at');
    }

    public function scopeEligibleForForwarding($query)
    {
        return $query->whereIn('status', ['on_duty', 'busy']);
    }
}
