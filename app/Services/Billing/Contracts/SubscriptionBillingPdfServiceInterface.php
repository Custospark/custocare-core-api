<?php

declare(strict_types=1);

namespace App\Services\Billing\Contracts;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\Response;

interface SubscriptionBillingPdfServiceInterface
{
    public function downloadInvoicePdf(Invoice $invoice): Response;

    public function downloadReceiptPdf(Payment $payment): Response;

    public function generateInvoicePdfContent(Invoice $invoice): string;

    public function generateReceiptPdfContent(Payment $payment): string;
}
