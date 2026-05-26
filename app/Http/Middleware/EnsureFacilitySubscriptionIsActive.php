<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Facility;
use App\Services\Billing\Contracts\SubscriptionServiceInterface;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnsureFacilitySubscriptionIsActive
 *
 * Blocks API access if the resolved facility does not have an accessible
 * subscription (active, valid trial, or within grace period).
 *
 * Facility resolution priority:
 *  1. Route model-bound {facility} parameter
 *  2. Route integer {facility_id} parameter
 *  3. X-Facility-ID request header
 *
 * Usage in routes:
 *   Route::middleware(['auth:sanctum', 'facility.subscription.active'])
 */
class EnsureFacilitySubscriptionIsActive
{
    public function __construct(
        private readonly SubscriptionServiceInterface $subscriptionService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $facility = $this->resolveFacility($request);

        if (! $facility) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to determine facility context.',
                'errors'  => ['facility' => ['No facility identified in this request.']],
                'data'    => null,
            ], 400);
        }

        // Applies pending scheduled changes before access check
        $subscription = $this->subscriptionService->getSubscriptionForFacility($facility->id);

        if (! $subscription || ! $subscription->hasAccess()) {
            return response()->json([
                'success' => false,
                'message' => 'Facility subscription is not active.',
                'errors'  => [
                    'subscription' => [
                        'This facility does not have an active subscription. '
                        . 'Please subscribe to a plan or contact Custocare support.',
                    ],
                ],
                'data'    => null,
                'action'  => 'subscribe', // hint for the client to show upgrade UI
            ], 402); // 402 Payment Required
        }

        // Share subscription with downstream controllers/services via request
        $request->attributes->set('active_subscription', $subscription);
        $request->attributes->set('subscription_facility', $facility);

        return $next($request);
    }

   private function resolveFacility(Request $request): ?Facility
    {
        // 1. Route model binding
        $facility = $request->route('facility');
        if ($facility instanceof Facility) {
            return $facility;
        }

        // 2. Integer route parameter
        if (is_numeric($facility)) {
            return Facility::find((int) $facility);
        }

        // 3. X-Active-Facility-Id header (primary)
        $activeFacilityId = $request->header('X-Active-Facility-Id');
        if (is_numeric($activeFacilityId)) {
            return Facility::find((int) $activeFacilityId);
        }

        // 4. X-Facility-Id header (fallback)
        $facilityId = $request->header('X-Facility-Id');
        if (is_numeric($facilityId)) {
            return Facility::find((int) $facilityId);
        }

        return null;
    }
}
