<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Billing\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\Admin\ApprovePaymentRequest;
use App\Http\Requests\Billing\Admin\RejectPaymentRequest;
use App\Http\Resources\Billing\PaymentResource;
use App\Models\Payment;
use App\Services\Billing\Contracts\PaymentServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Admin payment management.
 *
 * KEY ENDPOINTS:
 *  POST /approve → confirms receipt, triggers subscription activation
 *  POST /reject  → rejects payment with mandatory reason
 */
class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentServiceInterface $paymentService
    ) {}

    /**
     * GET /api/admin/billing/payments
     * List all payments; useful for pending payment queue.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only(['status', 'facility_id', 'payment_type', 'method']);
        $perPage = $request->integer('per_page', 15);

        $payments = $this->paymentService->getAllPayments($filters, $perPage);

        return PaymentResource::collection($payments);
    }

    /**
     * GET /api/admin/billing/payments/{payment}
     */
    public function show(Payment $payment): JsonResponse
    {
        $payment->load(['facility', 'subscription.plan', 'approvedBy']);

        return response()->json([
            'success' => true,
            'message' => 'Payment retrieved.',
            'data'    => new PaymentResource($payment),
        ]);
    }

    /**
     * POST /api/admin/billing/payments/{payment}/approve
     *
     * ✅ Admin confirms the payment receipt/evidence.
     * This is the primary endpoint that activates a facility's subscription.
     *
     * The subscription is automatically transitioned:
     *  - onboarding / subscription type → activateSubscription()
     *  - renewal type                   → renewSubscription()
     */
    public function approve(
        ApprovePaymentRequest $request,
        Payment $payment
    ): JsonResponse {
        try {
            $adminUser = $request->attributes->get('admin_user');

            $approved = $this->paymentService->approvePayment(
                payment:    $payment,
                approvedBy: $adminUser,
                notes:      $request->input('notes')
            );

            return response()->json([
                'success' => true,
                'message' => 'Payment approved. Facility subscription has been activated.',
                'data'    => new PaymentResource($approved->load(['subscription.plan', 'facility'])),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data'    => null,
            ], $e->getCode() >= 400 ? $e->getCode() : 422);
        }
    }

    /**
     * POST /api/admin/billing/payments/{payment}/reject
     *
     * ❌ Admin rejects the payment with a mandatory reason.
     * The subscription remains in its current status.
     */
    public function reject(
        RejectPaymentRequest $request,
        Payment $payment
    ): JsonResponse {
        try {
            $adminUser = $request->attributes->get('admin_user');

            $rejected = $this->paymentService->rejectPayment(
                payment:    $payment,
                rejectedBy: $adminUser,
                reason:     $request->input('reason')
            );

            return response()->json([
                'success' => true,
                'message' => 'Payment rejected. Facility has been notified.',
                'data'    => new PaymentResource($rejected),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data'    => null,
            ], $e->getCode() >= 400 ? $e->getCode() : 422);
        }
    }
}
