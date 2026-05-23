<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Billing\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Billing\InvoiceResource;
use App\Models\Invoice;
use App\Services\Billing\Contracts\InvoiceServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceServiceInterface $invoiceService
    ) {}

    /**
     * GET /api/admin/billing/invoices
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only(['status', 'facility_id', 'invoice_type']);
        $perPage = $request->integer('per_page', 15);

        $invoices = $this->invoiceService->getAllInvoices($filters, $perPage);

        return InvoiceResource::collection($invoices);
    }

    /**
     * GET /api/admin/billing/invoices/{invoice}
     */
    public function show(Invoice $invoice): JsonResponse
    {
        $invoice->load(['facility', 'subscription.plan']);

        return response()->json([
            'success' => true,
            'message' => 'Invoice retrieved.',
            'data'    => new InvoiceResource($invoice),
        ]);
    }

    /**
     * POST /api/admin/billing/invoices/{invoice}/mark-paid
     */
    public function markPaid(Request $request, Invoice $invoice): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:0',
            'paid_at' => 'nullable|date',
        ]);

        $updated = $this->invoiceService->markAsPaid(
            $invoice,
            (float) $request->input('amount'),
            $request->input('paid_at')
        );

        return response()->json([
            'success' => true,
            'message' => 'Invoice marked as paid.',
            'data'    => new InvoiceResource($updated),
        ]);
    }

    /**
     * POST /api/admin/billing/invoices/{invoice}/cancel
     */
    public function cancel(Invoice $invoice): JsonResponse
    {
        $cancelled = $this->invoiceService->cancelInvoice($invoice);

        return response()->json([
            'success' => true,
            'message' => 'Invoice cancelled.',
            'data'    => new InvoiceResource($cancelled),
        ]);
    }
}
