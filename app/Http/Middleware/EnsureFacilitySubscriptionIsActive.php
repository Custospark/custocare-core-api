<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Billing\SubscriptionStatus;
use App\Models\Facility;
use App\Services\Billing\Contracts\SubscriptionServiceInterface;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnsureFacilitySubscriptionIsActive
 *
 * Blocks API access when the resolved facility lacks an accessible subscription
 * (active, valid trial, or within grace period). Returns a specific error
 * message based on the subscription's actual status.
 *
 * Facility resolution priority:
 *  1. Route model-bound {facility} parameter
 *  2. Route integer {facility_id} parameter
 *  3. X-Active-Facility-Id header
 *  4. X-Facility-Id header (fallback)
 *
 * Usage in routes:
 *   Route::middleware(['auth:sanctum', 'facility.subscription.active'])
 */
class EnsureFacilitySubscriptionIsActive
{
    /** Routes that bypass subscription check — auth, plan browsing, subscription, and payment submission. */
    private const EXCEPT_PATTERNS = [
        'api/auth/*',
        'api/billing/plans*',
        'api/facilities/*/subscription*',
        'api/facilities/*/payments*',
        'api/facilities/*/usage',
        'api/facilities/*/assignable-modules',
    ];

    public function __construct(
        private readonly SubscriptionServiceInterface $subscriptionService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Allow subscription-management and billing routes through
        foreach (self::EXCEPT_PATTERNS as $pattern) {
            if ($request->is($pattern)) {
                return $next($request);
            }
        }

        $facility = $this->resolveFacility($request);

        // Silently pass through when no facility context — global middleware.
        if (! $facility) {
            return $next($request);
        }

        $subscription = $this->subscriptionService->getSubscriptionForFacility($facility->id);

        $this->attachSubscriptionDates($request, $subscription);

        if (! $subscription) {
            return $this->deny(
                'This facility does not have an active subscription. Please subscribe to a plan or contact Custocare support.',
                ['subscription' => ['No subscription found for this facility.']],
                402,
                'subscribe',
            );
        }

        return match ($subscription->status) {
            SubscriptionStatus::ACTIVE => $subscription->hasAccess()
                ? $this->allow($request, $next, $subscription, $facility)
                : $this->deny(
                    'Your subscription has ended. Please renew to continue using Custocare.',
                    ['subscription' => ['Access ended on ' . ($subscription->ends_at?->toDateString() ?? 'N/A') . '.']],
                    402,
                    'subscribe',
                ),

            SubscriptionStatus::TRIAL => $subscription->trial_ends_at?->isFuture()
                ? $this->allow($request, $next, $subscription, $facility)
                : $this->deny(
                    'Your trial period has ended. Please complete payment to continue using Custocare.',
                    ['subscription' => ['Trial period has ended on ' . ($subscription->trial_ends_at?->toDateString() ?? 'N/A') . '.']],
                    402,
                    'subscribe',
                ),

            SubscriptionStatus::PAST_DUE => $subscription->grace_period_ends_at?->isFuture()
                ? $this->allow($request, $next, $subscription, $facility)
                : $this->deny(
                    'Your subscription is past due and the grace period has ended. Please make a payment to restore access.',
                    ['subscription' => ['Grace period ended on ' . ($subscription->grace_period_ends_at?->toDateString() ?? 'N/A') . '.']],
                    402,
                    'subscribe',
                ),

            SubscriptionStatus::SUSPENDED => $this->deny(
                'Your subscription has been suspended. Please contact Custocare support to restore access.',
                ['subscription' => ['Subscription is suspended.']],
                403,
                'contact_support',
            ),

            SubscriptionStatus::CANCELLED => $this->deny(
                'Your subscription has been cancelled. Please subscribe to a plan to continue using Custocare.',
                ['subscription' => ['Subscription is cancelled.']],
                402,
                'subscribe',
            ),

            default => $this->deny(
                'Facility subscription is not active. Please contact Custocare support.',
                ['subscription' => ['Subscription status: ' . ($subscription->status->value ?? 'unknown') . '.']],
                402,
                'subscribe',
            ),
        };
    }

    private function allow(
        Request $request,
        Closure $next,
        $subscription,
        Facility $facility,
    ): Response {
        $request->attributes->set('active_subscription', $subscription);
        $request->attributes->set('subscription_facility', $facility);
        return $next($request);
    }

    private function attachSubscriptionDates(Request $request, $subscription): void
    {
        if (! $subscription) {
            return;
        }
        $request->attributes->set('active_subscription', $subscription);
        $request->attributes->set('subscription_facility', $this->resolveFacility($request));
    }

    private function deny(
        string $message,
        array $errors,
        int $statusCode,
        ?string $action = null,
    ): Response {
        $request = request();
        $subscription = $request->attributes->get('active_subscription');

        $payload = [
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
            'data'    => null,
        ];

        if ($subscription) {
            $payload['subscription'] = [
                'status'             => $subscription->status->value,
                'has_access'         => $subscription->hasAccess(),
                'trial_ends_at'      => $subscription->trial_ends_at?->toISOString(),
                'ends_at'            => $subscription->ends_at?->toISOString(),
                'next_billing_date'  => $subscription->next_billing_date?->toISOString(),
                'grace_period_ends_at' => $subscription->grace_period_ends_at?->toISOString(),
            ];
        }

        if ($action) {
            $payload['action'] = $action;
        }
        return response()->json($payload, $statusCode);
    }

    private function resolveFacility(Request $request): ?Facility
    {
        $facility = $request->route('facility');
        if ($facility instanceof Facility) {
            return $facility;
        }

        if (is_numeric($facility)) {
            return Facility::find((int) $facility);
        }

        $activeFacilityId = $request->header('X-Active-Facility-Id');
        if (is_numeric($activeFacilityId)) {
            return Facility::find((int) $activeFacilityId);
        }

        $facilityId = $request->header('X-Facility-Id');
        if (is_numeric($facilityId)) {
            return Facility::find((int) $facilityId);
        }

        return null;
    }
}
