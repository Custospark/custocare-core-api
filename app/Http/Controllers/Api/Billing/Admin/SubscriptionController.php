<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Billing\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\Admin\ManageSubscriptionRequest;
use App\Http\Resources\Billing\SubscriptionResource;
use App\Models\Subscription;
use App\Services\Billing\Contracts\SubscriptionServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Admin subscription management.
 * Admins can list, view, manually activate, suspend, or cancel subscriptions.
 */
class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionServiceInterface $subscriptionService
    ) {}

    /**
     * GET /api/admin/billing/subscriptions
     * List all subscriptions with optional filters.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only(['status', 'facility_id', 'plan_id']);
        $perPage = $request->integer('per_page', 15);

        $subscriptions = $this->subscriptionService->getAllSubscriptions($filters, $perPage);

        return SubscriptionResource::collection($subscriptions);
    }

    /**
     * GET /api/admin/billing/subscriptions/{subscription}
     */
    public function show(Subscription $subscription): JsonResponse
    {
        $subscription->load(['facility', 'plan', 'payments', 'approvedBy']);

        return response()->json([
            'success' => true,
            'message' => 'Subscription retrieved.',
            'data'    => new SubscriptionResource($subscription),
        ]);
    }

    /**
     * POST /api/admin/billing/subscriptions/{subscription}/activate
     *
     * Manually activate a subscription without requiring a payment record.
     * Use for edge cases (e.g. cash payment already verified offline).
     */
    public function activate(
        ManageSubscriptionRequest $request,
        Subscription $subscription
    ): JsonResponse {
        try {
                /**
         * Authorization Architecture Overview:
         *
         * - Platform-level authority → Enforced via Users table (Spatie roles).
         * - Facility-level authority → Managed through the Staff model.
         * - Subscription enforcement → Determined by resolved Facility context.
         *
         * This separation ensures clear responsibility boundaries in a multi-tenant SaaS architecture.
         */
            $adminStaff = $request->attributes->get('admin_user');

            // Create a synthetic approved payment record for audit trail
            $payment = $subscription->payments()->create([
                'facility_id'  => $subscription->facility_id,
                'amount'       => $subscription->plan->priceIn('UGX'),
                'currency'     => 'UGX',
                'method'       => 'cash',
                'payment_type' => 'subscription',
                'status'       => 'approved',
                'paid_at'      => now(),
                'approved_at'  => now(),
                'approved_by_staff_id' => $adminStaff->id,
                'receipt_notes' => $request->input('reason', 'Manually activated by admin.'),
            ]);

            $updated = $this->subscriptionService->activateSubscription(
                $subscription, $payment, $adminStaff
            );

            return response()->json([
                'success' => true,
                'message' => 'Subscription manually activated.',
                'data'    => new SubscriptionResource($updated->load(['plan', 'facility'])),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data'    => null,
            ], 422);
        }
    }

    /**
     * POST /api/admin/billing/subscriptions/{subscription}/suspend
     * Manually suspend a facility's subscription.
     */
    public function suspend(
        ManageSubscriptionRequest $request,
        Subscription $subscription
    ): JsonResponse {
        $updated = $this->subscriptionService->suspendSubscription($subscription);

        return response()->json([
            'success' => true,
            'message' => 'Subscription suspended.',
            'data'    => new SubscriptionResource($updated),
        ]);
    }

    /**
     * POST /api/admin/billing/subscriptions/{subscription}/cancel
     * Administratively cancel a subscription.
     */
    public function cancel(
        ManageSubscriptionRequest $request,
        Subscription $subscription
    ): JsonResponse {
        $updated = $this->subscriptionService->cancelSubscription(
            $subscription,
            $request->input('reason')
        );

        return response()->json([
            'success' => true,
            'message' => 'Subscription cancelled.',
            'data'    => new SubscriptionResource($updated),
        ]);
    }
}
