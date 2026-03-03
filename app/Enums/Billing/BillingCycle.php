<?php

declare(strict_types=1);

namespace App\Enums\Billing;

/** Supported billing cycles. Yearly can be added later. */
enum BillingCycle: string
{
    case MONTHLY = 'monthly';

    public function monthsToAdd(): int
    {
        return match($this) {
            self::MONTHLY => 1,
        };
    }
}
