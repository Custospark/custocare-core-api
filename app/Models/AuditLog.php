<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * AuditLog Model
 * 
 * Immutable audit trail for compliance, security, and operational monitoring.
 * This model should never be updated after creation to maintain audit integrity.
 * 
 * @property string $audit_uuid
 * @property string $operation
 * @property string $entity_type
 * @property int|null $entity_id
 * @property string|null $entity_identifier
 * @property array|null $previous_values
 * @property array|null $new_values
 * @property array|null $changed_fields
 * @property string $performed_by_type
 * @property int|null $performed_by_id
 * @property string|null $performed_by_identifier
 * @property string|null $performed_by_role
 * @property string $request_id
 * @property string|null $session_id
 * @property string|null $user_ip
 * @property string|null $user_agent
 * @property string|null $geolocation
 * @property string $compliance_reason
 * @property bool $legal_hold_flag
 * @property string|null $justification
 * @property int|null $facility_id
 * @property int|null $department_id
 * @property int|null $patient_id
 * @property bool $phi_accessed
 * @property array|null $phi_fields_accessed
 * @property string $result
 * @property string|null $failure_reason
 * @property string|null $error_code
 * @property int|null $operation_duration_ms
 * @property \Illuminate\Support\Carbon $created_at
 * @property array|null $metadata
 * @property \Illuminate\Support\Carbon|null $archived_at
 * @property \Illuminate\Support\Carbon|null $purged_at
 */
