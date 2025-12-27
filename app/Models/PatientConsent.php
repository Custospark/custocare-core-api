<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;

class PatientConsent extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'consent_uuid',
        'patient_id',
        'consent_type',
        'scope_facility_ids',
        'scope_department_ids',
        'scope_staff_ids',
        'scope_service_categories',
        'scope_limitations',
        'legal_basis',
        'granted_at',
        'effective_from',
        'expires_at',
        'revoked_at',
        'revocation_reason',
        'revoked_by_staff_id',
        'witnessed_by_staff_id',
        'witness_signature_hash',
        'patient_signature_hash',
        'signature_method',
        'consent_ip_address',
        'consent_user_agent',
        'consent_device_fingerprint',
        'consent_geolocation',
        'consent_form_version',
        'consent_document_hash',
        'consent_document_storage_path',
        'consent_document_metadata',
        'consent_language',
        'interpreter_used',
        'interpreter_language',
        'capacity_confirmed',
        'legal_guardian_id',
        'status',
        'superseded_by_consent_id',
        'audit_trail',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'consent_uuid' => 'string',
        'granted_at' => 'datetime',
        'effective_from' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'scope_facility_ids' => AsArrayObject::class,
        'scope_department_ids' => AsArrayObject::class,
        'scope_staff_ids' => AsArrayObject::class,
        'scope_service_categories' => AsArrayObject::class,
        'consent_document_metadata' => AsArrayObject::class,
        'audit_trail' => AsArrayObject::class,
        'metadata' => AsArrayObject::class,
        'interpreter_used' => 'boolean',
        'capacity_confirmed' => 'boolean',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'consent_document_hash',
        'witness_signature_hash',
        'patient_signature_hash',
        'consent_device_fingerprint',
    ];

    /**
     * Get the route key for the model.
     *
     * @return string
     */
    public function getRouteKeyName()
    {
        return 'consent_uuid';
    }

    /**
     * Boot the model.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->consent_uuid)) {
                $model->consent_uuid = (string) \Illuminate\Support\Str::orderedUuid();
            }
        });
    }

    /**
     * Get the patient that owns the consent.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get the staff member who witnessed the consent.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function witness()
    {
        return $this->belongsTo(Staff::class, 'witnessed_by_staff_id');
    }

    /**
     * Get the staff member who revoked the consent.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function revoker()
    {
        return $this->belongsTo(Staff::class, 'revoked_by_staff_id');
    }

    /**
     * Get the legal guardian if patient lacks capacity.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function legalGuardian()
    {
        return $this->belongsTo(Patient::class, 'legal_guardian_id');
    }

    /**
     * Get the consent that superseded this consent.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function supersededBy()
    {
        return $this->belongsTo(PatientConsent::class, 'superseded_by_consent_id');
    }

    /**
     * Scope a query to only include active consents.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->whereNull('revoked_at');
    }

    /**
     * Scope a query to only include consents for a specific type.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('consent_type', $type);
    }

    /**
     * Check if the consent is currently active.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->status === 'active' &&
            ($this->expires_at === null || $this->expires_at->isFuture()) &&
            $this->revoked_at === null;
    }

    /**
     * Check if the consent is expired.
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        return $this->status === 'expired' ||
            ($this->expires_at !== null && $this->expires_at->isPast());
    }

    /**
     * Check if the consent is revoked.
     *
     * @return bool
     */
    public function isRevoked(): bool
    {
        return $this->status === 'revoked' || $this->revoked_at !== null;
    }

    /**
     * Get the consent types as a human-readable array.
     *
     * @return array
     */
    public static function getConsentTypes(): array
    {
        return [
            'treatment' => 'General Treatment Authorization',
            'procedures' => 'Specific Procedure Consent',
            'anesthesia' => 'Anesthesia Administration',
            'blood_transfusion' => 'Blood Product Consent',
            'research' => 'Clinical Trial Participation',
            'data_sharing' => 'EHR Data Sharing',
            'marketing' => 'Marketing Communications',
            'photography' => 'Clinical Photography',
            'teaching' => 'Teaching Hospital Participation',
            'organ_donation' => 'Organ/Tissue Donation',
            'release_of_info' => 'Information Release to Third Parties',
        ];
    }

    /**
     * Get the legal basis options as a human-readable array.
     *
     * @return array
     */
    public static function getLegalBasisOptions(): array
    {
        return [
            'explicit_consent' => 'Explicit Consent (GDPR Article 6(1)(a))',
            'contractual' => 'Contractual Necessity (GDPR Article 6(1)(b))',
            'legal_obligation' => 'Legal Obligation (GDPR Article 6(1)(c))',
            'vital_interests' => 'Vital Interests (GDPR Article 6(1)(d))',
            'legitimate_interest' => 'Legitimate Interest (GDPR Article 6(1)(f))',
        ];
    }
}