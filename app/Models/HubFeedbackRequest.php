<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class HubFeedbackRequest extends Model
{
    public const CATEGORY_FEEDBACK = 'feedback';

    public const CATEGORY_FEATURE_REQUEST = 'feature_request';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_ACKNOWLEDGED = 'acknowledged';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'uuid',
        'user_id',
        'category',
        'subject',
        'body',
        'status',
        'include_in_roadmap',
        'admin_internal_notes',
        'staff_reply',
    ];

    protected function casts(): array
    {
        return [
            'include_in_roadmap' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (HubFeedbackRequest $row): void {
            if (empty($row->uuid)) {
                $row->uuid = (string) Str::uuid();
            }
        });
    }

    /** @return array<int, string> */
    public static function allowedCategories(): array
    {
        return [self::CATEGORY_FEEDBACK, self::CATEGORY_FEATURE_REQUEST];
    }

    /** @return array<int, string> */
    public static function allowedStatuses(): array
    {
        return [
            self::STATUS_SUBMITTED,
            self::STATUS_ACKNOWLEDGED,
            self::STATUS_IN_PROGRESS,
            self::STATUS_RESOLVED,
            self::STATUS_CLOSED,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(HubFeedbackVote::class);
    }

    public function isOpenForRoadmapVoting(): bool
    {
        if ($this->category !== self::CATEGORY_FEATURE_REQUEST || ! $this->include_in_roadmap) {
            return false;
        }

        return in_array($this->status, [
            self::STATUS_SUBMITTED,
            self::STATUS_ACKNOWLEDGED,
            self::STATUS_IN_PROGRESS,
        ], true);
    }
}
