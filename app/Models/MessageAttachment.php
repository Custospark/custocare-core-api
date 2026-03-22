<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class MessageAttachment extends Model
{
    protected $appends = ['download_url'];

    protected $fillable = [
        'message_id',
        'original_name',
        'stored_name',
        'disk',
        'path',
        'mime_type',
        'size_bytes',
        'size_formatted',
        'uploaded_by',
        'upload_status',
        'upload_progress',
    ];

    protected $casts = [
        'size_bytes'      => 'integer',
        'upload_progress' => 'integer',
    ];


    // ── Relationships ─────────────────────────────────────────────────────

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // ── Accessors ─────────────────────────────────────────────────────────

    /**
     * Get the download URL for this attachment.
     * This will be included in API responses automatically.
     */
   

    public function getDownloadUrlAttribute(): string
{
    return URL::temporarySignedRoute(
        'messages.attachments.download',  // route name (registered below)
        now()->addMinutes(60),
        ['attachmentId' => $this->id]
    );
}

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * Generate a temporary signed URL for downloading the file.
     * Falls back to a permanent URL for public disks.
     */   public function downloadUrl(): string
{
    return URL::temporarySignedRoute(
        'messages.attachments.download',  // route name (registered below)
        now()->addMinutes(60),
        ['attachmentId' => $this->id]
    );
}
    // public function downloadUrl(int $expirationMinutes = 60): string
    // {
    //     /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
    //     $disk = Storage::disk($this->disk);
        
    //     // Check if the disk supports temporary URLs
    //     if ($disk instanceof \Illuminate\Contracts\Filesystem\Filesystem && 
    //         method_exists($disk, 'temporaryUrl')) {
    //         try {
    //             /** @var string $url */
    //             $url = $disk->temporaryUrl($this->path, now()->addMinutes($expirationMinutes));
    //             return $url;
    //         } catch (\Exception $e) {
    //             // If temporary URL fails, fall back to permanent URL
    //             /** @var string $url */
    //             $url = $disk->url($this->path);
    //             return $url;
    //         }
    //     }
        
    //     // For disks without temporary URL support
    //     /** @var string $url */
    //     $url = $disk->url($this->path);
    //     return $url;
    // }

    /**
     * Format raw bytes into a human-readable string (KB / MB / GB).
     */
    public static function formatBytes(int $bytes): string
    {
        if ($bytes < 1_024) {
            return $bytes . ' B';
        }

        if ($bytes < 1_048_576) {
            return round($bytes / 1_024, 1) . ' KB';
        }

        if ($bytes < 1_073_741_824) {
            return round($bytes / 1_048_576, 1) . ' MB';
        }

        return round($bytes / 1_073_741_824, 2) . ' GB';
    }

    /**
     * Delete the physical file from storage when the model is deleted.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::deleting(function (self $attachment): void {
            Storage::disk($attachment->disk)->delete($attachment->path);
        });
    }
}