<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StoreStaffRequest;
use App\Http\Requests\Staff\UpdateStaffRequest;
use App\Http\Resources\StaffResource;
use App\Services\Contracts\StaffServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class StaffController extends Controller
{
    /**
     * Staff service instance.
     */
    protected StaffServiceInterface $staffService;

    /**
     * Create a new controller instance.
     */
    public function __construct(StaffServiceInterface $staffService)
    {
        $this->staffService = $staffService;
        
        // Apply middleware
        //TODO:Define these middlewares.
        // $this->middleware('auth:api');
        // $this->middleware('can:viewAny,App\Models\Staff')->only(['index', 'show']);
        // $this->middleware('can:create,App\Models\Staff')->only(['store']);
        // $this->middleware('can:update,staff')->only(['update']);
        // $this->middleware('can:delete,staff')->only(['destroy']);
    }

    /**
     * Display a listing of the staff.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Get filters from request
            $filters = $request->only([
                'employment_status', 
                'global_role_level', 
                'search',
                'has_expired_license'
            ]);
            
            // Get paginated staff
            $perPage = $request->get('per_page', 20);
            $staff = $this->staffService->getAllStaff($filters, $perPage);
            
            return response()->json([
                'success' => true,
                'message' => 'Staff retrieved successfully.',
                'data' => StaffResource::collection($staff),
                'meta' => [
                    'current_page' => $staff->currentPage(),
                    'last_page' => $staff->lastPage(),
                    'per_page' => $staff->perPage(),
                    'total' => $staff->total(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error retrieving staff list', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve staff list.',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Store a newly created staff in storage.
     */
    public function store(StoreStaffRequest $request): JsonResponse
    {
        try {
            $staff = $this->staffService->createStaff(
                $request->validated()
            );

            return response()->json([
                'success' => true,
                'message' => 'Staff account created successfully.',
                'data'    => new StaffResource($staff),
                'errors'  => null,
            ], JsonResponse::HTTP_CREATED);

        } catch (\Illuminate\Auth\AuthenticationException $e) {

            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'data'    => null,
                'errors'  => null,
            ], JsonResponse::HTTP_UNAUTHORIZED);

        } catch (\RuntimeException $e) {

            Log::warning('Staff creation failed', [
                'reason' => $e->getMessage(),
                'input'  => $request->validated(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to create staff record.',
                'data'    => null,
                'errors'  => null,
            ], JsonResponse::HTTP_BAD_REQUEST);

        } catch (\Throwable $e) {

            Log::error('Unexpected staff creation error', [
                'exception' => $e,
                'input'     => $request->validated(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error.',
                'data'    => null,
                'errors'  => null,
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified staff.
     */
    public function show(int $id): JsonResponse
    {
        try {
            // Get staff by ID
            $staff = $this->staffService->getStaffById($id);
            
            if (!$staff) {
                return response()->json([
                    'success' => false,
                    'message' => 'Staff not found.',
                    'data' => null
                ], JsonResponse::HTTP_NOT_FOUND);
            }
            
            // Check authorization using policy
            if (!auth::user()->can('view', $staff)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to view this staff record.',
                    'data' => null
                ], JsonResponse::HTTP_FORBIDDEN);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Staff retrieved successfully.',
                'data' => new StaffResource($staff)
            ]);
        } catch (\Exception $e) {
            Log::error('Error retrieving staff', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve staff.',
                'data' => null
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update the specified staff in storage.
     */
    public function update(UpdateStaffRequest $request, int $id): JsonResponse
    {
        try {
            // Get validated data
            $data = $request->validated();
            
            // Update staff
            $result = $this->staffService->updateStaff($id, $data);
            
            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'data' => $result['data']
                ], JsonResponse::HTTP_BAD_REQUEST);
            }
            
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => new StaffResource($result['data'])
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating staff', [
                'id' => $id,
                'data' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while updating staff.',
                'data' => null
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Remove the specified staff from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            // Delete staff
            $result = $this->staffService->deleteStaff($id);
            
            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'data' => $result['data']
                ], JsonResponse::HTTP_BAD_REQUEST);
            }
            
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result['data']
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting staff', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while deleting staff.',
                'data' => null
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update staff license information.
     */
    public function updateLicense(Request $request, int $id): JsonResponse
    {
        try {
            // Validate request
            $validated = $request->validate([
                'license_number_encrypted' => 'required|string|max:512',
                'license_number_hash' => 'required|string|max:128',
                'issuing_state' => 'required|string|max:50',
                'expiry_date' => 'required|date|after:today',
            ]);
            
            // Update license
            $result = $this->staffService->updateLicenseInfo($id, $validated);
            
            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'data' => $result['data']
                ], JsonResponse::HTTP_BAD_REQUEST);
            }
            
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => new StaffResource($result['data'])
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors()
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            Log::error('Error updating staff license', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update license information.',
                'data' => null
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update staff employment status.
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        try {
            // Validate request
            $validated = $request->validate([
                'status' => 'required|in:active,on_leave,suspended,terminated,retired,credentialing_pending',
                'reason' => 'nullable|string|max:1000',
            ]);
            
            // Check authorization
            $staff = $this->staffService->getStaffById($id);
            if ($staff && !auth::user()->can('updateEmploymentStatus', $staff)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to update employment status.',
                    'data' => null
                ], JsonResponse::HTTP_FORBIDDEN);
            }
            
            // Update status
            $result = $this->staffService->updateEmploymentStatus(
                $id, 
                $validated['status'], 
                $validated['reason'] ?? null
            );
            
            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'data' => $result['data']
                ], JsonResponse::HTTP_BAD_REQUEST);
            }
            
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => new StaffResource($result['data'])
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors()
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            Log::error('Error updating staff status', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update employment status.',
                'data' => null
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get staff with expiring credentials.
     */
    public function expiringCredentials(Request $request): JsonResponse
    {
        try {
            // Check authorization
            if (!auth::user()->can('viewAny', \App\Models\Staff::class)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to view this information.',
                    'data' => null
                ], JsonResponse::HTTP_FORBIDDEN);
            }
            
            $days = $request->get('days', 30);
            $result = $this->staffService->getStaffWithExpiringCredentials($days);
            
            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'data' => $result['data']
                ], JsonResponse::HTTP_BAD_REQUEST);
            }
            
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result['data']
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting expiring credentials', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve expiring credentials.',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Validate staff action authorization.
     */
    public function validateAction(Request $request, int $id): JsonResponse
    {
        try {
            // Validate request
            $validated = $request->validate([
                'action' => 'required|string|in:prescribe_medication,supervise_others,access_confidential',
            ]);
            
            $result = $this->staffService->validateStaffAction($id, $validated['action']);
            
            return response()->json([
                'success' => $result['valid'],
                'message' => $result['message'],
                'data' => [
                    'valid' => $result['valid'],
                    'errors' => $result['errors']
                ]
            ], $result['valid'] ? JsonResponse::HTTP_OK : JsonResponse::HTTP_FORBIDDEN);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors()
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            Log::error('Error validating staff action', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to validate staff action.',
                'data' => null
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}