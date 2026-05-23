<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Controller;
use App\Http\Resources\Billing\InvoiceResource;
use App\Models\Facility;
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
     * GET /api/facilities/{facility}/invoices
     */
    public function index(Request $request, Facility $facility): AnonymousResourceCollection
    {
        $filters = $request->only(['status', 'invoice_type', 'date_from', 'date_to']);
        $perPage = $request->integer('per_page', 15);

        $invoices = $this->invoiceService->getInvoicesForFacility($facility->id, $filters, $perPage);

        return InvoiceResource::collection($invoices);
    }

    /**
     * GET /api/facilities/{facility}/invoices/{invoice}
     */
    public function show(Facility $facility, int $invoiceId): JsonResponse
    {
        $invoice = $this->invoiceService->findInvoiceById($invoiceId);

        if (! $invoice || $invoice->facility_id !== $facility->id) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found.',
            ], 404);
        }

        $invoice->load(['subscription.plan', 'facility']);

        return response()->json([
            'success' => true,
            'message' => 'Invoice retrieved.',
            'data'    => new InvoiceResource($invoice),
        ]);
    }
}
