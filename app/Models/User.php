<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasPermissions;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $global_user_uuid
 * @property string $national_id_hash
 * @property string $national_id_encrypted
 * @property string $national_id_country_code
 * @property string $identity_state
 * @property Carbon|null $identity_verified_at
 * @property string|null $identity_verification_method
 * @property int|null $identity_verified_by_staff_id
 * @property string $data_residency_region
 * @property array|null $allowed_processing_regions
 * @property int|null $created_from_facility_id
 * @property string|null $email_encrypted
 * @property string|null $email_hash
 * @property string|null $phone_encrypted
 * @property string|null $phone_hash
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $title
 * @property string|null $display_name
 * @property Carbon|null $dob
 * @property string|null $gender
 * @property string|null $address_line1
 * @property string|null $address_line2
 * @property string|null $city
 * @property string|null $state
 * @property string|null $country
 * @property string|null $postal_code
 * @property string|null $password_hash
 * @property Carbon|null $password_changed_at
 * @property bool $requires_password_change
 * @property bool $mfa_enabled
 * @property string|null $mfa_secret_encrypted
 * @property Carbon|null $last_login_at
 * @property string|null $last_login_ip
 * @property string|null $last_login_user_agent
 * @property int $failed_login_attempts
 * @property Carbon|null $account_locked_until
 * @property int|null $created_by_staff_id
 * @property int|null $updated_by_staff_id
 * @property string|null $created_ip
 * @property array|null $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes,HasApiTokens,HasRoles,HasPermissions;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    public const TOKEN_EXPIRATION_MINUTES = 15;
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
        'last_login_ip',
        'last_login_user_agent',
        'created_by_staff_id',
        'updated_by_staff_id',
        'created_ip',
        'metadata',
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
    public function generateAuthToken(string $deviceName = 'auth-token'): string
    {
        // Delete all existing tokens to enforce single session
        $this->deleteAllTokens();
        
        // Create new token with expiration
        return $this->createToken($deviceName, ['*'], now()->addDays(7))->plainTextToken;
    }

    /**
     * Verify email address.
     */
    public function markEmailAsVerified(): void
    {
        $this->email_verified_at = now();
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
}