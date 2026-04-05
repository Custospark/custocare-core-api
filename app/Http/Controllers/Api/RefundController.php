<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Refund\RefundTransactionRequest;
use App\Services\Billing\RefundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class RefundController extends Controller
{
    protected RefundService $refundService;

    public function __construct(RefundService $refundService)
    {
        $this->refundService = $refundService;
    }

    /**
     * POST /api/billing-cycles/{billingCycleId}/void
     */
    public function voidTransaction(Request $request, int $billingCycleId): JsonResponse
    {
        try {
            [$facilityId, $staffId, $headerError] = $this->extractHeaders($request);

            if ($headerError) {
                return $headerError;
            }

            $validated = $request->validate([
                'reason' => 'required|in:billing_error,service_not_rendered,duplicate_charge,patient_request,administrative_correction,pricing_error,cancelled_service,other',
                'reason_notes' => 'required_if:reason,other|string|max:500',
                'restore_inventory' => 'nullable|boolean',
            ]);

            $result = $this->refundService->voidTransaction(
                $billingCycleId,
                $validated,
                $facilityId,
                $staffId
            );

            return response()->json($result, $this->determineStatusCode($result));
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            Log::error('Void transaction request failed', [
                'billing_cycle_id' => $billingCycleId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to void transaction.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * POST /api/billing-cycles/{billingCycleId}/refund
     */
    public function refundTransaction(RefundTransactionRequest $request, int $billingCycleId): JsonResponse
    {
        try {
            [$facilityId, $staffId, $headerError] = $this->extractHeaders($request);

            if ($headerError) {
                return $headerError;
            }

            $validated = $request->validated();

            $result = $this->refundService->refundTransaction(
                $billingCycleId,
                $validated,
                $facilityId,
                $staffId
            );

            return response()->json($result, $this->determineStatusCode($result));
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            Log::error('Refund transaction request failed', [
                'billing_cycle_id' => $billingCycleId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process refund.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    private function determineStatusCode(array $result): int
    {
        if (!empty($result['success'])) {
            return 200;
        }

        $errors = $result['errors'] ?? [];

        if (isset($errors['billing_cycle']) || isset($errors['line_item_id'])) {
            return 404;
        }

        return 422;
    }

    /**
     * @return array{int, int, JsonResponse|null}
     */
    private function extractHeaders(Request $request): array
    {
        $facilityId = (int) $request->header('X-Facility-Id');
        $staffId = (int) $request->header('X-Staff-Id');

        if ($facilityId <= 0 || $staffId <= 0) {
            $errorResponse = response()->json([
                'success' => false,
                'message' => 'Missing required headers.',
                'errors' => array_filter([
                    'X-Facility-Id' => $facilityId <= 0 ? ['X-Facility-Id header is required.'] : null,
                    'X-Staff-Id' => $staffId <= 0 ? ['X-Staff-Id header is required.'] : null,
                ]),
            ], 422);

            return [0, 0, $errorResponse];
        }

        return [$facilityId, $staffId, null];
    }
}
