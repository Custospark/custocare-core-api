<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\WalkInSession\CreateSessionRequest;
use App\Http\Requests\WalkInSession\UpgradeSessionRequest;
use App\Http\Resources\WalkInSessionResource;
use App\Services\Contracts\WalkInCustomerServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WalkInSessionController extends Controller
{
    /**
     * Walk-in customer service instance.
     */
    protected WalkInCustomerServiceInterface $walkInCustomerService;

    /**
     * Create a new controller instance.
     */
    public function __construct(WalkInCustomerServiceInterface $walkInCustomerService)
    {
        $this->walkInCustomerService = $walkInCustomerService;
        
        // Apply middleware
        // TODO: Define these middlewares
        // $this->middleware('auth:api');
        // $this->middleware('can:create,App\Models\Visit')->only(['createSession']);
        // $this->middleware('can:update,App\Models\BillingCycle')->only(['upgrade']);
    }

    /**
     * Create a walk-in session.
     */
    public function createSession(CreateSessionRequest $request, int $facilityId): JsonResponse
    {
        try {
            $staffId = $request->user()?->id;
            
            $session = $this->walkInCustomerService->createWalkInSession(
                $facilityId,
                $staffId
            );

            return response()->json([
                'success' => true,
                'message' => 'Walk-in session created successfully.',
                'data' => new WalkInSessionResource($session),
            ], JsonResponse::HTTP_CREATED);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::warning('Facility not found for walk-in session', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Facility not found.',
                'data' => null,
            ], JsonResponse::HTTP_NOT_FOUND);

        } catch (\RuntimeException $e) {
            Log::warning('Walk-in session creation failed', [
                'reason' => $e->getMessage(),
                'facility_id' => $facilityId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to create walk-in session.',
                'data' => null,
            ], JsonResponse::HTTP_BAD_REQUEST);

        } catch (\Throwable $e) {
            Log::error('Unexpected walk-in session creation error', [
                'exception' => $e,
                'facility_id' => $facilityId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error.',
                'data' => null,
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Upgrade walk-in session to real patient.
     */
    public function upgrade(UpgradeSessionRequest $request, int $billingCycleId): JsonResponse
    {
        try {
            $staffId = $request->user()?->id;
            $validated = $request->validated();
            $facilityId = (int) $validated['facility_id'];

            $result = $this->walkInCustomerService->upgradeWalkInToRealPatient(
                $billingCycleId,
                $facilityId,
                $validated,
                $staffId
            );

            return response()->json([
                'success' => true,
                'message' => 'Walk-in session upgraded successfully.',
                'data' => $result,
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::warning('Billing cycle not found for upgrade', [
                'billing_cycle_id' => $billingCycleId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Billing cycle not found.',
                'data' => null,
            ], JsonResponse::HTTP_NOT_FOUND);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);

        } catch (\RuntimeException $e) {
            Log::warning('Walk-in session upgrade failed', [
                'reason' => $e->getMessage(),
                'billing_cycle_id' => $billingCycleId,
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], JsonResponse::HTTP_BAD_REQUEST);

        } catch (\Throwable $e) {
            Log::error('Unexpected walk-in session upgrade error', [
                'exception' => $e,
                'billing_cycle_id' => $billingCycleId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error.',
                'data' => null,
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get or create facility walk-in patient.
     */
    public function getFacilityWalkInPatient(Request $request, int $facilityId): JsonResponse
    {
        try {
            $staffId = $request->user()?->id;
            
            $walkInPatient = $this->walkInCustomerService->getOrCreateFacilityWalkInPatient(
                $facilityId,
                $staffId
            );

            return response()->json([
                'success' => true,
                'message' => 'Facility walk-in patient retrieved successfully.',
                'data' => $walkInPatient,
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::warning('Facility not found for walk-in patient', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Facility not found.',
                'data' => null,
            ], JsonResponse::HTTP_NOT_FOUND);

        } catch (\Throwable $e) {
            Log::error('Error getting facility walk-in patient', [
                'exception' => $e,
                'facility_id' => $facilityId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve facility walk-in patient.',
                'data' => null,
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Validate if a user is the system walk-in user.
     */
    public function validateSystemWalkInUser(Request $request, int $userId): JsonResponse
    {
        try {
            // This would require fetching the user first
            // For now, returning a placeholder response
            return response()->json([
                'success' => true,
                'message' => 'System walk-in user validation endpoint.',
                'data' => [
                    'user_id' => $userId,
                    'is_system_walkin' => false,
                ],
            ]);

        } catch (\Throwable $e) {
            Log::error('Error validating system walk-in user', [
                'exception' => $e,
                'user_id' => $userId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to validate system walk-in user.',
                'data' => null,
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}