<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Support\Carbon;

final class SubscriptionProrationCalculator
{
    /**
     * @return array{
     *   proration_due: float,
     *   days_remaining: int,
     *   days_in_period: int,
     *   credit_unused: float,
     *   charge_new: float,
     *   old_price_usd: float,
     *   new_price_usd: float
     * }
     */
    public static function calculate(Subscription $subscription, Plan $currentPlan, Plan $targetPlan): array
    {
        $now = Carbon::now();
        $endsAt = $subscription->ends_at ? $subscription->ends_at->copy() : $now->copy()->addMonth();
        $startsAt = $subscription->starts_at ? $subscription->starts_at->copy() : $now->copy()->subMonth();

        $daysInPeriod = max(1, (int) $startsAt->diffInDays($endsAt));
        $daysRemaining = max(0, (int) $now->diffInDays($endsAt, false));

        $oldPrice = (float) $currentPlan->price_usd;
        $newPrice = (float) $targetPlan->price_usd;

        $creditUnused = round($oldPrice * ($daysRemaining / $daysInPeriod), 2);
        $chargeNew = round($newPrice * ($daysRemaining / $daysInPeriod), 2);
        $prorationDue = round(max(0, $chargeNew - $creditUnused), 2);

        return [
            'proration_due'   => $prorationDue,
            'days_remaining'  => $daysRemaining,
            'days_in_period'  => $daysInPeriod,
            'credit_unused'   => $creditUnused,
            'charge_new'      => $chargeNew,
            'old_price_usd'   => $oldPrice,
            'new_price_usd'   => $newPrice,
        ];
    }
}
