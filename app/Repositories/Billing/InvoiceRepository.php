<?php

declare(strict_types=1);

namespace App\Repositories\Billing;

use App\Enums\Billing\InvoiceStatus;
use App\Models\Invoice;
use App\Repositories\Billing\Contracts\InvoiceRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class InvoiceRepository implements InvoiceRepositoryInterface
{
    public function findById(int $id): ?Invoice
    {
        return Invoice::with(['subscription.plan', 'facility'])->find($id);
    }

    public function findByInvoiceNumber(string $invoiceNumber): ?Invoice
    {
        return Invoice::with(['subscription.plan', 'facility'])
            ->where('invoice_number', $invoiceNumber)
            ->first();
    }

    public function create(array $data): Invoice
    {
        return Invoice::create($data);
    }

    public function update(Invoice $invoice, array $data): Invoice
    {
        $invoice->update($data);
        return $invoice->fresh();
    }

    public function getForFacility(int $facilityId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Invoice::forFacility($facilityId)
            ->with(['subscription.plan'])
            ->when(
                isset($filters['status']),
                fn($q) => $q->where('status', $filters['status'])
            )
            ->when(
                isset($filters['invoice_type']),
                fn($q) => $q->where('invoice_type', $filters['invoice_type'])
            )
            ->when(
                isset($filters['date_from']),
                fn($q) => $q->whereDate('issued_at', '>=', $filters['date_from'])
            )
            ->when(
                isset($filters['date_to']),
                fn($q) => $q->whereDate('issued_at', '<=', $filters['date_to'])
            )
            ->latest()
            ->paginate($perPage);
    }

    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Invoice::query()
            ->with(['facility', 'subscription.plan'])
            ->when(
                isset($filters['status']),
                fn($q) => $q->where('status', $filters['status'])
            )
            ->when(
                isset($filters['facility_id']),
                fn($q) => $q->where('facility_id', $filters['facility_id'])
            )
            ->when(
                isset($filters['invoice_type']),
                fn($q) => $q->where('invoice_type', $filters['invoice_type'])
            )
            ->latest()
            ->paginate($perPage);
    }

    public function getOverdueCount(): int
    {
        return Invoice::where('status', InvoiceStatus::UNPAID)
            ->whereDate('due_at', '<', now())
            ->count();
    }

    public function getPendingRevenue(): float
    {
        return (float) Invoice::whereIn('status', [
            InvoiceStatus::UNPAID,
            InvoiceStatus::OVERDUE,
            InvoiceStatus::PARTIALLY_PAID,
        ])->sum('amount');
    }
}
