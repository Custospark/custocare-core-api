<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ambulance extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ambulance_uuid',
        'facility_id',
        'crew_team_lead_staff_id',
        'vehicle_identifier',
        'vehicle_type',
        'equipment_level',
        'status',
        'last_service_date',
        'next_service_due_date',
        'current_mileage',
        'capacity',
        'features',
        'metadata',
        'created_by_staff_id',
        'updated_by_staff_id',
    ];

    protected $casts = [
        'ambulance_uuid' => 'string',
        'last_service_date' => 'date',
        'next_service_due_date' => 'date',
        'current_mileage' => 'integer',
        'capacity' => 'integer',
        'features' => 'array',
        'metadata' => 'array',
    ];

    protected $hidden = ['deleted_at'];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->ambulance_uuid)) {
                $model->ambulance_uuid = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function crewTeamLead(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'crew_team_lead_staff_id');
    }

    public function trips(): HasMany
    {
        return $this->hasMany(AmbulanceTrip::class);
    }

    public function crewMembers(): HasMany
    {
        return $this->hasMany(AmbulanceCrewMember::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by_staff_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'updated_by_staff_id');
    }

    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    public function scopeForFacility($query, int $facilityId)
    {
        return $query->where('facility_id', $facilityId);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('vehicle_type', $type);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
