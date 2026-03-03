<?php

declare(strict_types=1);

namespace App\Enums\Billing;

/**
 * Subscription lifecycle statuses.
 *
 * trial      → Facility signed up; awaiting first payment confirmation.
 * active     → Payment confirmed by admin; facility has full access.
 * past_due   → Billing date passed; within 7-day grace window.
 * suspended  → Grace period expired; API access blocked.
 * cancelled  → Facility voluntarily cancelled or admin-terminated.
 */
enum SubscriptionStatus: string
{
    case TRIAL     = 'trial';
    case ACTIVE    = 'active';
    case PAST_DUE  = 'past_due';
    case SUSPENDED = 'suspended';
    case CANCELLED = 'cancelled';

    /** Statuses that grant API access. */
    public static function accessGranted(): array
    {
        return [
            self::TRIAL->value,
            self::ACTIVE->value,
            self::PAST_DUE->value,   // within grace period
        ];
    }

    public function label(): string
    {
        return match($this) {
            self::TRIAL     => 'Trial',
            self::ACTIVE    => 'Active',
            self::PAST_DUE  => 'Past Due',
            self::SUSPENDED => 'Suspended',
            self::CANCELLED => 'Cancelled',
        };
    }
}
