<?php

declare(strict_types=1);

namespace App\Enums\Billing;

enum SubscriptionScheduledChangeStatus: string
{
    case PENDING   = 'pending';
    case APPLIED   = 'applied';
    case CANCELLED = 'cancelled';
}
