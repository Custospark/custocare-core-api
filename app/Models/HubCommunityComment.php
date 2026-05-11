<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class HubCommunityComment extends Model
{
    protected $fillable = [
        'uuid',
        'hub_community_post_id',
        'user_id',
        'body',
    ];

    protected static function booted(): void
    {
        static::creating(function (HubCommunityComment $row): void {
            if (empty($row->uuid)) {
                $row->uuid = (string) Str::uuid();
            }
        });
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(HubCommunityPost::class, 'hub_community_post_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
