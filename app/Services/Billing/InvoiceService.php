<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\Billing\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Repositories\Billing\Contracts\InvoiceRepositoryInterface;
use App\Services\Billing\Contracts\InvoiceServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InvoiceService implements InvoiceServiceInterface
{
    public function __construct(
        private readonly InvoiceRepositoryInterface $invoiceRepo,
    ) {}

    public function createInvoice(Subscription $subscription, array $data): Invoice
    {
        return DB::transaction(function () use ($subscription, $data) {
            $invoiceNumber = $this->generateInvoiceNumber();

            return $this->invoiceRepo->create([
                'subscription_id' => $subscription->id,
                'facility_id'     => $subscription->facility_id,
                'invoice_number'  => $invoiceNumber,
                'invoice_type'    => $data['invoice_type'] ?? 'subscription',
                'status'          => InvoiceStatus::UNPAID,
                'amount'          => $data['amount'],
                'currency'        => $data['currency'] ?? 'USD',
                'paid_amount'     => 0,
                'description'     => $data['description'] ?? null,
                'line_items'      => $data['line_items'] ?? null,
                'issued_at'       => $data['issued_at'] ?? now()->toDateString(),
                'due_at'          => $data['due_at'] ?? now()->addDays(30)->toDateString(),
            ]);
        });
    }

    public function markAsPaid(Invoice $invoice, float $amount, ?string $paidAt = null): Invoice
    {
        return DB::transaction(function () use ($invoice, $amount, $paidAt) {
            $newPaid = $invoice->paid_amount + $amount;
            $newStatus = $newPaid >= $invoice->amount
                ? InvoiceStatus::PAID
                : InvoiceStatus::PARTIALLY_PAID;

            return $this->invoiceRepo->update($invoice, [
                'paid_amount' => $newPaid,
                'status'      => $newStatus,
                'paid_at'     => $newStatus === InvoiceStatus::PAID
                    ? ($paidAt ?? now()->toDateString())
                    : null,
            ]);
        });
    }

    public function cancelInvoice(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice) {
            return $this->invoiceRepo->update($invoice, [
                'status'       => InvoiceStatus::CANCELLED,
                'cancelled_at' => now(),
            ]);
        });
    }

    public function getInvoicesForFacility(int $facilityId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->invoiceRepo->getForFacility($facilityId, $filters, $perPage);
    }

    public function getAllInvoices(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->invoiceRepo->getAllPaginated($filters, $perPage);
    }

    public function findInvoiceById(int $id): ?Invoice
    {
        return $this->invoiceRepo->findById($id);
    }

    public function getOverdueCount(): int
    {
        return $this->invoiceRepo->getOverdueCount();
    }

    public function getPendingRevenue(): float
    {
        return $this->invoiceRepo->getPendingRevenue();
    }

    private function generateInvoiceNumber(): string
    {
        $year = now()->format('Y');
        $prefix = "INV-{$year}-";
        $last = Invoice::where('invoice_number', 'like', "{$prefix}%")
            ->orderBy('invoice_number', 'desc')
            ->value('invoice_number');

        $next = $last ? (int) Str::after($last, $prefix) + 1 : 1;
        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
