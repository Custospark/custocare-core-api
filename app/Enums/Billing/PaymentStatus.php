<?php

declare(strict_types=1);

namespace App\Enums\Billing;

/**
 * Payment record statuses.
 *
 * pending  → Payment recorded by facility; awaiting admin confirmation.
 * approved → Admin confirmed receipt/evidence; triggers subscription activation.
 * rejected → Admin rejected payment evidence.
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
            self::PENDING  => 'Pending Review',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::REFUNDED => 'Refunded',
        };
    }
}
