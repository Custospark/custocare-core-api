<?php

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\StorePaymentRequest;
use App\Http\Resources\Billing\PaymentResource;
use App\Models\Subscription;
use App\Services\Billing\Contracts\PaymentServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentServiceInterface $paymentService
    ) {}

    /**
     * Record a payment for the active facility's subscription.
     * Expects the active facility context via X-Active-Facility-Id header.
     */
    public function store(StorePaymentRequest $request): JsonResponse
    {
        $facilityId = $request->header('X-Active-Facility-Id');

        if (!$facilityId) {
            return response()->json([
                'success' => false,
                'message' => 'No active facility selected.',
                'errors'  => ['facility' => ['X-Active-Facility-Id header is required.']],
                'data'    => null,
            ], 400);
        }

        $subscription = Subscription::where('facility_id', $facilityId)
            ->whereIn('status', ['trial', 'active', 'past_due'])
            ->latest()
            ->first();

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'No active subscription found for this facility.',
                'errors'  => ['subscription' => ['Create a subscription first.']],
                'data'    => null,
            ], 404);
        }

        $receipt = $request->file('receipt');
        $payment = $this->paymentService->recordPayment(
            $subscription,
            $request->validated(),
            $receipt
        );

        return response()->json([
            'success' => true,
            'message' => 'Payment recorded. Awaiting admin approval.',
            'data'    => new PaymentResource($payment),
            'errors'  => null,
        ], 201);
    }
}
