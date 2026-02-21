<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Billing\RefundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * RefundController
 *
 * Exposes exactly two HTTP entry points:
 *  - POST …/{billingCycleId}/void    → voidTransaction()
 *  - POST …/{billingCycleId}/refund  → refundTransaction()
 *
 * Type detection (full vs. partial refund) is delegated entirely to
 * the service layer; the controller only validates input and routes.
 */
class RefundController extends Controller
{
    /**
     * @var RefundService
     */
    protected RefundService $refundService;

    public function __construct(RefundService $refundService)
    {
        $this->refundService = $refundService;
    }

    // -------------------------------------------------------------------------
    // ENTRY POINT 1 – Void Transaction
    // -------------------------------------------------------------------------

    /**
     * Void a transaction.
     *
     * Marks the billing cycle as invalid without issuing a monetary refund.
     * Only applicable to draft billings or those created within the last 24 h
     * with no submitted insurance claim.
     *
     * POST /api/billing-cycles/{billingCycleId}/void
     *
     * Headers required:
     *   X-Facility-Id  (int)
     *   X-Staff-Id     (int)
     *
     * Body:
     *   reason           string  required
     *   reason_notes     string  required when reason = "other"
     *   restore_inventory bool   optional (defaults to true in service)
     *
     * @param Request $request
     * @param int     $billingCycleId
     * @return JsonResponse
     */
    public function voidTransaction(Request $request, int $billingCycleId): JsonResponse
    {
        try {
            // --- Header extraction & guard -----------------------------------
            [$facilityId, $staffId, $headerError] = $this->extractHeaders($request);

            if ($headerError) {
                return $headerError;
            }

            // --- Validation --------------------------------------------------
            $validated = $request->validate([
                'reason'          => 'required|in:billing_error,service_not_rendered,duplicate_charge,patient_request,administrative_correction,pricing_error,cancelled_service,other',
                'reason_notes'    => 'required_if:reason,other|string|max:500',
                'restore_inventory' => 'boolean',
            ]);

            // --- Delegate to service -----------------------------------------
            $result     = $this->refundService->voidTransaction($billingCycleId, $validated, $facilityId, $staffId);
            $statusCode = $result['success'] ? 200 : 422;

            return response()->json($result, $statusCode);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Void transaction request failed', [
                'billing_cycle_id' => $billingCycleId,
                'error'            => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to void transaction.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // -------------------------------------------------------------------------
    // ENTRY POINT 2 – Refund Transaction (full OR partial, auto-detected)
    // -------------------------------------------------------------------------

    /**
     * Process a refund — full or partial.
     *
     * The refund type is resolved automatically:
     *   • Payload contains `line_items`  →  partial refund
     *   • No `line_items` in payload     →  full refund
     *
     * POST /api/billing-cycles/{billingCycleId}/refund
     *
     * Headers required:
     *   X-Facility-Id  (int)
     *   X-Staff-Id     (int)
     *
     * Body (full refund):
     *   reason           string   required
     *   reason_notes     string   required when reason = "other"
     *   refund_methods   array    required  (type, amount, reference?)
     *   restore_inventory bool    optional
     *
     * Body (partial refund — same as above, plus):
     *   line_items                   array  required | min:1
     *   line_items.*.line_item_id    int    required | exists:invoice_line_items
     *   line_items.*.refund_amount   numeric optional (defaults to full line amount)
     *
     * @param Request $request
     * @param int     $billingCycleId
     * @return JsonResponse
     */
    public function refundTransaction(Request $request, int $billingCycleId): JsonResponse
    {
        try {
            // --- Header extraction & guard -----------------------------------
            [$facilityId, $staffId, $headerError] = $this->extractHeaders($request);

            if ($headerError) {
                return $headerError;
            }

            // --- Unified validation for both refund types -------------------
            // line_items is nullable; its presence signals a partial refund.
            $validated = $request->validate([
                'reason'       => 'required|in:billing_error,service_not_rendered,duplicate_charge,patient_request,insurance_denial,administrative_correction,pricing_error,cancelled_service,other',
                'reason_notes' => 'required_if:reason,other|string|max:500',

                // Optional — omit entirely for a full refund
                'line_items'                      => 'nullable|array|min:1',
                'line_items.*.line_item_id'       => 'required_with:line_items|integer|exists:invoice_line_items,id',
                'line_items.*.refund_amount'      => 'nullable|numeric|min:0',

                'refund_methods'           => 'required|array|min:1',
                'refund_methods.*.type'    => 'required|in:cash,card,insurance,mobile,bank_transfer,cheque,other',
                'refund_methods.*.amount'  => 'required|numeric|min:0',
                'refund_methods.*.reference' => 'nullable|string',

                'restore_inventory' => 'boolean',
            ]);

            // --- Delegate to service (type auto-detected inside) -------------
            $result     = $this->refundService->refundTransaction($billingCycleId, $validated, $facilityId, $staffId);
            $statusCode = $result['success'] ? 200 : 422;

            return response()->json($result, $statusCode);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Refund transaction request failed', [
                'billing_cycle_id' => $billingCycleId,
                'error'            => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process refund.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    // -------------------------------------------------------------------------
    // Internal helper
    // -------------------------------------------------------------------------

    /**
     * Extract and validate X-Facility-Id / X-Staff-Id from request headers.
     *
     * Returns a 3-element array: [facilityId, staffId, errorResponse|null]
     * If errorResponse is non-null the caller should return it immediately.
     *
     * @param Request $request
     * @return array{int, int, JsonResponse|null}
     */
    private function extractHeaders(Request $request): array
    {
        $facilityId = (int) $request->header('X-Facility-Id');
        $staffId    = (int) $request->header('X-Staff-Id');

        if (!$facilityId || !$staffId) {
            $errorResponse = response()->json([
                'success' => false,
                'message' => 'Missing required headers.',
                'errors'  => array_filter([
                    'X-Facility-Id' => !$facilityId ? ['X-Facility-Id header is required.'] : null,
                    'X-Staff-Id'    => !$staffId    ? ['X-Staff-Id header is required.']    : null,
                ]),
            ], 422);

            return [0, 0, $errorResponse];
        }

        return [$facilityId, $staffId, null];
    }
}
