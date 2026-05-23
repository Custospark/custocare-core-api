<?php

declare(strict_types=1);

namespace App\Enums\Billing;

enum InvoiceType: string
{
    case SUBSCRIPTION = 'subscription';
    case RENEWAL = 'renewal';
    case ONBOARDING = 'onboarding';
    case ADJUSTMENT = 'adjustment';

    public function label(): string
    {
        return match($this) {
            self::SUBSCRIPTION => 'Subscription',
            self::RENEWAL      => 'Renewal',
            self::ONBOARDING   => 'Onboarding Fee',
            self::ADJUSTMENT   => 'Adjustment',
        };
    }
}
