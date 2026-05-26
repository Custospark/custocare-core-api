<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Controller;
use App\Http\Resources\Billing\BillingDocumentResource;
use App\Http\Resources\Billing\InvoiceResource;
use App\Http\Resources\Billing\SubscriptionReceiptResource;
use App\Models\Facility;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Billing\Contracts\InvoiceServiceInterface;
use App\Services\Billing\Contracts\SubscriptionBillingDocumentServiceInterface;
use App\Services\Billing\Contracts\SubscriptionBillingPdfServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BillingDocumentController extends Controller
{
    public function __construct(
        private readonly SubscriptionBillingDocumentServiceInterface $documents,
        private readonly SubscriptionBillingPdfServiceInterface $pdfService,
        private readonly InvoiceServiceInterface $invoiceService,
    ) {}

    /**
     * GET /api/facilities/{facility}/billing-documents/invoices
     */
    public function invoices(Request $request, Facility $facility): AnonymousResourceCollection
    {
        $filters = array_merge(
            $request->only(['status', 'invoice_type', 'date_from', 'date_to']),
            ['payable_only' => $request->boolean('payable_only', true)],
        );
        $perPage = $request->integer('per_page', 15);

        $invoices = $this->documents->getInvoicesForFacility($facility->id, $filters, $perPage);

        return InvoiceResource::collection($invoices);
    }

    /**
     * GET /api/facilities/{facility}/billing-documents/invoices/{invoice}
     */
    public function invoice(Facility $facility, int $invoice): JsonResponse
    {
        $invoiceModel = $this->invoiceService->findInvoiceById($invoice);

        if (! $invoiceModel || (int) $invoiceModel->facility_id !== (int) $facility->id) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found.',
            ], 404);
        }

        $invoiceModel->load(['subscription.plan', 'facility', 'payment']);

        return response()->json([
            'success' => true,
            'message' => 'Invoice document retrieved.',
            'data'    => [
                'invoice'  => new InvoiceResource($invoiceModel),
                'document' => new BillingDocumentResource(
                    $this->documents->buildInvoiceDocument($invoiceModel),
                ),
            ],
        ]);
    }

    /**
     * GET /api/facilities/{facility}/billing-documents/receipts
     */
    public function receipts(Request $request, Facility $facility): AnonymousResourceCollection
    {
        $filters = $request->only(['payment_type', 'date_from', 'date_to']);
        $perPage = $request->integer('per_page', 15);

        $payments = $this->documents->getReceiptsForFacility($facility->id, $filters, $perPage);

        return SubscriptionReceiptResource::collection($payments);
    }

    /**
     * GET /api/facilities/{facility}/billing-documents/receipts/{payment}
     */
    public function receipt(Facility $facility, Payment $payment): JsonResponse
    {
        if ((int) $payment->facility_id !== (int) $facility->id) {
            return response()->json([
                'success' => false,
                'message' => 'Receipt not found for this facility.',
            ], 404);
        }

        try {
            $document = $this->documents->buildReceiptDocument($payment);
        } catch (\DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode() >= 400 ? $e->getCode() : 422);
        }

        $payment->load(['subscription.plan', 'facility', 'approvedBy', 'invoice']);

        return response()->json([
            'success' => true,
            'message' => 'Receipt document retrieved.',
            'data'    => [
                'receipt'  => new SubscriptionReceiptResource($payment),
                'document' => new BillingDocumentResource($document),
            ],
        ]);
    }

    /**
     * GET /api/facilities/{facility}/billing-documents/invoices/{invoice}/pdf
     */
    public function invoicePdf(Facility $facility, int $invoice): Response|JsonResponse
    {
        $invoiceModel = $this->invoiceService->findInvoiceById($invoice);

        if (! $invoiceModel || (int) $invoiceModel->facility_id !== (int) $facility->id) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found.',
            ], 404);
        }

        return $this->pdfService->downloadInvoicePdf($invoiceModel);
    }

    /**
     * GET /api/facilities/{facility}/billing-documents/receipts/{payment}/pdf
     */
    public function receiptPdf(Facility $facility, Payment $payment): Response|JsonResponse
    {
        if ((int) $payment->facility_id !== (int) $facility->id) {
            return response()->json([
                'success' => false,
                'message' => 'Receipt not found for this facility.',
            ], 404);
        }

        try {
            return $this->pdfService->downloadReceiptPdf($payment);
        } catch (\DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode() >= 400 ? $e->getCode() : 422);
        }
    }
}
