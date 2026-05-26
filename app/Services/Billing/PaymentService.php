<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\Billing\PaymentStatus;
use App\Enums\Billing\PaymentType;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Repositories\Billing\Contracts\PaymentRepositoryInterface;
use App\Models\Plan;
use App\Services\Billing\Contracts\PaymentServiceInterface;
use App\Services\Billing\Contracts\SubscriptionBillingDocumentServiceInterface;
use App\Services\Billing\Contracts\SubscriptionBillingPdfServiceInterface;
use App\Services\Billing\Contracts\SubscriptionPaymentQuoteServiceInterface;
use App\Services\Billing\Contracts\SubscriptionServiceInterface;
use App\Services\Billing\BillingFacilitySummaryService;
use App\Services\Notification\NotificationService;
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
        private readonly SubscriptionServiceInterface $subscriptionService,
        private readonly SubscriptionPaymentQuoteServiceInterface $quoteService,
        private readonly BillingFacilitySummaryService $billingFacilitySummaryService,
        private readonly SubscriptionBillingDocumentServiceInterface $billingDocuments,
        private readonly SubscriptionBillingPdfServiceInterface $billingPdfService,
        private readonly NotificationService $notificationService,
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
            $hasPendingProof = Payment::query()
                ->where('subscription_id', $subscription->id)
                ->where('status', PaymentStatus::PENDING->value)
                ->exists();

            if ($hasPendingProof) {
                throw new \DomainException(
                    'A payment proof is already pending approval. Wait for the platform admin to review it before submitting another.',
                    422
                );
            }

            $quoteIntent = $data['quote_intent'] ?? $this->resolveQuoteIntent($subscription, $data['payment_type']);
            $targetPlanId = isset($data['target_plan_id'])
                ? (int) $data['target_plan_id']
                : ($subscription->metadata['pending_upgrade_plan_id'] ?? null);

            $this->quoteService->validatePaymentAmount(
                $subscription,
                (float) $data['amount'],
                $quoteIntent,
                $targetPlanId ? (int) $targetPlanId : null,
            );

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
                'metadata'            => array_merge($data['metadata'] ?? [], [
                    'quote_intent'    => $quoteIntent,
                    'target_plan_id'  => $targetPlanId,
                ]),
            ]);

            $subscription = $subscription->fresh(['plan', 'facility']);
            $this->billingDocuments->createInvoiceForPayment($subscription, $payment);

            Log::info('[Billing] Payment recorded — awaiting admin approval', [
                'payment_id'      => $payment->id,
                'subscription_id' => $subscription->id,
                'facility_id'     => $subscription->facility_id,
                'amount'          => $payment->amount,
                'currency'        => $payment->currency,
                'type'            => $payment->payment_type,
            ]);

            // Send payment submission confirmation to facility owner(s)
            try {
                $facility = $subscription->facility;
                if ($facility) {
                    $ref = $payment->transaction_reference ?? 'N/A';
                    $this->notificationService->sendBillingToFacility(
                        $facility,
                        'Payment proof received — pending review',
                        "<p>Your payment of <strong>{$payment->currency} " . number_format((float) $payment->amount, 2) . "</strong> for <strong>{$subscription->plan?->name}</strong> has been received.</p>
                        <p>Reference: <strong>{$ref}</strong></p>
                        <p>A platform administrator will review your payment proof shortly. You will be notified once it is approved.</p>",
                    );
                }
            } catch (\Exception $e) {
                Log::error('[Billing] Failed to send payment submission email', [
                    'payment_id' => $payment->id,
                    'error'      => $e->getMessage(),
                ]);
            }

            return $payment->fresh(['subscription.plan', 'invoice']);
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
                PaymentType::UPGRADE_PRORATION => $this->applyUpgradeProration($subscription, $payment, $approvedBy),
            };

            $payment = $this->billingDocuments->issueReceiptForApprovedPayment(
                $payment->fresh(['subscription.plan', 'facility']),
            );

            // Send receipt to facility owner(s) with PDF attached
            try {
                $facility = $payment->facility;
                if ($facility && $payment->receipt_number) {
                    $pdfContent = $this->billingPdfService->generateReceiptPdfContent($payment);

                    $this->notificationService->sendBillingToFacility(
                        $facility,
                        "Payment approved — receipt #{$payment->receipt_number}",
                        "<p>Your payment of <strong>{$payment->currency} " . number_format((float) $payment->amount, 2) . "</strong> for <strong>{$subscription->plan?->name}</strong> has been approved.</p>
                        <p>Receipt: <strong>{$payment->receipt_number}</strong></p>
                        <p>Your receipt is attached below. Thank you for your payment.</p>",
                        [
                            [
                                'data' => $pdfContent,
                                'name' => str_replace('/', '_', $payment->receipt_number) . '.pdf',
                                'mime' => 'application/pdf',
                            ],
                        ],
                    );
                }
            } catch (\Exception $e) {
                Log::error('[Billing] Failed to send payment approval receipt email', [
                    'payment_id' => $payment->id,
                    'error'      => $e->getMessage(),
                ]);
            }

            Log::info('[Billing] Payment approved by admin', [
                'payment_id'  => $payment->id,
                'approved_by' => $approvedBy->id,
                'type'        => $payment->payment_type->value,
            ]);

            return $payment->fresh(['subscription.plan', 'invoice']);
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
        $paginator = $this->paymentRepo->getAllPaginated($filters, $perPage);

        return $this->billingFacilitySummaryService->enrichPaymentPaginator($paginator);
    }

    public function findPaymentById(int $id): ?Payment
    {
        return $this->paymentRepo->findById($id);
    }

    private function resolveQuoteIntent(Subscription $subscription, string $paymentType): string
    {
        $type = PaymentType::tryFrom($paymentType);

        return match ($type) {
            PaymentType::RENEWAL            => 'renewal',
            PaymentType::UPGRADE_PRORATION  => 'upgrade_now',
            PaymentType::ONBOARDING         => 'first_activation',
            PaymentType::SUBSCRIPTION       => $subscription->status->value === 'trial'
                ? 'subscription'
                : 'first_activation',
            default => 'subscription',
        };
    }

    private function applyUpgradeProration(
        Subscription $subscription,
        Payment $payment,
        User $approvedBy,
    ): void {
        $targetPlanId = $payment->metadata['target_plan_id']
            ?? $subscription->metadata['pending_upgrade_plan_id']
            ?? null;

        if (! $targetPlanId) {
            throw new \DomainException('Upgrade target plan is missing from payment metadata.', 422);
        }

        $plan = Plan::findOrFail((int) $targetPlanId);

        $this->subscriptionService->upgradeNow($subscription, $plan, $approvedBy);
    }
}
