<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AmbulanceTrip extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ambulance_trips';

    protected $fillable = [
        'trip_uuid',
        'facility_id',
        'patient_id',
        'visit_id',
        'ambulance_id',
        'dispatch_staff_id',
        'requesting_staff_id',
        'trip_type',
        'priority',
        'status',
        'pickup_location',
        'pickup_facility_id',
        'destination_location',
        'destination_facility_id',
        'dispatch_notes',
        'trip_notes',
        'mileage',
        'estimated_duration_minutes',
        'dispatched_at',
        'en_route_at',
        'on_scene_at',
        'patient_contact_at',
        'depart_scene_at',
        'at_destination_at',
        'completed_at',
        'cancelled_at',
        'cancellation_reason',
        'metadata',
        'created_by_staff_id',
        'updated_by_staff_id',
    ];

    protected $casts = [
        'trip_uuid' => 'string',
        'mileage' => 'decimal:2',
        'estimated_duration_minutes' => 'integer',
        'dispatched_at' => 'datetime',
        'en_route_at' => 'datetime',
        'on_scene_at' => 'datetime',
        'patient_contact_at' => 'datetime',
        'depart_scene_at' => 'datetime',
        'at_destination_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected $hidden = ['deleted_at'];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->trip_uuid)) {
                $model->trip_uuid = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function ambulance(): BelongsTo
    {
        return $this->belongsTo(Ambulance::class);
    }

    public function dispatchStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'dispatch_staff_id');
    }

    public function requestingStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'requesting_staff_id');
    }

    public function pickupFacility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'pickup_facility_id');
    }

    public function destinationFacility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'destination_facility_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(AmbulanceTripLog::class, 'trip_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by_staff_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'updated_by_staff_id');
    }

    // ─── Status helpers ───

    public function isRequested(): bool { return $this->status === 'requested'; }
    public function isDispatched(): bool { return $this->status === 'dispatched'; }
    public function isEnRoute(): bool { return $this->status === 'en_route'; }
    public function isOnScene(): bool { return $this->status === 'on_scene'; }
    public function isTransporting(): bool { return $this->status === 'transporting'; }
    public function isAtDestination(): bool { return $this->status === 'at_destination'; }
    public function isCompleted(): bool { return $this->status === 'completed'; }
    public function isCancelled(): bool { return $this->status === 'cancelled'; }

    // ─── Status transitions ───

    public function dispatch(): bool
    {
        return $this->update(['status' => 'dispatched', 'dispatched_at' => now()]);
    }

    public function markEnRoute(): bool
    {
        return $this->update(['status' => 'en_route', 'en_route_at' => now()]);
    }

    public function markOnScene(): bool
    {
        return $this->update(['status' => 'on_scene', 'on_scene_at' => now()]);
    }

    public function markPatientContact(): bool
    {
        return $this->update(['status' => 'transporting', 'patient_contact_at' => now()]);
    }

    public function markDepartScene(): bool
    {
        return $this->update(['depart_scene_at' => now()]);
    }

    public function markAtDestination(): bool
    {
        return $this->update(['status' => 'at_destination', 'at_destination_at' => now()]);
    }

    public function markCompleted(): bool
    {
        $data = ['status' => 'completed', 'completed_at' => now()];
        if ($this->dispatched_at && $this->completed_at) {
            $data['actual_duration_minutes'] = $this->dispatched_at->diffInMinutes($this->completed_at);
        }
        return $this->update($data);
    }

    public function cancel(?string $reason = null): bool
    {
        $data = ['status' => 'cancelled', 'cancelled_at' => now(), 'cancellation_reason' => $reason];
        return $this->update($data);
    }

    // ─── Scopes ───

    public function scopeForPatient($query, int $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    public function scopeForFacility($query, int $facilityId)
    {
        return $query->where('facility_id', $facilityId);
    }

    public function scopeFromFacility($query, int $facilityId)
    {
        return $query->where(function ($q) use ($facilityId) {
            $q->where('facility_id', $facilityId)
              ->orWhere('pickup_facility_id', $facilityId);
        });
    }

    public function scopeToFacility($query, int $facilityId)
    {
        return $query->where('destination_facility_id', $facilityId);
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', ['completed', 'cancelled']);
    }

    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('trip_type', $type);
    }
}
