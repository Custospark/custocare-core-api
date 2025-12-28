<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $attachment_uuid
 * @property int $message_id
 * @property string $attachment_type
 * @property string $file_name
 * @property string $mime_type
 * @property int $file_size_bytes
 * @property string $storage_disk
 * @property string $storage_path
 * @property bool $contains_phi
 * @property string $checksum
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * 
 * @property-read Message $message
 */
class MessageAttachment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'attachment_uuid',
        'message_id',
        'attachment_type',
        'file_name',
        'mime_type',
        'file_size_bytes',
        'storage_disk',
        'storage_path',
        'contains_phi',
        'checksum',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'attachment_uuid' => 'string',
        'message_id' => 'integer',
        'attachment_type' => 'string',
        'file_size_bytes' => 'integer',
        'contains_phi' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array
     */
    protected $attributes = [
        'contains_phi' => true,
    ];

    /**
     * Get the attachment types allowed for this model.
     *
     * @return array
     */
    public static function getAttachmentTypes(): array
    {
        return [
            'image',
            'pdf',
            'lab_result',
            'radiology_image',
            'audio',
            'video',
            'other',
        ];
    }

    /**
     * Get the message that owns this attachment.
     *
     * @return BelongsTo
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * Generate a human-readable file size.
     *
     * @return string
     */
    public function getFormattedFileSizeAttribute(): string
    {
        $bytes = $this->file_size_bytes;
        
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        
        return $bytes . ' bytes';
    }

    /**
     * Check if the attachment is an image.
     *
     * @return bool
     */
    public function isImage(): bool
    {
        return $this->attachment_type === 'image';
    }

    /**
     * Check if the attachment is a document.
     *
     * @return bool
     */
    public function isDocument(): bool
    {
        return in_array($this->attachment_type, ['pdf', 'lab_result']);
    }

    /**
     * Check if the attachment contains PHI.
     *
     * @return bool
     */
    public function hasProtectedHealthInfo(): bool
    {
        return $this->contains_phi;
    }
}