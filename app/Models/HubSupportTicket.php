<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class HubSupportTicket extends Model
{
    public const CATEGORY_ACCOUNT_ISSUE = 'account_issue';
    public const CATEGORY_FACILITY_ISSUE = 'facility_issue';
    public const CATEGORY_GENERAL = 'general';

    public const PRIORITY_LOW = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH = 'high';

    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_ACKNOWLEDGED = 'acknowledged';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'uuid',
        'user_id',
        'category',
        'priority',
        'subject',
        'body',
        'status',
        'staff_reply',
        'admin_internal_notes',
    ];

    protected function casts(): array
    {
        return [];
    }

    protected static function booted(): void
    {
        static::creating(function (HubSupportTicket $row): void {
            if (empty($row->uuid)) {
                $row->uuid = (string) Str::uuid();
            }
        });
    }

    /** @return array<int, string> */
    public static function allowedCategories(): array
    {
        return [
            self::CATEGORY_ACCOUNT_ISSUE,
            self::CATEGORY_FACILITY_ISSUE,
            self::CATEGORY_GENERAL,
        ];
    }

    /** @return array<int, string> */
    public static function allowedPriorities(): array
    {
        return [self::PRIORITY_LOW, self::PRIORITY_MEDIUM, self::PRIORITY_HIGH];
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

    public function updates(): HasMany
    {
        return $this->hasMany(HubSupportTicketUpdate::class, 'hub_support_ticket_id');
    }
}

