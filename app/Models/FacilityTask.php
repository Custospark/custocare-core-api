<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FacilityTask extends Model
{
    protected $table = 'facility_tasks';

    protected $fillable = [
        'facility_id',
        'title',
        'description',
        'category',
        'priority',
        'status',
        'due_at',
        'assigned_to_user_id',
        'assigned_by_user_id',
        'ward_id',
        'visit_uuid',
        'started_at',
        'completed_at',
        'cancelled_at',
        'cancellation_reason',
        'completion_notes',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'visit_uuid' => 'string',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class, 'visit_uuid', 'visit_uuid');
    }

    public function events(): HasMany
    {
        return $this->hasMany(FacilityTaskEvent::class, 'facility_task_id')->orderByDesc('id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
