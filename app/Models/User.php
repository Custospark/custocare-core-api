<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasPermissions;
use Spatie\Permission\Traits\HasRoles;


class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes,HasApiTokens,HasRoles,HasPermissions;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    public const TOKEN_EXPIRATION_MINUTES = 10;
    public const OTP_EXPIRATION_MINUTES = 10;

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $fillable = [
        'global_user_uuid',
        'national_id_hash',
        'national_id_encrypted',
        'national_id_country_code',
        'identity_state',
        'identity_verified_at',
        'identity_verification_method',
        'identity_verified_by_staff_id',
        'data_residency_region',
        'allowed_processing_regions',
        'created_from_facility_id',
        'email_encrypted',
        'email_hash',
        'phone_encrypted',
        'phone_hash',
        'first_name',
        'last_name',
        'title',
        'display_name',
        'dob',
        'gender',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'country',
        'postal_code',
        'password_hash',
        'requires_password_change',
        'mfa_enabled',
        'mfa_secret_encrypted',
        'last_login_at',
        'last_login_ip',
        'last_login_user_agent',
        'failed_login_attempts',
        'created_by_staff_id',
        'updated_by_staff_id',
        'created_ip',
        'metadata',
        /*
        |--------------------------------------------------------------------------
        | 🆕 User Status Fields
        |--------------------------------------------------------------------------
        */
        'status',
        'status_reason',
        'status_set_at',
        'status_set_by',
       
        'theme_mode',
        'ui_density',
        'timezone',
        'locale',
        'profile_photo_path',
        'profile_photo_disk',
        'profile_photo_updated_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<string>
     */
    protected $hidden = [
        'national_id_encrypted',
        'email_encrypted',
        'phone_encrypted',
        'password_hash',
        'mfa_secret_encrypted',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'identity_state' => 'string',
        'identity_verified_at' => 'datetime',
        'allowed_processing_regions' => 'array',
        'dob' => 'date',
        'requires_password_change' => 'boolean',
        'mfa_enabled' => 'boolean',
        'last_login_at' => 'datetime',
        'account_locked_until' => 'datetime',
        'password_changed_at' => 'datetime',
        'metadata' => 'array',
        'failed_login_attempts' => 'integer',
        'theme_mode' => 'string',
        'ui_density' => 'string',
        'timezone' => 'string',
        'locale' => 'string',
        'profile_photo_path' => 'string',
        'profile_photo_disk' => 'string',
        'profile_photo_updated_at' => 'datetime',
    ];

    /**
     * Get the name of the unique identifier for the user.
     *
     * @return string
     */
    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    /**
     * Get the password for the user.
     *
     * @return string
     */
    public function getAuthPassword(): string
    {
        return $this->password_hash ?? '';
    }  

    /**
     * Delete all existing tokens for this user.
     */
    public function deleteAllTokens(): void
    {
        $this->tokens()->delete();
    }

    /**
     * Generate a new token with single session enforcement.
     */
public function generateAuthToken(string $deviceName = 'auth-token', bool $forceNew = false): string
{
    // If not forcing new token, check for existing valid token
    if (!$forceNew) {
        $existingToken = $this->tokens()
            ->where('name', $deviceName)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();
        
        if ($existingToken && $existingToken->plainTextToken) {
            return $existingToken->plainTextToken;
        }
    }
    
    // Delete all existing tokens to enforce single session
    $this->deleteAllTokens();
    
    // Create new token with expiration
    $newToken = $this->createToken($deviceName, ['*'], now()->addDays(7));
    
    // Make sure we have a valid token
    if (!$newToken || !$newToken->plainTextToken) {
        throw new \RuntimeException('Failed to create authentication token');
    }
    
    return $newToken->plainTextToken;
}

    /**
     * Verify email address.
     */
    public function markEmailAsVerified(): void
    {
        $this->email_verified_at = now();
        $this->identity_verified_at = now();
        $this->identity_verification_method = 'email';
        $this->save();
    }

    /**
     * Check if email is verified.
     */
    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }
    /**
     * Check if user's identity is verified.
     *
     * @return bool
     */
    public function isIdentityVerified(): bool
    {
        return $this->identity_state === 'verified';
    }

    /**
     * Check if account is locked.
     *
     * @return bool
     */
    public function isAccountLocked(): bool
    {
        if (!$this->account_locked_until) {
            return false;
        }

        return Carbon::now()->lt($this->account_locked_until);
    }

    /**
     * Get full name.
     *
     * @return string|null
     */
    public function getFullNameAttribute(): ?string
    {
        if ($this->first_name && $this->last_name) {
            return "{$this->first_name} {$this->last_name}";
        }

        return $this->display_name;
    }

    /**
     * Patient chart linked to this account (portal / patient role).
     * Used by {@see \App\Policies\AppointmentPolicy} and appointment booking validation.
     */
    public function patientProfile(): HasOne
    {
        return $this->hasOne(Patient::class, 'user_id');
    }

    /**
     * Staff profile linked to this login (clinical / facility users).
     */
    public function staff(): HasOne
    {
        return $this->hasOne(Staff::class, 'user_id');
    }

    /**
     * Check if the user has an associated staff profile with active
     * or on-leave facility assignment.
     */
    public function isStaff(): bool
    {
        return $this->staff()
            ->whereHas('facilityStaffRoles', function ($q) {
                $q->whereIn('assignment_status', [
                    FacilityStaffRole::STATUS_ACTIVE,
                    FacilityStaffRole::STATUS_ON_LEAVE,
                ]);
            })
            ->exists();
    }

    /**
     * Check if the user has a specific permission.
     * Delegates to Spatie's hasPermissionTo().
     */
    public function hasPermission(string $permission): bool
    {
        return $this->hasPermissionTo($permission);
    }

      public function notifications()
    {
        return $this->hasMany(Notification::class);
    }
        public function readNotifications()
    {
        return $this->belongsToMany(Notification::class, 'notification_user')
                    ->withPivot('read_at')
                    ->withTimestamps();
    }

    // In User model temporarily add:
public function setEmailAttribute($value)
{
    $this->attributes['email'] = $value;
    $this->attributes['email_hash'] = hash('sha256', strtolower(trim($value)));
    Log::debug('Setting user email', [
        'email' => $value,
        'email_hash' => $this->attributes['email_hash']
    ]);
}
}