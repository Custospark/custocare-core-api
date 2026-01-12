<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Permission\Models\Role;

class StaffInvitation extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'invitation_uuid',
        'staff_id',
        'facility_id',
        'department_id',
        'role_code',
        'module_code',
        'status',
        'sent_at',
        'responded_at',
        'expires_at',
        'invited_by_staff_id',
        'reminder_sent_at',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
      protected $casts = [
        'module_code' => 'array',
        'metadata'    => 'array',
        'sent_at'     => 'datetime',
        'reminder_sent_at' => 'datetime',
        'responded_at' => 'datetime',
        'expires_at'  => 'datetime',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'sent_at',
        'responded_at',
        'expires_at',
        'deleted_at',
    ];

    /**
     * Get the staff member being invited.
     */
    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    /**
     * Get the facility where the staff is invited.
     */
    public function facility()
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * Get the department (if specified).
     */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

      /**
     * Check if the invitation is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Get the role (if specified).Use FacilityStaffRole Table.
     */
    public function role()
    {
        return $this->belongsTo(FacilityStaffRole::class);
    }

    /**
     * Get the staff member who sent the invitation.
     */
    public function invitedBy()
    {
        return $this->belongsTo(Staff::class, 'invited_by_staff_id');
    }

    /**
     * Mark the invitation as sent.
     */
    public function markAsSent(): void
    {
        $this->update([
            'sent_at' => now(),
            'status' => 'pending',
        ]);
    }

    /**
     * Mark the invitation as accepted.
     */
    public function markAsAccepted(): void
    {
        $this->update([
            'status' => 'accepted',
            'responded_at' => now(),
        ]);
    }

    /**
     * Mark the invitation as declined.
     */
    public function markAsDeclined(): void
    {
        $this->update([
            'status' => 'declined',
            'responded_at' => now(),
        ]);
    }

    /**
     * Mark the invitation as expired.
     */
    public function markAsExpired(): void
    {
        $this->update([
            'status' => 'expired',
        ]);
    }
    /**
     * Check if invitation can be accepted
     * 
     * @return bool
     */
    public function canBeAccepted(): bool
    {
        return $this->status === 'pending' && !$this->isExpired();
    }

    /**
     * Check if invitation can be declined
     * 
     * @return bool
     */
    public function canBeDeclined(): bool
    {
        return $this->status === 'pending' && !$this->isExpired();
    }

    /**
     * Check if invitation is expired
     * 
     * @return bool
     */
    public function isExpired(): bool
    {
        if (!$this->expires_at) {
            return false;
        }

        return now()->isAfter($this->expires_at);
    }

    /**
     * Get days until expiry
     * 
     * @return int|null
     */
    public function getDaysUntilExpiryAttribute(): ?int
    {
        if (!$this->expires_at) {
            return null;
        }

        if ($this->isExpired()) {
            return 0;
        }

        return now()->diffInDays($this->expires_at, false);
    }

   
    public function facilityStaffRole(): HasOne
    {
        return $this->hasOne(FacilityStaffRole::class, 'staff_invitation_id');
    }
}