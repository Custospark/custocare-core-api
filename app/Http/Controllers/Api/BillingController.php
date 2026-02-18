<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\FinalizeBillingRequest;
use App\Http\Requests\Billing\GetBillingRequest;
use App\Services\Billing\BillingService;
use App\Services\Contracts\BillingServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Billing Controller
 *
 * Handles HTTP requests for Billing operations
 */
class BillingController extends Controller
{
    /**
     * Billing service instance
     *
     * @var BillingService
     */
    protected $billingService;

    /**
     * Constructor
     *
     * @param BillingServiceInterface $billingService
     */
    public function __construct(BillingService $billingService)
    {
        $this->billingService = $billingService;
    }

    /**
     * Store billing data (finalize payment and persist to database)
     * 
     * Endpoint: POST /api/billing/finalize
     * 
     * @param FinalizeBillingRequest $request
     * @return JsonResponse
     */
    public function finalize(FinalizeBillingRequest $request): JsonResponse
    {
        try {
            // 1) Get facility from header
            $facilityId = (int) $request->header('X-Facility-Id');
            
            if (!$facilityId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing X-Facility-Id header.',
                    'errors' => ['facility_id' => ['X-Facility-Id header is required.']],
                ], 422);
            }

            // 2) Get authenticated staff ID
            $userId = Auth::id();
            $staffId = DB::table('staff')->where('user_id', $userId)->value('id');

            if (!$staffId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Staff profile not found.',
                    'errors' => ['staff' => ['No staff record linked to this account.']],
                ], 403);
            }

            // 3) Call service to finalize billing
            $result = $this->billingService->finalizeBilling(
                $request->validated(),
                $facilityId,
                $staffId
            );

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            // 4) Return success response
            return response()->json($result, 201);

        } catch (\Exception $e) {
            Log::error('Failed to finalize billing in controller', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while finalizing billing.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Retrieve billing data for a visit
     * 
     * Endpoint: GET /api/billing/visit/{visitId}
     * 
     * @param GetBillingRequest $request
     * @param int $visitId
     * @return JsonResponse
     */
    public function getByVisit(GetBillingRequest $request, int $visitId): JsonResponse
    {
        try {
            // 1) Get facility from header
            $facilityId = (int) $request->header('X-Facility-Id');
            
            if (!$facilityId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing X-Facility-Id header.',
                    'errors' => ['facility_id' => ['X-Facility-Id header is required.']],
                ], 422);
            }

            // 2) Call service to retrieve billing data
            $result = $this->billingService->getBillingByVisit($visitId, $facilityId);

            if (!$result['success']) {
                $statusCode = isset($result['errors']) ? 404 : 400;
                return response()->json($result, $statusCode);
            }

            // 3) Return success response
            return response()->json($result, 200);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve billing data in controller', [
                'visit_id' => $visitId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while retrieving billing data.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}