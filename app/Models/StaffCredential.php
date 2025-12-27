<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;

class StaffCredential extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'credential_uuid',
        'staff_id',
        'credential_type',
        'credential_name',
        'credential_number_encrypted',
        'credential_number_hash',
        'issuing_authority',
        'issuing_authority_contact',
        'issuing_state_country',
        'issued_date',
        'valid_from',
        'valid_to',
        'requires_renewal',
        'renewal_reminder_date',
        'verification_status',
        'verified_at',
        'verified_by_staff_id',
        'verification_method',
        'verification_notes',
        'credential_document_hash',
        'document_storage_path',
        'document_mime_type',
        'document_size_bytes',
        'snapshot_taken_at',
        'is_current',
        'superseded_by_credential_id',
        'created_by_staff_id',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'issued_date' => 'date',
        'valid_from' => 'date',
        'valid_to' => 'date',
        'renewal_reminder_date' => 'date',
        'verified_at' => 'datetime',
        'snapshot_taken_at' => 'datetime',
        'requires_renewal' => 'boolean',
        'is_current' => 'boolean',
        'metadata' => AsArrayObject::class,
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'credential_number_encrypted',
        'credential_number_hash',
        'document_storage_path',
    ];

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'credential_uuid';
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->credential_uuid)) {
                $model->credential_uuid = (string) \Illuminate\Support\Str::uuid();
            }
            if (empty($model->snapshot_taken_at)) {
                $model->snapshot_taken_at = now();
            }
        });
    }

    /**
     * Relationship: Staff member who owns this credential
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function staff(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /**
     * Relationship: Staff member who verified this credential
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function verifiedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Staff::class, 'verified_by_staff_id');
    }

    /**
     * Relationship: Staff member who created this credential record
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function createdBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by_staff_id');
    }

    /**
     * Relationship: Credential that supersedes this one
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function supersededBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_credential_id');
    }

    /**
     * Relationship: Credentials that this credential supersedes
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function supersedes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(self::class, 'superseded_by_credential_id');
    }

    /**
     * Scope: Only current credentials
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCurrent($query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_current', true);
    }

    /**
     * Scope: Credentials expiring soon (within 30 days)
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $days Number of days to check
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeExpiringSoon($query, int $days = 30): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('valid_to', '<=', now()->addDays($days))
                    ->where('valid_to', '>', now())
                    ->where('is_current', true)
                    ->where('verification_status', 'verified');
    }

    /**
     * Scope: Expired credentials
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeExpired($query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('valid_to', '<', now())
                    ->where('is_current', true)
                    ->where('verification_status', 'verified');
    }

    /**
     * Check if credential is expired
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        if (!$this->valid_to) {
            return false;
        }
        
        return $this->valid_to->isPast() 
            && $this->is_current 
            && $this->verification_status === 'verified';
    }

    /**
     * Check if credential requires renewal
     *
     * @return bool
     */
    public function requiresRenewal(): bool
    {
        if (!$this->requires_renewal) {
            return false;
        }
        
        if (!$this->valid_to) {
            return false;
        }
        
        return $this->valid_to->isFuture() 
            && $this->valid_to->diffInDays(now()) <= 60;
    }

    /**
     * Get all credential type options
     *
     * @return array
     */
    public static function getCredentialTypes(): array
    {
        return [
            'medical_license',
            'board_certification',
            'dea_registration',
            'cds_registration',
            'malpractice_insurance',
            'professional_liability',
            'cpr_certification',
            'acls_certification',
            'pals_certification',
            'bls_certification',
            'specialty_training',
            'continuing_education',
            'privileging',
            'hospital_affiliation'
        ];
    }

    /**
     * Get all verification status options
     *
     * @return array
     */
    public static function getVerificationStatuses(): array
    {
        return [
            'pending',
            'verified',
            'expired',
            'suspended',
            'revoked',
            'under_review'
        ];
    }
}