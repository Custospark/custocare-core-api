<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class HubCommunityPost extends Model
{
    public const CHANNEL_DISCUSSION = 'discussion';

    public const CHANNEL_FEATURE_IDEA = 'feature_idea';

    public const CHANNEL_PRODUCT_UPDATE = 'product_update';

    protected $fillable = [
        'uuid',
        'user_id',
        'channel',
        'title',
        'body',
        'comments_count',
    ];

    protected function casts(): array
    {
        return [
            'comments_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (HubCommunityPost $row): void {
            if (empty($row->uuid)) {
                $row->uuid = (string) Str::uuid();
            }
        });
    }

    /** @return array<int, string> */
    public static function allowedChannels(): array
    {
        return [
            self::CHANNEL_DISCUSSION,
            self::CHANNEL_FEATURE_IDEA,
            self::CHANNEL_PRODUCT_UPDATE,
        ];
    }

    /**
     * Channels authenticated hub users may create via the public community API.
     * Product updates are authored only via Platform Admin.
     *
     * @return array<int, string>
     */
    public static function allowedUserComposeChannels(): array
    {
        return [
            self::CHANNEL_DISCUSSION,
            self::CHANNEL_FEATURE_IDEA,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(HubCommunityComment::class, 'hub_community_post_id')
            ->orderBy('created_at');
    }
}
