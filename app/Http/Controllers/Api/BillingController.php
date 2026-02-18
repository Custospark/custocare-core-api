<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\FinalizeBillingRequest;
use App\Http\Requests\Billing\GetBillingRequest;
use App\Services\Billing\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Billing Controller
 *
 * Handles HTTP requests for Billing operations with comprehensive error handling
 */
class BillingController extends Controller
{
    /**
     * Billing service instance
     *
     * @var BillingService
     */
    protected BillingService $billingService;

    /**
     * Constructor
     *
     * @param BillingService $billingService
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
                Log::warning('Missing X-Facility-Id header in billing finalize request', [
                    'user_id' => Auth::id(),
                    'ip' => $request->ip(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Missing X-Facility-Id header.',
                    'errors' => ['facility_id' => ['X-Facility-Id header is required.']],
                ], 422);
            }

            // 2) Get authenticated staff ID
            $userId = Auth::id();
            
            if (!$userId) {
                Log::warning('Unauthenticated billing finalize attempt', [
                    'ip' => $request->ip(),
                    'facility_id' => $facilityId,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required.',
                    'errors' => ['auth' => ['You must be authenticated to perform this action.']],
                ], 401);
            }

            $staffId = DB::table('staff')->where('user_id', $userId)->value('id');

            if (!$staffId) {
                Log::warning('Staff profile not found for user attempting billing finalize', [
                    'user_id' => $userId,
                    'facility_id' => $facilityId,
                ]);

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
                Log::warning('Billing finalization failed at service layer', [
                    'user_id' => $userId,
                    'staff_id' => $staffId,
                    'facility_id' => $facilityId,
                    'visit_id' => $request->input('visit_id'),
                    'message' => $result['message'] ?? 'Unknown error',
                ]);

                return response()->json($result, 400);
            }

            // 4) Return success response
            return response()->json($result, 201);

        } catch (Throwable $e) {
            Log::error('Unexpected error in billing finalize controller', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
                'facility_id' => $request->header('X-Facility-Id'),
                'visit_id' => $request->input('visit_id'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while finalizing billing. Please try again or contact support if the issue persists.',
                'errors' => ['system' => ['A system error prevented billing finalization.']],
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
                Log::warning('Missing X-Facility-Id header in get billing request', [
                    'user_id' => Auth::id(),
                    'visit_id' => $visitId,
                    'ip' => $request->ip(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Missing X-Facility-Id header.',
                    'errors' => ['facility_id' => ['X-Facility-Id header is required.']],
                ], 422);
            }

            // 2) Verify user is authenticated
            $userId = Auth::id();
            
            if (!$userId) {
                Log::warning('Unauthenticated billing retrieval attempt', [
                    'ip' => $request->ip(),
                    'facility_id' => $facilityId,
                    'visit_id' => $visitId,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required.',
                    'errors' => ['auth' => ['You must be authenticated to view billing data.']],
                ], 401);
            }

            // 3) Call service to retrieve billing data
            $result = $this->billingService->getBillingByVisit($visitId, $facilityId);

            if (!$result['success']) {
                $statusCode = isset($result['errors']) ? 404 : 400;
                
                Log::info('Billing retrieval returned non-success', [
                    'user_id' => $userId,
                    'facility_id' => $facilityId,
                    'visit_id' => $visitId,
                    'status_code' => $statusCode,
                    'message' => $result['message'] ?? 'Unknown error',
                ]);

                return response()->json($result, $statusCode);
            }

            // 4) Return success response
            return response()->json($result, 200);

        } catch (Throwable $e) {
            Log::error('Unexpected error in get billing controller', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
                'facility_id' => $request->header('X-Facility-Id'),
                'visit_id' => $visitId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while retrieving billing data. Please try again or contact support if the issue persists.',
                'errors' => ['system' => ['A system error prevented billing data retrieval.']],
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
