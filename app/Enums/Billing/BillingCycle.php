<?php

declare(strict_types=1);

namespace App\Enums\Billing;

/** Supported billing cycles. */
enum BillingCycle: string
{
    case MONTHLY = 'monthly';
    case YEARLY  = 'yearly';

    public function monthsToAdd(): int
    {
        return match($this) {
            self::MONTHLY => 1,
            self::YEARLY  => 12,
        };
    }
}
