<?php

declare(strict_types=1);

namespace App\Services\Billing\Contracts;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface SubscriptionBillingDocumentServiceInterface
{
    /**
     * @return array<string, mixed>
     */
    public function buildInvoiceDocument(Invoice $invoice): array;

    /**
     * @return array<string, mixed>
     */
    public function buildReceiptDocument(Payment $payment): array;

    public function createInvoiceForPayment(Subscription $subscription, Payment $payment): Invoice;

    public function issueReceiptForApprovedPayment(Payment $payment): Payment;

    public function getInvoicesForFacility(int $facilityId, array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function getReceiptsForFacility(int $facilityId, array $filters = [], int $perPage = 15): LengthAwarePaginator;
}
