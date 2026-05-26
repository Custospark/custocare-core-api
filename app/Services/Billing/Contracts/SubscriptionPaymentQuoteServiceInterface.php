<?php

declare(strict_types=1);

namespace App\Services\Billing\Contracts;

use App\Models\Plan;
use App\Models\Subscription;

interface SubscriptionPaymentQuoteServiceInterface
{
    /**
     * @return array<string, mixed>
     */
    public function buildQuote(Subscription $subscription, ?Plan $targetPlan, string $intent): array;

    public function validatePaymentAmount(Subscription $subscription, float $amount, string $intent, ?int $targetPlanId = null): void;
}
