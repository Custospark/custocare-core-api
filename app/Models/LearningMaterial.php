<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LearningMaterial extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'title',
        'description',
        'video_url',
        'thumbnail_url',
        'thumbnail_path',
        'banner_image_url',
        'category',
        'sort_order',
        'is_published',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'sort_order'   => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (LearningMaterial $material): void {
            if (empty($material->uuid)) {
                $material->uuid = (string) Str::uuid();
            }
        });

        static::forceDeleted(function (LearningMaterial $material): void {
            if ($material->thumbnail_path) {
                Storage::disk('public')->delete($material->thumbnail_path);
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return array<int, string> */
    public static function allowedCategories(): array
    {
        return [
            'watch-tutorials',
            'start-training',
            'getting-started',
            'track-progress',
        ];
    }
}
