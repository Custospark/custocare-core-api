<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * App\Models\User
 *
 * @property int $id
 * @property string $global_user_uuid
 * @property string $national_id_hash
 * @property string $national_id_encrypted
 * @property string $national_id_country_code
 * @property string $identity_state
 * @property \Illuminate\Support\Carbon|null $identity_verified_at
 * @property string|null $identity_verification_method
 * @property int|null $identity_verified_by_staff_id
 * @property string $data_residency_region
 * @property array|null $allowed_processing_regions
 * @property int|null $created_from_facility_id
 * @property string|null $email_encrypted
 * @property string|null $email_hash
 * @property string|null $phone_encrypted
 * @property string|null $phone_hash
 * @property string|null $password_hash
 * @property \Illuminate\Support\Carbon|null $password_changed_at
 * @property bool $requires_password_change
 * @property bool $mfa_enabled
 * @property string|null $mfa_secret_encrypted
 * @property \Illuminate\Support\Carbon|null $last_login_at
 * @property string|null $last_login_ip
 * @property string|null $last_login_user_agent
 * @property int $failed_login_attempts
 * @property \Illuminate\Support\Carbon|null $account_locked_until
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property int|null $created_by_staff_id
 * @property int|null $updated_by_staff_id
 * @property string|null $created_ip
 * @property array|null $metadata
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|User onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|User query()
 * @method static \Illuminate\Database\Eloquent\Builder|User whereAccountLockedUntil($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereAllowedProcessingRegions($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereCreatedByStaffId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereCreatedFromFacilityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereCreatedIp($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereDataResidencyRegion($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereEmailEncrypted($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereEmailHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereFailedLoginAttempts($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereGlobalUserUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereIdentityState($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereIdentityVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereIdentityVerifiedByStaffId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereIdentityVerificationMethod($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereLastLoginAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereLastLoginIp($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereLastLoginUserAgent($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereMfaEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereMfaSecretEncrypted($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereNationalIdCountryCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereNationalIdEncrypted($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereNationalIdHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User wherePasswordChangedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User wherePasswordHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User wherePhoneEncrypted($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User wherePhoneHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereRequiresPasswordChange($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereUpdatedByStaffId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|User withoutTrashed()
 * @mixin \Eloquent
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes,HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
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
        'password_hash',
        'password_changed_at',
        'requires_password_change',
        'mfa_enabled',
        'mfa_secret_encrypted',
        'last_login_at',
        'last_login_ip',
        'last_login_user_agent',
        'failed_login_attempts',
        'account_locked_until',
        'created_by_staff_id',
        'updated_by_staff_id',
        'created_ip',
        'metadata',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'national_id_encrypted',
        'password_hash',
        'mfa_secret_encrypted',
        'email_encrypted',
        'phone_encrypted',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'identity_verified_at' => 'datetime',
        'password_changed_at' => 'datetime',
        'last_login_at' => 'datetime',
        'account_locked_until' => 'datetime',
        'requires_password_change' => 'boolean',
        'mfa_enabled' => 'boolean',
        'failed_login_attempts' => 'integer',
        'allowed_processing_regions' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Identity states enum.
     */
    public const IDENTITY_STATE_PENDING = 'pending';
    public const IDENTITY_STATE_VERIFIED = 'verified';
    public const IDENTITY_STATE_SUSPENDED = 'suspended';
    public const IDENTITY_STATE_ARCHIVED = 'archived';

    /**
     * Get the identity states.
     *
     * @return array<string, string>
     */
    public static function getIdentityStates(): array
    {
        return [
            self::IDENTITY_STATE_PENDING => 'Pending',
            self::IDENTITY_STATE_VERIFIED => 'Verified',
            self::IDENTITY_STATE_SUSPENDED => 'Suspended',
            self::IDENTITY_STATE_ARCHIVED => 'Archived',
        ];
    }

    /**
     * Boot the model.
     *
     * @return void
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->global_user_uuid)) {
                $model->global_user_uuid = \Illuminate\Support\Str::uuid()->toString();
            }
        });
    }

    /**
     * Check if the user's identity is verified.
     *
     * @return bool
     */
    public function isIdentityVerified(): bool
    {
        return $this->identity_state === self::IDENTITY_STATE_VERIFIED 
            && $this->identity_verified_at !== null;
    }

    /**
     * Check if the account is locked.
     *
     * @return bool
     */
    public function isAccountLocked(): bool
    {
        return $this->account_locked_until && $this->account_locked_until->isFuture();
    }

    /**
     * Increment failed login attempts.
     *
     * @return void
     */
    public function incrementFailedLoginAttempts(): void
    {
        $this->increment('failed_login_attempts');
    }

    /**
     * Reset failed login attempts.
     *
     * @return void
     */
    public function resetFailedLoginAttempts(): void
    {
        $this->failed_login_attempts = 0;
        $this->account_locked_until = null;
    }
}