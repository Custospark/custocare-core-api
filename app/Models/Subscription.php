<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Billing\BillingCycle;
use App\Enums\Billing\SubscriptionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Class Subscription
 *
 * Represents a facility's subscription to a Custocare plan.
 * ⚠ Subscriptions are scoped to FACILITIES — not users or apps.
 *
 * @property int                      $id
 * @property int                      $facility_id
 * @property int                      $plan_id
 * @property SubscriptionStatus       $status
 * @property Carbon|null              $trial_ends_at
 * @property Carbon                   $starts_at
 * @property Carbon                   $ends_at
 * @property Carbon                   $next_billing_date
 * @property Carbon|null              $grace_period_ends_at
 * @property Carbon|null              $suspended_at
 * @property Carbon|null              $cancelled_at
 * @property Carbon|null              $approved_at
 * @property int|null                 $approved_by_user_id
 * @property bool                     $onboarding_fee_paid
 * @property string|null              $notes
 * @property array|null               $metadata
 */
class Subscription extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'facility_id',
        'plan_id',
        'status',
        'trial_ends_at',
        'starts_at',
        'ends_at',
        'next_billing_date',
        'grace_period_ends_at',
        'suspended_at',
        'cancelled_at',
        'approved_at',
        'approved_by_user_id',
        'onboarding_fee_paid',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'status'               => SubscriptionStatus::class,
        'trial_ends_at'        => 'datetime',
        'starts_at'            => 'datetime',
        'ends_at'              => 'datetime',
        'next_billing_date'    => 'datetime',
        'grace_period_ends_at' => 'datetime',
        'suspended_at'         => 'datetime',
        'cancelled_at'         => 'datetime',
        'approved_at'          => 'datetime',
        'onboarding_fee_paid'  => 'boolean',
        'metadata'             => 'array',
        'deleted_at'           => 'datetime',
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────────────────────────────────

    /** The facility this subscription belongs to. */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /** The plan the facility is subscribed to. */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** Staff member who initially approved this subscription. */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    /** All payments recorded against this subscription. */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function scheduledChanges(): HasMany
    {
        return $this->hasMany(SubscriptionScheduledChange::class);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', SubscriptionStatus::ACTIVE);
    }

    public function scopeForFacility($query, int $facilityId)
    {
        return $query->where('facility_id', $facilityId);
    }

    public function scopePendingGracePeriod($query)
    {
        // Active subscriptions whose billing date has passed
        return $query->whereIn('status', [
            SubscriptionStatus::ACTIVE->value,
            SubscriptionStatus::TRIAL->value,
        ])->where('next_billing_date', '<', now());
    }

    public function scopeGraceExpired($query)
    {
        // Past-due subscriptions whose grace window has closed
        return $query->where('status', SubscriptionStatus::PAST_DUE->value)
                     ->where('grace_period_ends_at', '<', now());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Status helpers
    // ─────────────────────────────────────────────────────────────────────────

    /** True if the facility currently has platform access. */
    public function hasAccess(): bool
    {
        return match ($this->status) {
            SubscriptionStatus::ACTIVE => $this->hasActivePeriodAccess(),
            SubscriptionStatus::TRIAL   => $this->trial_ends_at?->isFuture() ?? true,
            SubscriptionStatus::PAST_DUE => $this->grace_period_ends_at?->isFuture() ?? false,
            default                     => false,
        };
    }

    /**
     * True when the facility may submit payment proof (activation, renewal, upgrade).
     * Broader than hasAccess() — e.g. expired trial or past-due after grace still need to pay.
     */
    public function canAcceptFacilityPayment(): bool
    {
        return match ($this->status) {
            SubscriptionStatus::TRIAL,
            SubscriptionStatus::ACTIVE,
            SubscriptionStatus::PAST_DUE,
            SubscriptionStatus::SUSPENDED => true,
            default => false,
        };
    }

    /** Active subscription, including cancel-at-period-end until ends_at. */
    private function hasActivePeriodAccess(): bool
    {
        if (! ($this->metadata['cancel_at_period_end'] ?? false)) {
            return true;
        }

        return $this->ends_at?->isFuture() ?? false;
    }

    public function isCancelAtPeriodEnd(): bool
    {
        return (bool) ($this->metadata['cancel_at_period_end'] ?? false)
            && $this->status === SubscriptionStatus::ACTIVE;
    }

    public function accessEndsAt(): ?Carbon
    {
        if ($this->isCancelAtPeriodEnd()) {
            return $this->ends_at;
        }

        return $this->metadata['access_ends_at'] ?? null
            ? Carbon::parse($this->metadata['access_ends_at'])
            : null;
    }

    public function isActive(): bool
    {
        return $this->status === SubscriptionStatus::ACTIVE;
    }

    public function isOnTrial(): bool
    {
        return $this->status === SubscriptionStatus::TRIAL
            && ($this->trial_ends_at?->isFuture() ?? true);
    }

    public function isPastDue(): bool
    {
        return $this->status === SubscriptionStatus::PAST_DUE;
    }

    public function isSuspended(): bool
    {
        return $this->status === SubscriptionStatus::SUSPENDED;
    }

    public function isCancelled(): bool
    {
        return $this->status === SubscriptionStatus::CANCELLED;
    }

    /** Days remaining in current billing period. */
    public function daysRemaining(): int
    {
        return max(0, (int) now()->diffInDays($this->ends_at, false));
    }
}
