<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Billing\SubscriptionStatus;
use App\Models\Facility;
use App\Services\Billing\Contracts\SubscriptionServiceInterface;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
    /** Routes that bypass subscription check — plan browsing, subscription, and payment submission. */
    private const EXCEPT_PATTERNS = [
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
                Log::debug('[SubscriptionMiddleware] Excepted path — passing through', [
                    'path' => $request->path(),
                    'pattern' => $pattern,
                ]);
                return $next($request);
            }
        }

        $facility = $this->resolveFacility($request);

        // Silently pass through when no facility context — global middleware.
        // Subscription checks only apply to facility-scoped requests.
        if (! $facility) {
            Log::debug('[SubscriptionMiddleware] No facility resolved — passing through', [
                'path' => $request->path(),
                'method' => $request->method(),
                'headers' => [
                    'x-active-facility-id' => $request->header('X-Active-Facility-Id'),
                    'x-facility-id' => $request->header('X-Facility-Id'),
                ],
            ]);
            return $next($request);
        }

        Log::debug('[SubscriptionMiddleware] Facility resolved', [
            'facility_id' => $facility->id,
            'facility_code' => $facility->facility_code,
            'path' => $request->path(),
        ]);

        $subscription = $this->subscriptionService->getSubscriptionForFacility($facility->id);

        Log::debug('[SubscriptionMiddleware] Subscription lookup', [
            'facility_id' => $facility->id,
            'has_subscription' => $subscription ? 'yes' : 'no',
            'subscription_id' => $subscription?->id,
            'status' => $subscription?->status?->value,
            'trial_ends_at' => $subscription?->trial_ends_at?->toISOString(),
            'trial_is_future' => $subscription?->trial_ends_at?->isFuture(),
        ]);

        if (! $subscription) {
            return $this->deny(
                'This facility does not have an active subscription. Please subscribe to a plan or contact Custocare support.',
                ['subscription' => ['No subscription found for this facility.']],
                402,
                'subscribe',
            );
        }

        return match ($subscription->status) {
            SubscriptionStatus::ACTIVE => $this->allow($request, $next, $subscription, $facility),

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
        Log::debug('[SubscriptionMiddleware] Allowing request', [
            'facility_id' => $facility->id,
            'subscription_status' => $subscription->status->value,
        ]);
        $request->attributes->set('active_subscription', $subscription);
        $request->attributes->set('subscription_facility', $facility);
        return $next($request);
    }

    private function deny(
        string $message,
        array $errors,
        int $statusCode,
        ?string $action = null,
    ): Response {
        Log::warning('[SubscriptionMiddleware] Blocking request', [
            'message' => $message,
            'status_code' => $statusCode,
            'action' => $action,
        ]);
        $payload = [
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
            'data'    => null,
        ];
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
