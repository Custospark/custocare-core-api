<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\StoreSubscriptionRequest;
use App\Http\Resources\Billing\SubscriptionResource;
use App\Models\Facility;
use App\Models\Plan;
use App\Services\Billing\Contracts\SubscriptionServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Facility-facing subscription management.
 * Facilities create and cancel their subscriptions here.
 *
 * All routes are scoped to a {facility} route parameter.
 */
class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionServiceInterface $subscriptionService
    ) {}

    /**
     * GET /api/facilities/{facility}/subscription
     * Retrieve the current subscription for a facility.
     */
    public function show(Facility $facility): JsonResponse
    {
        $subscription = $this->subscriptionService->getSubscriptionForFacility($facility->id);

        if (! $subscription) {
            return response()->json([
                'success' => false,
                'message' => 'No subscription found for this facility.',
                'data'    => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Subscription retrieved successfully.',
            'data'    => new SubscriptionResource($subscription->load(['plan', 'payments'])),
        ]);
    }

    /**
     * POST /api/facilities/{facility}/subscription
     *
     * Create a new subscription for the facility.
     * Subscription starts in "trial" status.
     * A payment must be submitted and admin-approved to go "active".
     */
    public function store(
        StoreSubscriptionRequest $request,
        Facility $facility
    ): JsonResponse {
        try {
            $plan = Plan::findOrFail($request->integer('plan_id'));

            if (! $plan->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'The selected plan is not currently available.',
                    'data'    => null,
                ], 422);
            }

            $subscription = $this->subscriptionService->createSubscription(
                facility: $facility,
                plan: $plan,
                options: ['notes' => $request->input('notes')]
            );

            return response()->json([
                'success' => true,
                'message' => 'Subscription created. Status: trial. Submit a payment to activate.',
                'data'    => new SubscriptionResource($subscription->load('plan')),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data'    => null,
            ], $e->getCode() >= 400 ? $e->getCode() : 422);
        }
    }

    /**
     * DELETE /api/facilities/{facility}/subscription
     * Cancel the facility's current subscription.
     */
    public function cancel(Request $request, Facility $facility): JsonResponse
    {
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $subscription = $this->subscriptionService->getSubscriptionForFacility($facility->id);

        if (! $subscription || $subscription->isCancelled()) {
            return response()->json([
                'success' => false,
                'message' => 'No active subscription found to cancel.',
                'data'    => null,
            ], 404);
        }

        $cancelled = $this->subscriptionService->cancelSubscription(
            $subscription,
            $request->input('reason')
        );

        return response()->json([
            'success' => true,
            'message' => 'Subscription cancelled successfully.',
            'data'    => new SubscriptionResource($cancelled),
        ]);
    }
}
