<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Billing;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\CancelSubscriptionRequest;
use App\Http\Requests\Billing\PaymentQuoteRequest;
use App\Http\Requests\Billing\ScheduleSubscriptionChangeRequest;
use App\Http\Requests\Billing\StoreSubscriptionRequest;
use App\Http\Requests\Billing\UpgradeNowRequest;
use App\Http\Resources\Billing\SubscriptionResource;
use App\Models\Facility;
use App\Models\Plan;
use App\Repositories\Billing\Contracts\SubscriptionRepositoryInterface;
use App\Services\Billing\Contracts\SubscriptionPaymentQuoteServiceInterface;
use App\Services\Billing\Contracts\SubscriptionScheduledChangeServiceInterface;
use App\Services\Billing\Contracts\SubscriptionServiceInterface;
use Illuminate\Http\JsonResponse;

/**
 * Facility-facing subscription management.
 * Facilities create and cancel their subscriptions here.
 *
 * All routes are scoped to a {facility} route parameter.
 */
class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionServiceInterface $subscriptionService,
        private readonly SubscriptionScheduledChangeServiceInterface $scheduledChangeService,
        private readonly SubscriptionPaymentQuoteServiceInterface $quoteService,
        private readonly SubscriptionRepositoryInterface $subscriptionRepo,
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
                'success' => true,
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
                options: array_filter([
                    'notes'         => $request->input('notes'),
                    'billing_cycle' => $request->input('billing_cycle'),
                ], fn($v) => $v !== null),
            );

            $wasUpdated = ($subscription->_action ?? null) === 'updated';
            $statusLabel = $subscription->status->value ?? 'trial';
            $actionLabel = $wasUpdated ? 'updated' : 'created';
            $statusHint = match ($statusLabel) {
                'past_due' => ' Payment is overdue.',
                'trial'    => ' Submit a payment to activate.',
                default    => '',
            };

            return response()->json([
                'success' => true,
                'message' => "Subscription {$actionLabel}. Status: {$statusLabel}.{$statusHint}",
                'data'    => new SubscriptionResource($subscription->load('plan')),
            ], $wasUpdated ? 200 : 201);

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
    public function cancel(CancelSubscriptionRequest $request, Facility $facility): JsonResponse
    {
        $subscription = $this->subscriptionService->getSubscriptionForFacility($facility->id);

        if (! $subscription || $subscription->isCancelled()) {
            return response()->json([
                'success' => false,
                'message' => 'No active subscription found to cancel.',
                'data'    => null,
            ], 404);
        }

        $mode = $request->input('mode', 'at_period_end');

        // Facility-facing: only at_period_end unless platform admin (not exposed here)
        if ($mode === 'immediate') {
            return response()->json([
                'success' => false,
                'message' => 'Immediate cancellation is only available to platform administrators.',
                'data'    => null,
            ], 403);
        }

        try {
            $cancelled = $this->subscriptionService->cancelSubscription(
                $subscription,
                $request->input('reason'),
                'at_period_end',
            );

            return response()->json([
                'success' => true,
                'message' => 'Cancellation scheduled. You will retain access until the end of your billing period.',
                'data'    => new SubscriptionResource($cancelled->load('plan')),
            ]);
        } catch (\DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data'    => null,
            ], $e->getCode() >= 400 ? $e->getCode() : 422);
        }
    }

    /**
     * POST /api/facilities/{facility}/subscription/schedule-change
     */
    public function scheduleChange(
        ScheduleSubscriptionChangeRequest $request,
        Facility $facility,
    ): JsonResponse {
        $subscription = $this->subscriptionService->getSubscriptionForFacility($facility->id);

        if (! $subscription || ! $subscription->hasAccess()) {
            return response()->json([
                'success' => false,
                'message' => 'No active subscription found to change.',
                'data'    => null,
            ], 404);
        }

        try {
            $targetPlan = Plan::findOrFail($request->integer('plan_id'));

            $change = $this->scheduledChangeService->schedulePlanChange(
                $subscription,
                $targetPlan,
                $request->string('change_type')->toString(),
                billingCycle: $request->input('billing_cycle'),
            );

            $fresh = $this->subscriptionService->getSubscriptionForFacility($facility->id);

            return response()->json([
                'success' => true,
                'message' => "Plan change to {$targetPlan->name} scheduled for your next billing cycle.",
                'data'    => new SubscriptionResource($fresh?->load('plan')),
                'scheduled_change' => [
                    'id'           => $change->id,
                    'change_type'  => $change->change_type,
                    'effective_at' => $change->effective_at?->toISOString(),
                    'to_plan_id'   => $change->to_plan_id,
                ],
            ]);
        } catch (\DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data'    => null,
            ], $e->getCode() >= 400 ? $e->getCode() : 422);
        }
    }

    /**
     * POST /api/facilities/{facility}/subscription/upgrade-now
     * Prepares proration quote; plan upgrades after payment approval.
     */
    public function upgradeNow(UpgradeNowRequest $request, Facility $facility): JsonResponse
    {
        $subscription = $this->subscriptionService->getSubscriptionForFacility($facility->id);

        if (! $subscription || ! $subscription->hasAccess()) {
            return response()->json([
                'success' => false,
                'message' => 'No active subscription found to upgrade.',
                'data'    => null,
            ], 404);
        }

        try {
            $targetPlan = Plan::findOrFail($request->integer('plan_id'));

            $quote = $this->quoteService->buildQuote($subscription, $targetPlan, 'upgrade_now', $request->input('billing_cycle'));

            $pendingBillingCycle = $request->input('billing_cycle');
            $this->subscriptionRepo->update($subscription, [
                'metadata' => array_merge($subscription->metadata ?? [], array_filter([
                    'pending_upgrade_plan_id' => $targetPlan->id,
                    'pending_upgrade_billing_cycle' => $pendingBillingCycle,
                    'latest_quote_intent'     => 'upgrade_now',
                ], fn($v) => $v !== null)),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Upgrade quote generated. Submit payment to complete the upgrade.',
                'data'    => new SubscriptionResource($subscription->fresh()->load('plan')),
                'quote'   => $quote,
            ]);
        } catch (\DomainException|\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data'    => null,
            ], $e instanceof \DomainException && $e->getCode() >= 400 ? $e->getCode() : 422);
        }
    }

    /**
     * DELETE /api/facilities/{facility}/subscription/scheduled-change
     */
    public function cancelScheduledChange(Facility $facility): JsonResponse
    {
        $subscription = $this->subscriptionService->getSubscriptionForFacility($facility->id);

        if (! $subscription) {
            return response()->json([
                'success' => false,
                'message' => 'No subscription found.',
                'data'    => null,
            ], 404);
        }

        $this->scheduledChangeService->cancelPendingChange($subscription);

        $fresh = $this->subscriptionService->getSubscriptionForFacility($facility->id);

        return response()->json([
            'success' => true,
            'message' => 'Pending plan change cancelled.',
            'data'    => new SubscriptionResource($fresh?->load('plan')),
        ]);
    }

    /**
     * GET /api/facilities/{facility}/subscription/payment-quote
     */
    public function paymentQuote(PaymentQuoteRequest $request, Facility $facility): JsonResponse
    {
        $subscription = $this->subscriptionService->getSubscriptionForFacility($facility->id);

        if (! $subscription) {
            return response()->json([
                'success' => false,
                'message' => 'No subscription found for this facility.',
                'data'    => null,
            ], 404);
        }

        try {
            $targetPlan = $request->filled('plan_id')
                ? Plan::findOrFail($request->integer('plan_id'))
                : null;

            $intent = $request->string('intent')->toString();
            $quote = $this->quoteService->buildQuote($subscription, $targetPlan, $intent, $request->input('billing_cycle'));

            return response()->json([
                'success' => true,
                'message' => 'Payment quote generated.',
                'data'    => $quote,
            ]);
        } catch (\DomainException|\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data'    => null,
            ], 422);
        }
    }
}
