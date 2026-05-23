<?php

declare(strict_types=1);

namespace App\Repositories\Billing\Contracts;

use App\Models\Invoice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface InvoiceRepositoryInterface
{
    public function findById(int $id): ?Invoice;
    public function findByInvoiceNumber(string $invoiceNumber): ?Invoice;
    public function create(array $data): Invoice;
    public function update(Invoice $invoice, array $data): Invoice;
    public function getForFacility(int $facilityId, array $filters = [], int $perPage = 15): LengthAwarePaginator;
    public function getAllPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator;
    public function getOverdueCount(): int;
    public function getPendingRevenue(): float;
}
