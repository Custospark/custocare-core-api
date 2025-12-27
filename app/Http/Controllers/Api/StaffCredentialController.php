<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StaffCredential\StoreStaffCredentialRequest;
use App\Http\Requests\StaffCredential\UpdateStaffCredentialRequest;
use App\Http\Resources\StaffCredentialResource;
use App\Services\Contracts\StaffCredentialServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class StaffCredentialController extends Controller
{
    /**
     * Service instance
     *
     * @var StaffCredentialServiceInterface
     */
    protected StaffCredentialServiceInterface $service;

    /**
     * Constructor
     *
     * @param StaffCredentialServiceInterface $service
     */
    public function __construct(StaffCredentialServiceInterface $service)
    {
        $this->service = $service;
        
        // Apply middleware
        // $this->middleware('auth:api');
        
        // Apply policy
        $this->authorizeResource(\App\Models\StaffCredential::class, 'credential', [
            'except' => ['expiring', 'expired', 'statistics', 'verify', 'supersede']
        ]);
    }

    /**
     * Display a listing of credentials.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'staff_id', 
                'credential_type', 
                'verification_status', 
                'is_current',
                'search',
                'order_by',
                'order_direction'
            ]);

            $perPage = $request->input('per_page', 15);
            
            $result = $this->service->searchCredentials($filters, $perPage);
            
            if (!$result['success']) {
                return response()->json($result, $result['status']);
            }

            // Transform data using resource
            $transformedData = StaffCredentialResource::collection($result['data']);
            
            // Add pagination metadata
            $responseData = [
                'success' => true,
                'message' => $result['message'],
                'data' => $transformedData,
                'meta' => $result['pagination'] ?? null,
            ];

            return response()->json($responseData, $result['status']);
        } catch (\Exception $e) {
          Log::error('Error in StaffCredentialController@index', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again later.',
                'data' => null,
                'status' => 500
            ], 500);
        }
    }

    /**
     * Store a newly created credential.
     *
     * @param StoreStaffCredentialRequest $request
     * @return JsonResponse
     */
    public function store(StoreStaffCredentialRequest $request): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            
            $result = $this->service->createCredential($validatedData);
            
            if (!$result['success']) {
                return response()->json($result, $result['status']);
            }

            // Transform data using resource
            $transformedData = new StaffCredentialResource($result['data']);
            
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $transformedData,
            ], $result['status']);
        } catch (\Exception $e) {
          Log::error('Error in StaffCredentialController@store', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create credential. Please try again later.',
                'data' => null,
                'status' => 500
            ], 500);
        }
    }

    /**
     * Display the specified credential.
     *
     * @param string $credentialUuid
     * @return JsonResponse
     */
    public function show(string $credentialUuid): JsonResponse
    {
        try {
            $result = $this->service->getCredential($credentialUuid);
            
            if (!$result['success']) {
                return response()->json($result, $result['status']);
            }

            // Transform data using resource
            $transformedData = new StaffCredentialResource($result['data']);
            
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $transformedData,
            ], $result['status']);
        } catch (\Exception $e) {
          Log::error('Error in StaffCredentialController@show', [
                'credential_uuid' => $credentialUuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve credential. Please try again later.',
                'data' => null,
                'status' => 500
            ], 500);
        }
    }

    /**
     * Update the specified credential.
     *
     * @param UpdateStaffCredentialRequest $request
     * @param string $credentialUuid
     * @return JsonResponse
     */
    public function update(UpdateStaffCredentialRequest $request, string $credentialUuid): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            
            $result = $this->service->updateCredential($credentialUuid, $validatedData);
            
            if (!$result['success']) {
                return response()->json($result, $result['status']);
            }

            // Transform data using resource
            $transformedData = new StaffCredentialResource($result['data']);
            
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $transformedData,
            ], $result['status']);
        } catch (\Exception $e) {
          Log::error('Error in StaffCredentialController@update', [
                'credential_uuid' => $credentialUuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update credential. Please try again later.',
                'data' => null,
                'status' => 500
            ], 500);
        }
    }

    /**
     * Remove the specified credential.
     *
     * @param string $credentialUuid
     * @return JsonResponse
     */
    public function destroy(string $credentialUuid): JsonResponse
    {
        try {
            $result = $this->service->deleteCredential($credentialUuid);
            
            return response()->json($result, $result['status']);
        } catch (\Exception $e) {
          Log::error('Error in StaffCredentialController@destroy', [
                'credential_uuid' => $credentialUuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete credential. Please try again later.',
                'data' => null,
                'status' => 500
            ], 500);
        }
    }

    /**
     * Verify a credential.
     *
     * @param Request $request
     * @param string $credentialUuid
     * @return JsonResponse
     */
    public function verify(Request $request, string $credentialUuid): JsonResponse
    {
        try {
            // Check authorization
            $credential = \App\Models\StaffCredential::where('credential_uuid', $credentialUuid)->firstOrFail();
            $this->authorize('verify', $credential);

            $validatedData = $request->validate([
                'verification_method' => 'required|string|in:primary_source,database_check,document_review',
                'verification_notes' => 'nullable|string|max:1000',
            ]);

            $verifyingStaffId = Auth::id();
            
            $result = $this->service->verifyCredential($credentialUuid, $validatedData, $verifyingStaffId);
            
            if (!$result['success']) {
                return response()->json($result, $result['status']);
            }

            // Transform data using resource
            $transformedData = new StaffCredentialResource($result['data']);
            
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $transformedData,
            ], $result['status']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'status' => 422
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Credential not found',
                'data' => null,
                'status' => 404
            ], 404);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
                'status' => 403
            ], 403);
        } catch (\Exception $e) {
          Log::error('Error in StaffCredentialController@verify', [
                'credential_uuid' => $credentialUuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to verify credential. Please try again later.',
                'data' => null,
                'status' => 500
            ], 500);
        }
    }

    /**
     * Supersede a credential with a new one.
     *
     * @param StoreStaffCredentialRequest $request
     * @param string $credentialUuid
     * @return JsonResponse
     */
    public function supersede(StoreStaffCredentialRequest $request, string $credentialUuid): JsonResponse
    {
        try {
            // Check authorization
            $credential = \App\Models\StaffCredential::where('credential_uuid', $credentialUuid)->firstOrFail();
            $this->authorize('supersede', $credential);

            $validatedData = $request->validated();
            $createdByStaffId = Auth::id();
            
            $result = $this->service->supersedeCredential($credentialUuid, $validatedData, $createdByStaffId);
            
            if (!$result['success']) {
                return response()->json($result, $result['status']);
            }

            // Transform data using resource
            $transformedData = [
                'old_credential' => new StaffCredentialResource($result['data']['old_credential']),
                'new_credential' => new StaffCredentialResource($result['data']['new_credential']),
            ];
            
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $transformedData,
            ], $result['status']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Credential not found',
                'data' => null,
                'status' => 404
            ], 404);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
                'status' => 403
            ], 403);
        } catch (\Exception $e) {
          Log::error('Error in StaffCredentialController@supersede', [
                'credential_uuid' => $credentialUuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to supersede credential. Please try again later.',
                'data' => null,
                'status' => 500
            ], 500);
        }
    }

    /**
     * Get expiring credentials.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function expiring(Request $request): JsonResponse
    {
        try {
            // Check authorization
            $this->authorize('viewExpiring', \App\Models\StaffCredential::class);

            $days = $request->input('days', 30);
            
            $result = $this->service->getExpiringCredentials($days);
            
            if (!$result['success']) {
                return response()->json($result, $result['status']);
            }

            // Transform data using resource
            $transformedData = StaffCredentialResource::collection($result['data']);
            
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $transformedData,
                'count' => $result['count'],
            ], $result['status']);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
                'status' => 403
            ], 403);
        } catch (\Exception $e) {
          Log::error('Error in StaffCredentialController@expiring', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve expiring credentials. Please try again later.',
                'data' => null,
                'status' => 500
            ], 500);
        }
    }

    /**
     * Get expired credentials.
     *
     * @return JsonResponse
     */
    public function expired(): JsonResponse
    {
        try {
            // Check authorization (same as expiring)
            $this->authorize('viewExpiring', \App\Models\StaffCredential::class);

            $result = $this->service->getExpiredCredentials();
            
            if (!$result['success']) {
                return response()->json($result, $result['status']);
            }

            // Transform data using resource
            $transformedData = StaffCredentialResource::collection($result['data']);
            
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $transformedData,
                'count' => $result['count'],
            ], $result['status']);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
                'status' => 403
            ], 403);
        } catch (\Exception $e) {
          Log::error('Error in StaffCredentialController@expired', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve expired credentials. Please try again later.',
                'data' => null,
                'status' => 500
            ], 500);
        }
    }

    /**
     * Get credential statistics.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            // Check authorization
            $this->authorize('viewStatistics', \App\Models\StaffCredential::class);

            $staffId = $request->input('staff_id');
            
            $result = $this->service->getStatistics($staffId);
            
            return response()->json($result, $result['status']);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
                'status' => 403
            ], 403);
        } catch (\Exception $e) {
            Log::error('Error in StaffCredentialController@statistics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics. Please try again later.',
                'data' => null,
                'status' => 500
            ], 500);
        }
    }

    /**
     * Get credentials for a specific staff member.
     *
     * @param int $staffId
     * @param Request $request
     * @return JsonResponse
     */
    public function staffCredentials(int $staffId, Request $request): JsonResponse
    {
        try {
            // Check if user can view this staff's credentials
            $this->authorize('viewAny', \App\Models\StaffCredential::class);

            $filters = $request->only(['credential_type', 'verification_status', 'is_current']);
            $filters['staff_id'] = $staffId;
            
            $result = $this->service->getStaffCredentials($staffId, $filters);
            
            if (!$result['success']) {
                return response()->json($result, $result['status']);
            }

            // Transform data using resource
            $transformedData = StaffCredentialResource::collection($result['data']);
            
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $transformedData,
            ], $result['status']);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
                'status' => 403
            ], 403);
        } catch (\Exception $e) {
          Log::error('Error in StaffCredentialController@staffCredentials', [
                'staff_id' => $staffId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve staff credentials. Please try again later.',
                'data' => null,
                'status' => 500
            ], 500);
        }
    }
}