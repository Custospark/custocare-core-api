<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\StorePaymentRequest;
use App\Http\Resources\Billing\PaymentResource;
use App\Models\Facility;
use App\Models\Payment;
use App\Models\Subscription;
use App\Services\Billing\Contracts\PaymentServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentServiceInterface $paymentService
    ) {}

    /**
     * GET /api/facilities/{facility}/payments
     */
    public function index(Request $request, Facility $facility): AnonymousResourceCollection
    {
        $filters = $request->only(['status', 'payment_type']);
        $perPage = $request->integer('per_page', 15);

        $payments = $this->paymentService->getPaymentsForFacility($facility->id, $filters, $perPage);

        return PaymentResource::collection($payments);
    }

    /**
     * GET /api/facilities/{facility}/payments/{payment}
     */
    public function show(Facility $facility, Payment $payment): JsonResponse
    {
        if ((int) $payment->facility_id !== (int) $facility->id) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found for this facility.',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Payment retrieved.',
            'data' => new PaymentResource($payment->load(['subscription.plan', 'approvedBy'])),
        ]);
    }

    /**
     * GET /api/facilities/{facility}/payments/{payment}/receipt
     * Streams receipt file from storage (no public symlink dependency).
     */
    public function receipt(Facility $facility, Payment $payment)
    {
        if ((int) $payment->facility_id !== (int) $facility->id || ! $payment->receipt_path) {
            abort(404, 'Receipt not found.');
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($payment->receipt_path)) {
            abort(404, 'Receipt file is missing from storage.');
        }

        $name = basename($payment->receipt_path);

        return $disk->download($payment->receipt_path, $name);
    }

    /**
     * POST /api/facilities/{facility}/payments
     */
    public function store(StorePaymentRequest $request, Facility $facility): JsonResponse
    {
        $subscription = Subscription::where('facility_id', $facility->id)
            ->whereIn('status', ['trial', 'active', 'past_due'])
            ->latest()
            ->first();

        if (! $subscription) {
            return response()->json([
                'success' => false,
                'message' => 'No active or trial subscription found for this facility.',
                'errors' => ['subscription' => ['Create or restore a subscription first.']],
                'data' => null,
            ], 404);
        }

        try {
            $payment = $this->paymentService->recordPayment(
                $subscription,
                $request->validated(),
                $request->file('receipt')
            );
        } catch (\DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => ['payment' => [$e->getMessage()]],
                'data' => null,
            ], $e->getCode() >= 400 ? $e->getCode() : 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Payment recorded. Awaiting admin approval.',
            'data' => new PaymentResource($payment->load(['subscription.plan'])),
            'errors' => null,
        ], 201);
    }
}
