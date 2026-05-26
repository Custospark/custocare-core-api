<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\Billing\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Billing\Contracts\SubscriptionBillingDocumentServiceInterface;
use App\Services\Billing\Contracts\SubscriptionBillingPdfServiceInterface;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class SubscriptionBillingPdfService implements SubscriptionBillingPdfServiceInterface
{
    public function __construct(
        private readonly SubscriptionBillingDocumentServiceInterface $documents,
    ) {}

    public function downloadInvoicePdf(Invoice $invoice): Response
    {
        $invoice->loadMissing(['subscription.plan', 'facility', 'payment']);
        $doc = $this->documents->buildInvoiceDocument($invoice);
        $filename = str_replace('/', '_', $doc['document_number']) . '.pdf';

        return Pdf::loadView('pdf.billing.invoice', ['doc' => $doc])
            ->download($filename);
    }

    public function downloadReceiptPdf(Payment $payment): Response
    {
        if ($payment->status !== PaymentStatus::APPROVED) {
            throw new \DomainException('Receipts are only available for approved payments.', 422);
        }

        $payment->loadMissing(['subscription.plan', 'facility', 'approvedBy']);
        $doc = $this->documents->buildReceiptDocument($payment);
        $filename = str_replace('/', '_', $doc['document_number']) . '.pdf';

        return Pdf::loadView('pdf.billing.receipt', ['doc' => $doc])
            ->download($filename);
    }

    public function generateInvoicePdfContent(Invoice $invoice): string
    {
        $invoice->loadMissing(['subscription.plan', 'facility', 'payment']);
        $doc = $this->documents->buildInvoiceDocument($invoice);

        return Pdf::loadView('pdf.billing.invoice', ['doc' => $doc])->output();
    }

    public function generateReceiptPdfContent(Payment $payment): string
    {
        if ($payment->status !== PaymentStatus::APPROVED) {
            throw new \DomainException('Receipts are only available for approved payments.', 422);
        }

        $payment->loadMissing(['subscription.plan', 'facility', 'approvedBy']);
        $doc = $this->documents->buildReceiptDocument($payment);

        return Pdf::loadView('pdf.billing.receipt', ['doc' => $doc])->output();
    }
}
