<?php

declare(strict_types=1);

namespace App\Enums\Billing;

/**
 * Classifies what a payment covers.
 *
 * onboarding   → One-time setup fee; charged once per facility.
 * subscription → Initial subscription payment for a plan.
 * renewal      → Monthly renewal payment.
 */
enum PaymentType: string
{
    case ONBOARDING   = 'onboarding';
    case SUBSCRIPTION = 'subscription';
    case RENEWAL      = 'renewal';

    public function label(): string
    {
        return match($this) {
            self::ONBOARDING   => 'Onboarding Fee',
            self::SUBSCRIPTION => 'Subscription',
            self::RENEWAL      => 'Renewal',
        };
    }
}
