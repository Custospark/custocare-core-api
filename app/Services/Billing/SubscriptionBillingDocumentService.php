<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Constants\Billing\BillingIssuer;
use App\Enums\Billing\InvoiceStatus;
use App\Enums\Billing\InvoiceType;
use App\Enums\Billing\PaymentStatus;
use App\Enums\Billing\PaymentType;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use App\Repositories\Billing\Contracts\InvoiceRepositoryInterface;
use App\Repositories\Billing\Contracts\PaymentRepositoryInterface;
use App\Services\Billing\Contracts\InvoiceServiceInterface;
use App\Services\Billing\Contracts\SubscriptionBillingDocumentServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class SubscriptionBillingDocumentService implements SubscriptionBillingDocumentServiceInterface
{
    public function __construct(
        private readonly InvoiceRepositoryInterface $invoiceRepo,
        private readonly PaymentRepositoryInterface $paymentRepo,
        private readonly InvoiceServiceInterface $invoiceService,
    ) {}

    public function buildInvoiceDocument(Invoice $invoice): array
    {
        $invoice->loadMissing(['subscription.plan', 'facility', 'payment']);

        return $this->baseDocument('invoice', $invoice, $invoice->subscription, $invoice->payment);
    }

    public function buildReceiptDocument(Payment $payment): array
    {
        if ($payment->status !== PaymentStatus::APPROVED) {
            throw new \DomainException('Receipts are only available for approved payments.', 422);
        }

        $payment->loadMissing(['subscription.plan', 'facility', 'approvedBy']);

        $invoice = $payment->invoice_id
            ? $this->invoiceRepo->findById((int) $payment->invoice_id)
            : $this->invoiceRepo->findByPaymentId($payment->id);

        return $this->baseDocument('receipt', $invoice, $payment->subscription, $payment);
    }

    public function createInvoiceForPayment(Subscription $subscription, Payment $payment): Invoice
    {
        $subscription->loadMissing(['plan', 'facility']);

        $existing = $this->invoiceRepo->findByPaymentId($payment->id);
        if ($existing) {
            return $existing;
        }

        $quote = $subscription->metadata['latest_quote'] ?? null;
        $lineItems = $this->lineItemsFromQuote($quote, $subscription, $payment);
        $amount = (float) $payment->amount;
        $currency = strtoupper((string) $payment->currency) ?: BillingIssuer::DEFAULT_CURRENCY;

        $invoice = $this->invoiceService->createInvoice($subscription, [
            'invoice_type' => $this->mapPaymentTypeToInvoiceType($payment->payment_type),
            'amount'       => $amount,
            'currency'     => $currency,
            'description'  => $this->invoiceDescription($subscription, $payment),
            'line_items'   => $lineItems,
            'issued_at'    => now()->toDateString(),
            'due_at'       => now()->addDays(14)->toDateString(),
        ]);

        $this->invoiceRepo->update($invoice, ['payment_id' => $payment->id]);
        $this->paymentRepo->update($payment, ['invoice_id' => $invoice->id]);

        return $invoice->fresh(['subscription.plan', 'facility']);
    }

    public function issueReceiptForApprovedPayment(Payment $payment): Payment
    {
        $payment->loadMissing(['subscription.plan', 'facility']);

        if (! $payment->receipt_number) {
            $payment = $this->paymentRepo->update($payment, [
                'receipt_number' => $this->generateReceiptNumber(),
            ]);
        }

        $invoice = $payment->invoice_id
            ? $this->invoiceRepo->findById((int) $payment->invoice_id)
            : $this->invoiceRepo->findByPaymentId($payment->id);

        if (! $invoice) {
            $invoice = $this->createInvoiceForPayment($payment->subscription, $payment);
        }

        if (
            $invoice->status !== InvoiceStatus::PAID
            && $invoice->status !== InvoiceStatus::PARTIALLY_PAID
        ) {
            $this->invoiceService->markAsPaid(
                $invoice,
                (float) $payment->amount,
                $payment->approved_at?->toDateString() ?? now()->toDateString(),
            );
        }

        return $payment->fresh(['subscription.plan', 'facility', 'approvedBy']);
    }

    public function getInvoicesForFacility(int $facilityId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $filters['payable_only'] = $filters['payable_only'] ?? true;

        return $this->invoiceRepo->getForFacility($facilityId, $filters, $perPage);
    }

    public function getReceiptsForFacility(int $facilityId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $filters['status'] = PaymentStatus::APPROVED->value;

        return $this->paymentRepo->getForFacility($facilityId, $filters, $perPage);
    }

    /**
     * @return array<string, mixed>
     */
    private function baseDocument(
        string $documentType,
        ?Invoice $invoice,
        ?Subscription $subscription,
        ?Payment $payment,
    ): array {
        $facility = $invoice?->facility ?? $payment?->facility ?? $subscription?->facility;
        $plan = $subscription?->plan;
        $lineItems = $invoice?->line_items
            ?? $this->lineItemsFromQuote($subscription?->metadata['latest_quote'] ?? null, $subscription, $payment);
        $currency = $invoice?->currency
            ?? $payment?->currency
            ?? BillingIssuer::DEFAULT_CURRENCY;
        $amount = $invoice
            ? (float) $invoice->amount
            : (float) ($payment?->amount ?? 0);
        $paidAmount = $invoice ? (float) $invoice->paid_amount : (float) ($payment?->amount ?? 0);
        $balanceDue = $invoice ? $invoice->balanceDue() : 0.0;

        $documentNumber = $documentType === 'receipt'
            ? ($payment?->receipt_number ?? '—')
            : ($invoice?->invoice_number ?? '—');

        return [
            'document_type'       => $documentType,
            'document_type_label' => $documentType === 'receipt' ? 'Payment Receipt' : 'Tax Invoice',
            'document_number'     => $documentNumber,
            'issuer'              => BillingIssuer::toArray(),
            'bill_to'             => [
                'facility_id'   => $facility?->id,
                'facility_name' => $facility?->facility_name,
                'facility_code' => $facility?->facility_code,
                'email'         => $facility?->email,
                'phone'         => $facility?->main_phone,
                'address'       => $this->formatFacilityAddress($facility),
            ],
            'product' => [
                'name'        => BillingIssuer::PRODUCT_NAME,
                'description' => BillingIssuer::PRODUCT_TAGLINE,
                'plan_name'   => $plan?->name,
                'plan_slug'   => $plan?->slug,
                'billing_cycle' => $plan?->billing_cycle ?? 'monthly',
            ],
            'subscription' => $subscription ? [
                'id'     => $subscription->id,
                'status' => $subscription->status->value,
            ] : null,
            'line_items'   => $lineItems,
            'subtotal'     => round($amount, 2),
            'total'        => round($amount, 2),
            'paid_amount'  => round($paidAmount, 2),
            'balance_due'  => round($balanceDue, 2),
            'currency'     => strtoupper((string) $currency),
            'status'       => $documentType === 'receipt'
                ? PaymentStatus::APPROVED->value
                : ($invoice?->status->value ?? InvoiceStatus::UNPAID->value),
            'status_label' => $documentType === 'receipt'
                ? 'Paid'
                : ($invoice?->status->label() ?? 'Unpaid'),
            'issued_at'    => $invoice?->issued_at?->toDateString()
                ?? $payment?->created_at?->toDateString(),
            'due_at'       => $invoice?->due_at?->toDateString(),
            'paid_at'      => $documentType === 'receipt'
                ? ($payment?->approved_at?->toISOString() ?? $payment?->paid_at?->toISOString())
                : $invoice?->paid_at?->toDateString(),
            'payment' => $payment ? [
                'id'                    => $payment->id,
                'amount'                => (float) $payment->amount,
                'currency'              => $payment->currency,
                'method'                => $payment->method->value,
                'method_label'          => $payment->method->label(),
                'payment_type'          => $payment->payment_type->value,
                'payment_type_label'    => $payment->payment_type->label(),
                'transaction_reference' => $payment->transaction_reference,
                'receipt_number'        => $payment->receipt_number,
                'approved_at'           => $payment->approved_at?->toISOString(),
            ] : null,
            'invoice_id'  => $invoice?->id,
            'payment_id'  => $payment?->id,
            'notes'       => $invoice?->description,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $quote
     * @return list<array{description: string, quantity: int|float, unit_price: float, total: float}>
     */
    private function lineItemsFromQuote(?array $quote, ?Subscription $subscription, ?Payment $payment): array
    {
        if (is_array($quote) && ! empty($quote['line_items'])) {
            return array_map(static function (array $item): array {
                $qty = (float) ($item['quantity'] ?? 1);
                $unit = (float) ($item['amount'] ?? $item['unit_price'] ?? 0);
                $total = (float) ($item['total'] ?? ($qty * $unit));

                return [
                    'description' => (string) ($item['label'] ?? $item['description'] ?? 'Charge'),
                    'quantity'    => $qty > 0 ? $qty : 1,
                    'unit_price'  => round($unit, 2),
                    'total'       => round($total, 2),
                ];
            }, $quote['line_items']);
        }

        $plan = $subscription?->plan;
        $amount = (float) ($payment?->amount ?? $plan?->price_usd ?? 0);

        if ($plan) {
            return [[
                'description' => sprintf('%s — %s subscription', BillingIssuer::PRODUCT_NAME, $plan->name),
                'quantity'    => 1,
                'unit_price'  => round($amount, 2),
                'total'       => round($amount, 2),
            ]];
        }

        return [[
            'description' => BillingIssuer::PRODUCT_NAME . ' subscription',
            'quantity'    => 1,
            'unit_price'  => round($amount, 2),
            'total'       => round($amount, 2),
        ]];
    }

    private function invoiceDescription(?Subscription $subscription, Payment $payment): string
    {
        $planName = $subscription?->plan?->name ?? 'plan';

        return sprintf(
            '%s subscription — %s (%s)',
            BillingIssuer::PRODUCT_NAME,
            $planName,
            $payment->payment_type->label(),
        );
    }

    private function mapPaymentTypeToInvoiceType(PaymentType $type): string
    {
        return match ($type) {
            PaymentType::ONBOARDING         => InvoiceType::ONBOARDING->value,
            PaymentType::RENEWAL            => InvoiceType::RENEWAL->value,
            PaymentType::UPGRADE_PRORATION  => InvoiceType::ADJUSTMENT->value,
            default                         => InvoiceType::SUBSCRIPTION->value,
        };
    }

    private function formatFacilityAddress(?object $facility): ?string
    {
        if (! $facility) {
            return null;
        }

        return collect([
            $facility->address_line1 ?? null,
            $facility->address_line2 ?? null,
            $facility->city ?? null,
            $facility->state_province ?? null,
            $facility->country_code ?? null,
        ])->filter()->implode(', ') ?: null;
    }

    private function generateReceiptNumber(): string
    {
        $year = now()->format('Y');
        $prefix = "RCP-{$year}-";
        $last = Payment::where('receipt_number', 'like', "{$prefix}%")
            ->orderBy('receipt_number', 'desc')
            ->value('receipt_number');

        $next = $last ? (int) Str::after($last, $prefix) + 1 : 1;

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
