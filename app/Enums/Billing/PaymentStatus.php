<?php

declare(strict_types=1);

namespace App\Enums\Billing;

/**
 * Payment record statuses (gateway vocabulary, shared with Custosell).
 *
 * pending   → Payment recorded; completes automatically on gateway confirmation.
 * completed → Money confirmed; triggers subscription activation.
 * failed    → Gateway rejected the payment or verification failed.
 * refunded  → Money returned after completion.
 */
enum PaymentStatus: string
{
    case PENDING   = 'pending';
    case COMPLETED = 'completed';
    case FAILED    = 'failed';
    case REFUNDED  = 'refunded';

    public function label(): string
    {
        return match($this) {
            self::PENDING   => 'Pending Payment',
            self::COMPLETED => 'Completed',
            self::FAILED    => 'Failed',
            self::REFUNDED  => 'Refunded',
        };
    }
}
