<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacilityShiftHandover extends Model
{
    protected $table = 'facility_shift_handovers';

    protected $fillable = [
        'facility_id',
        'ward_id',
        'shift_date',
        'shift_slot',
        'shift_label',
        'outgoing_summary',
        'pending_tasks_highlight',
        'incidents_notes',
        'equipment_issues',
        'staffing_notes',
        'handed_over_by_user_id',
        'handed_over_at',
        'received_by_user_id',
        'acknowledged_at',
        'status',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $appends = [
        'summary',
        'handed_over_to_user_id',
    ];

    protected $casts = [
        'shift_date' => 'date',
        'handed_over_at' => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    public function handedOverBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handed_over_by_user_id');
    }

    public function handedOverTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function getSummaryAttribute(): string
    {
        return $this->outgoing_summary;
    }

    public function getHandedOverToUserIdAttribute(): ?int
    {
        return $this->received_by_user_id;
    }
}
