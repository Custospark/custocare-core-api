<?php

declare(strict_types=1);

namespace App\Enums\Billing;

/**
 * Payment record statuses.
 *
 * pending  → Payment recorded; completes automatically on gateway confirmation.
 * approved → Payment confirmed; triggers subscription activation.
 * rejected → Payment rejected or failed at the gateway.
 * refunded → Payment was refunded.
 */
enum PaymentStatus: string
{
    case PENDING  = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case REFUNDED = 'refunded';

    public function label(): string
    {
        return match($this) {
            self::PENDING  => 'Pending Payment',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::REFUNDED => 'Refunded',
        };
    }
}
