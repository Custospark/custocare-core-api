<?php

declare(strict_types=1);

namespace App\Enums\Billing;

enum SubscriptionScheduledChangeType: string
{
    case UPGRADE     = 'upgrade';
    case DOWNGRADE   = 'downgrade';
    case CANCEL      = 'cancel';
    case PLAN_CHANGE = 'plan_change';

    public function label(): string
    {
        return match ($this) {
            self::UPGRADE     => 'Upgrade',
            self::DOWNGRADE   => 'Downgrade',
            self::CANCEL      => 'Cancellation',
            self::PLAN_CHANGE => 'Plan change',
        };
    }
}
