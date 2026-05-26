<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Billing\SubscriptionScheduledChangeStatus;
use App\Enums\Billing\SubscriptionScheduledChangeType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionScheduledChange extends Model
{
    protected $fillable = [
        'subscription_id',
        'facility_id',
        'change_type',
        'from_plan_id',
        'to_plan_id',
        'effective_at',
        'status',
        'proration_amount_usd',
        'requested_by_user_id',
        'metadata',
    ];

    protected $casts = [
        'change_type'          => SubscriptionScheduledChangeType::class,
        'status'               => SubscriptionScheduledChangeStatus::class,
        'effective_at'         => 'datetime',
        'proration_amount_usd' => 'float',
        'metadata'             => 'array',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function fromPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'from_plan_id');
    }

    public function toPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'to_plan_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === SubscriptionScheduledChangeStatus::PENDING;
    }
}
