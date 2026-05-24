<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\Billing\PaymentStatus;
use App\Enums\Billing\PaymentType;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Repositories\Billing\Contracts\PaymentRepositoryInterface;
use App\Services\Billing\Contracts\PaymentServiceInterface;
use App\Services\Billing\Contracts\SubscriptionServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PaymentService implements PaymentServiceInterface
{
    public function __construct(
        private readonly PaymentRepositoryInterface $paymentRepo,
        private readonly SubscriptionServiceInterface $subscriptionService
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Record a payment (facility-facing)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Record a manual payment against a subscription.
     * Status starts as "pending" until an admin approves it.
     *
     * @param  Subscription   $subscription  The subscription being paid for.
     * @param  array          $data          Validated payment data.
     * @param  UploadedFile|null $receipt    Optional receipt file upload.
     * @throws \Exception     If the subscription is not in a payable state.
     */
    public function recordPayment(
        Subscription $subscription,
        array $data,
        ?UploadedFile $receipt = null
    ): Payment {
        return DB::transaction(function () use ($subscription, $data, $receipt) {

            // ── Store receipt file if provided ────────────────────────────
            $receiptPath = null;
            if ($receipt) {
                $receiptPath = $receipt->store(
                    "billing/receipts/{$subscription->id}",
                    'public'
                );
            }

            $payment = $this->paymentRepo->create([
                'subscription_id'     => $subscription->id,
                'facility_id'         => $subscription->facility_id,
                'amount'              => $data['amount'],
                'currency'            => strtoupper($data['currency']),
                'method'              => $data['method'],
                'payment_type'        => $data['payment_type'],
                'transaction_reference' => $data['transaction_reference'] ?? null,
                'receipt_path'        => $receiptPath,
                'receipt_notes'       => $data['receipt_notes'] ?? null,
                'paid_at'             => $data['paid_at'] ?? Carbon::now(),
                'status'              => PaymentStatus::PENDING->value,
                'metadata'            => $data['metadata'] ?? null,
            ]);

            Log::info('[Billing] Payment recorded — awaiting admin approval', [
                'payment_id'      => $payment->id,
                'subscription_id' => $subscription->id,
                'facility_id'     => $subscription->facility_id,
                'amount'          => $payment->amount,
                'currency'        => $payment->currency,
                'type'            => $payment->payment_type,
            ]);

            return $payment;
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Approve payment (admin-facing)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Admin approves a pending payment.
     * Automatically triggers subscription activation or renewal.
     *
     * @throws \Exception If payment is not in pending state.
     */
    public function approvePayment(
        Payment $payment,
        User $approvedBy,
        ?string $notes = null
    ): Payment {
        return DB::transaction(function () use ($payment, $approvedBy, $notes) {

            if (! $payment->isPending()) {
                throw new \Exception(
                    "Payment #{$payment->id} is already {$payment->status->value} and cannot be approved.",
                    422
                );
            }

            // ── Approve the payment ───────────────────────────────────────
            $payment = $this->paymentRepo->update($payment, [
                'status'               => PaymentStatus::APPROVED->value,
                'approved_at'          => Carbon::now(),
                'approved_by_user_id'  => $approvedBy->id,
                'receipt_notes'        => $notes
                    ? ($payment->receipt_notes ? $payment->receipt_notes . "\nAdmin: $notes" : $notes)
                    : $payment->receipt_notes,
            ]);

            // ── Trigger the appropriate subscription transition ───────────
            $subscription = $payment->subscription;

            match ($payment->payment_type) {
                PaymentType::ONBOARDING => $this->subscriptionService->activateSubscription(
                    $subscription, $payment, $approvedBy
                ),
                PaymentType::SUBSCRIPTION => $this->subscriptionService->activateSubscription(
                    $subscription, $payment, $approvedBy
                ),
                PaymentType::RENEWAL => $this->subscriptionService->renewSubscription(
                    $subscription, $payment, $approvedBy
                ),
            };

            Log::info('[Billing] Payment approved by admin', [
                'payment_id'  => $payment->id,
                'approved_by' => $approvedBy->id,
                'type'        => $payment->payment_type->value,
            ]);

            return $payment->fresh();
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Reject payment (admin-facing)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Admin rejects a payment with a mandatory reason.
     *
     * @throws \Exception If payment is not pending.
     */
    public function rejectPayment(
        Payment $payment,
        User $rejectedBy,
        string $reason
    ): Payment {
        if (! $payment->isPending()) {
            throw new \Exception(
                "Payment #{$payment->id} is already {$payment->status->value} and cannot be rejected.",
                422
            );
        }

        $updated = $this->paymentRepo->update($payment, [
            'status'               => PaymentStatus::REJECTED->value,
            'approved_at'          => Carbon::now(),
            'approved_by_user_id'  => $rejectedBy->id,
            'rejection_reason'     => $reason,
        ]);

        Log::warning('[Billing] Payment rejected by admin', [
            'payment_id'  => $updated->id,
            'rejected_by' => $rejectedBy->id,
            'reason'      => $reason,
        ]);

        return $updated;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Queries
    // ─────────────────────────────────────────────────────────────────────────

    public function getPaymentsForFacility(
        int $facilityId,
        array $filters = [],
        int $perPage = 15
    ): LengthAwarePaginator {
        return $this->paymentRepo->getForFacility($facilityId, $filters, $perPage);
    }

    public function getAllPayments(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->paymentRepo->getAllPaginated($filters, $perPage);
    }

    public function findPaymentById(int $id): ?Payment
    {
        return $this->paymentRepo->findById($id);
    }
}
