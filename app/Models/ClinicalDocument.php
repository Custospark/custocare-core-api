<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;

/**
 * App\Models\ClinicalDocument
 *
 * @property int $id
 * @property string $document_uuid
 * @property int $facility_id
 * @property int $patient_id
 * @property int|null $visit_id
 * @property string $document_type
 * @property string $document_name
 * @property string|null $document_description
 * @property string $file_mime_type
 * @property string $file_extension
 * @property int $file_size_bytes
 * @property string $file_storage_path
 * @property string $file_hash
 * @property string|null $document_date
 * @property int|null $authored_by_staff_id
 * @property string|null $external_author
 * @property string $status
 * @property array|null $metadata
 * @property int|null $uploaded_by_staff_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class ClinicalDocument extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'document_uuid',
        'facility_id',
        'patient_id',
        'visit_id',
        'document_type',
        'document_name',
        'document_description',
        'file_mime_type',
        'file_extension',
        'file_size_bytes',
        'file_storage_path',
        'file_hash',
        'document_date',
        'authored_by_staff_id',
        'external_author',
        'status',
        'metadata',
        'uploaded_by_staff_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'document_date' => 'date',
        'metadata' => 'array',
        'file_size_bytes' => 'integer',
        'facility_id' => 'integer',
        'patient_id' => 'integer',
        'visit_id' => 'integer',
        'authored_by_staff_id' => 'integer',
        'uploaded_by_staff_id' => 'integer',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'file_storage_path',
    ];

    /**
     * Document type constants for type safety
     */
    public const TYPE_LAB_REPORT = 'lab_report';
    public const TYPE_RADIOLOGY_REPORT = 'radiology_report';
    public const TYPE_PATHOLOGY_REPORT = 'pathology_report';
    public const TYPE_OPERATIVE_NOTE = 'operative_note';
    public const TYPE_DISCHARGE_SUMMARY = 'discharge_summary';
    public const TYPE_CONSULTATION_LETTER = 'consultation_letter';
    public const TYPE_REFERRAL_LETTER = 'referral_letter';
    public const TYPE_CONSENT_FORM = 'consent_form';
    public const TYPE_ADVANCE_DIRECTIVE = 'advance_directive';
    public const TYPE_INSURANCE_CARD = 'insurance_card';
    public const TYPE_IDENTIFICATION = 'identification';
    public const TYPE_MEDICAL_RECORD_REQUEST = 'medical_record_request';
    public const TYPE_OTHER = 'other';

    /**
     * Status constants
     */
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUPERSEDED = 'superseded';
    public const STATUS_ENTERED_IN_ERROR = 'entered_in_error';

    /**
     * Get all valid document types
     *
     * @return array
     */
    public static function getValidDocumentTypes(): array
    {
        return [
            self::TYPE_LAB_REPORT,
            self::TYPE_RADIOLOGY_REPORT,
            self::TYPE_PATHOLOGY_REPORT,
            self::TYPE_OPERATIVE_NOTE,
            self::TYPE_DISCHARGE_SUMMARY,
            self::TYPE_CONSULTATION_LETTER,
            self::TYPE_REFERRAL_LETTER,
            self::TYPE_CONSENT_FORM,
            self::TYPE_ADVANCE_DIRECTIVE,
            self::TYPE_INSURANCE_CARD,
            self::TYPE_IDENTIFICATION,
            self::TYPE_MEDICAL_RECORD_REQUEST,
            self::TYPE_OTHER,
        ];
    }

    /**
     * Get all valid statuses
     *
     * @return array
     */
    public static function getValidStatuses(): array
    {
        return [
            self::STATUS_ACTIVE,
            self::STATUS_SUPERSEDED,
            self::STATUS_ENTERED_IN_ERROR,
        ];
    }

    /**
     * Boot the model
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->document_uuid)) {
                $model->document_uuid = \Illuminate\Support\Str::uuid()->toString();
            }
        });
    }

    /**
     * Relationship: Patient
     */
    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Relationship: Visit
     */
    public function visit()
    {
        return $this->belongsTo(Visit::class);
    }

    /**
     * Relationship: Facility
     */
    public function facility()
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * Relationship: Uploaded by staff
     */
    public function uploader()
    {
        return $this->belongsTo(Staff::class, 'uploaded_by_staff_id');
    }

    /**
     * Relationship: Author staff
     */
    public function author()
    {
        return $this->belongsTo(Staff::class, 'authored_by_staff_id');
    }

    /**
     * Scope: Active documents
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope: By patient
     */
    public function scopeByPatient($query, int $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    /**
     * Scope: By document type
     */
    public function scopeByDocumentType($query, string $documentType)
    {
        return $query->where('document_type', $documentType);
    }

    /**
     * Get human readable file size
     */
    public function getHumanFileSizeAttribute(): string
    {
        $bytes = $this->file_size_bytes;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Check if document is active
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}