class AuditLog extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'audit_logs';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'int';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * The attributes that are mass assignable.
     * Strictly limited to maintain audit integrity.
     *
     * @var array<string>
     */
    protected $fillable = [
        'audit_uuid',
        'operation',
        'entity_type',
        'entity_id',
        'entity_identifier',
        'previous_values',
        'new_values',
        'changed_fields',
        'performed_by_type',
        'performed_by_id',
        'performed_by_identifier',
        'performed_by_role',
        'request_id',
        'session_id',
        'user_ip',
        'user_agent',
        'geolocation',
        'compliance_reason',
        'legal_hold_flag',
        'justification',
        'facility_id',
        'department_id',
        'patient_id',
        'phi_accessed',
        'phi_fields_accessed',
        'result',
        'failure_reason',
        'error_code',
        'operation_duration_ms',
        'metadata',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array<string>
     */
    protected $hidden = [];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'audit_uuid' => 'string',
        'operation' => 'string',
        'entity_type' => 'string',
        'entity_id' => 'integer',
        'entity_identifier' => 'string',
        'previous_values' => 'array',
        'new_values' => 'array',
        'changed_fields' => 'array',
        'performed_by_type' => 'string',
        'performed_by_id' => 'integer',
        'performed_by_identifier' => 'string',
        'performed_by_role' => 'string',
        'request_id' => 'string',
        'session_id' => 'string',
        'user_ip' => 'string',
        'user_agent' => 'string',
        'geolocation' => 'string',
        'compliance_reason' => 'string',
        'legal_hold_flag' => 'boolean',
        'justification' => 'string',
        'facility_id' => 'integer',
        'department_id' => 'integer',
        'patient_id' => 'integer',
        'phi_accessed' => 'boolean',
        'phi_fields_accessed' => 'array',
        'result' => 'string',
        'failure_reason' => 'string',
        'error_code' => 'string',
        'operation_duration_ms' => 'integer',
        'created_at' => 'datetime',
        'metadata' => 'array',
        'archived_at' => 'datetime',
        'purged_at' => 'datetime',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array<string>
     */
    protected $dates = [
        'created_at',
        'archived_at',
        'purged_at',
    ];

    /**
     * Disable timestamp updates as audit logs are immutable.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Boot the model.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        // Generate UUID before creating
        static::creating(function ($model) {
            if (empty($model->audit_uuid)) {
                $model->audit_uuid = \Illuminate\Support\Str::uuid()->toString();
            }
            
            // Ensure created_at is set
            if (empty($model->created_at)) {
                $model->created_at = now();
            }
        });

        // Prevent updates to existing audit logs
        static::updating(function ($model) {
            throw new \RuntimeException('Audit logs are immutable and cannot be updated.');
        });

        // Log deletion attempts (soft delete not appropriate for audit logs)
        static::deleting(function ($model) {
            // In production, you might want to prevent deletion entirely
            // or implement a legal hold check
            if ($model->legal_hold_flag) {
                throw new \RuntimeException('Audit log is under legal hold and cannot be deleted.');
            }
        });
    }

    /**
     * Scope a query to only include logs for a specific entity.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $entityType
     * @param int|null $entityId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForEntity($query, string $entityType, ?int $entityId = null)
    {
        $query->where('entity_type', $entityType);
        
        if ($entityId !== null) {
            $query->where('entity_id', $entityId);
        }
        
        return $query;
    }

    /**
     * Scope a query to only include logs for a specific patient.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $patientId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForPatient($query, int $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    /**
     * Scope a query to only include logs that accessed PHI.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAccessedPhi($query)
    {
        return $query->where('phi_accessed', true);
    }

    /**
     * Scope a query to only include logs for a specific compliance reason.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $complianceReason
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForComplianceReason($query, string $complianceReason)
    {
        return $query->where('compliance_reason', $complianceReason);
    }

    /**
     * Scope a query to only include logs for a specific time period.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Carbon\Carbon $startDate
     * @param \Carbon\Carbon|null $endDate
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForPeriod($query, \Carbon\Carbon $startDate, ?\Carbon\Carbon $endDate = null)
    {
        $query->where('created_at', '>=', $startDate);
        
        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }
        
        return $query;
    }

    /**
     * Scope a query to only include logs for a specific facility.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $facilityId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForFacility($query, int $facilityId)
    {
        return $query->where('facility_id', $facilityId);
    }

    /**
     * Get the performer name attribute.
     *
     * @return \Illuminate\Database\Eloquent\Casts\Attribute
     */
    protected function performerName(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->performed_by_identifier) {
                    return $this->performed_by_identifier;
                }
                
                return $this->performed_by_type . '#' . ($this->performed_by_id ?? 'unknown');
            }
        );
    }

    /**
     * Get the entity name attribute.
     *
     * @return \Illuminate\Database\Eloquent\Casts\Attribute
     */
    protected function entityName(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->entity_identifier) {
                    return $this->entity_identifier;
                }
                
                return $this->entity_type . '#' . ($this->entity_id ?? 'unknown');
            }
        );
    }

    /**
     * Check if the audit log is under legal hold.
     *
     * @return bool
     */
    public function isUnderLegalHold(): bool
    {
        return $this->legal_hold_flag;
    }

    /**
     * Check if the audit log contains PHI access.
     *
     * @return bool
     */
    public function containsPhiAccess(): bool
    {
        return $this->phi_accessed;
    }

    /**
     * Get the operation duration in seconds.
     *
     * @return float|null
     */
    public function getOperationDurationInSeconds(): ?float
    {
        return $this->operation_duration_ms ? $this->operation_duration_ms / 1000 : null;
    }

    /**
     * Get the age of the audit log in days.
     *
     * @return int
     */
    public function getAgeInDays(): int
    {
        return $this->created_at->diffInDays(now());
    }

    /**
     * Check if the audit log is eligible for archival.
     * Assuming archival after 90 days for hot storage.
     *
     * @return bool
     */
    public function isEligibleForArchival(): bool
    {
        return $this->getAgeInDays() > 90 && !$this->isUnderLegalHold();
    }

    /**
     * Check if the audit log is eligible for purging.
     * Assuming purging after 7 years (2555 days).
     *
     * @return bool
     */
    public function isEligibleForPurging(): bool
    {
        return $this->getAgeInDays() > 2555 && !$this->isUnderLegalHold();
    }
}