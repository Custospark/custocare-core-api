<?php

declare(strict_types=1);

namespace App\Services\Billing\Contracts;

use App\Models\Invoice;
use App\Models\Subscription;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface InvoiceServiceInterface
{
    public function createInvoice(Subscription $subscription, array $data): Invoice;
    public function markAsPaid(Invoice $invoice, float $amount, ?string $paidAt = null): Invoice;
    public function cancelInvoice(Invoice $invoice): Invoice;
    public function getInvoicesForFacility(int $facilityId, array $filters = [], int $perPage = 15): LengthAwarePaginator;
    public function getAllInvoices(array $filters = [], int $perPage = 15): LengthAwarePaginator;
    public function findInvoiceById(int $id): ?Invoice;
    public function getOverdueCount(): int;
    public function getPendingRevenue(): float;
}
