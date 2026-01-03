<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Facility\StoreFacilityRequest;
use App\Http\Requests\Facility\UpdateFacilityRequest;
use App\Http\Resources\FacilityResource;
use App\Http\Resources\FacilityCollection;
use App\Services\Contracts\FacilityServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Class FacilityController
 * 
 * API Controller for Facility management.
 * Thin orchestration layer that delegates to Service layer.
 */
class FacilityController extends Controller
{
    /**
     * @var FacilityServiceInterface
     */
    private FacilityServiceInterface $facilityService;

    /**
     * FacilityController constructor.
     *
     * @param FacilityServiceInterface $facilityService
     */
    public function __construct(FacilityServiceInterface $facilityService)
    {
        $this->facilityService = $facilityService;
    }

    /**
     * Display a listing of the facilities.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'facility_type',
                'facility_tier',
                'country_code',
                'state_province',
                'city',
                'operational_status',
                'data_residency_region',
                'has_emergency_department',
                'is_24_7',
                'search',
            ]);
            
            $perPage = $request->get('per_page', 15);
            
            $facilities = $this->facilityService->getPaginatedFacilities($perPage, $filters);
            
            return response()->json([
                'success' => true,
                'message' => 'Facilities retrieved successfully',
                'data' => new FacilityResource($facilities),
                'meta' => [
                    'total' => $facilities->total(),
                    'per_page' => $facilities->perPage(),
                    'current_page' => $facilities->currentPage(),
                    'last_page' => $facilities->lastPage(),
                    'from' => $facilities->firstItem(),
                    'to' => $facilities->lastItem(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve facilities list', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve facilities',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null
            ], 500);
        }
    }

    /**
     * Store a newly created facility in storage.
     *
     * @param StoreFacilityRequest $request
     * @return JsonResponse
     */
    public function store(StoreFacilityRequest $request): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            $createdByStaffId = $validatedData['user_id'];
            
            /*Note:For General Creation Use createFacility() method for Admin Facility Creation,use createFacilityByAdmin() in service files.
            $facility = $this->facilityService->createFacility($validatedData, $createdByStaffId);
            
        */
             $facility = $this->facilityService->createFacilityByAdmin($validatedData, $createdByStaffId);
            return response()->json([
                'success' => true,
                'message' => 'Facility created successfully',
                'data' => new FacilityResource($facility),
                'errors' => null
            ], 201);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Handle validation exceptions from service if any
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'data' => null,
                'errors' => $e->errors()
            ], 422);
            
        } catch (\Exception $e) {
            Log::error('Failed to create facility', [
                'error' => $e->getMessage(),
                'created_by_staff_id' => $validatedData['user_id'] ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create facility',
                'data' => null,
                'errors' => ['system' => ['An unexpected error occurred']]
            ], 500);
        }
    }

    /**
     * Display the specified facility.
     *
     * @param string $facility
     * @return JsonResponse
     */
    public function show(string $facility): JsonResponse
    {
        try {
            $facilityModel = $this->facilityService->getFacilityByUuid($facility);
            
            if (!$facilityModel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Facility not found',
                    'errors' => ['facility_uuid' => ['Facility not found']],
                    'data' => null
                ], 404);
            }
            
            // Authorize view action
            $this->authorize('view', $facilityModel);
            
            return response()->json([
                'success' => true,
                'message' => 'Facility retrieved successfully',
                'data' => new FacilityResource($facilityModel),
                'errors' => null
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve facility', [
                'error' => $e->getMessage(),
                'facility_uuid' => $facility,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve facility',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null
            ], 500);
        }
    }

    /**
     * Update the specified facility in storage.
     *
     * @param UpdateFacilityRequest $request
     * @param string $facility
     * @return JsonResponse
     */
    public function update(UpdateFacilityRequest $request, string $facility): JsonResponse
    {
        try {
            $facilityModel = $this->facilityService->getFacilityByUuid($facility);
            
            if (!$facilityModel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Facility not found',
                    'errors' => ['facility_uuid' => ['Facility not found']],
                    'data' => null
                ], 404);
            }
            
            $validatedData = $request->validated();
            $updatedByStaffId = $request->user()->id;
            
            $result = $this->facilityService->updateFacility($facilityModel->id, $validatedData, $updatedByStaffId);
            
            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => $result['errors'],
                    'data' => null
                ], 422);
            }
            
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => new FacilityResource($result['facility']),
                'errors' => null
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update facility', [
                'error' => $e->getMessage(),
                'facility_uuid' => $facility,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update facility',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null
            ], 500);
        }
    }

    /**
     * Remove the specified facility from storage (soft delete).
     *
     * @param string $facility
     * @return JsonResponse
     */
    public function destroy(string $facility): JsonResponse
    {
        try {
            $facilityModel = $this->facilityService->getFacilityByUuid($facility);
            
            if (!$facilityModel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Facility not found',
                    'errors' => ['facility_uuid' => ['Facility not found']],
                    'data' => null
                ], 404);
            }
            
            // Authorize delete action
            $this->authorize('delete', $facilityModel);
            
            $result = $this->facilityService->deleteFacility($facilityModel->id);
            
            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => $result['errors'],
                    'data' => null
                ], 422);
            }
            
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => null,
                'errors' => null
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete facility', [
                'error' => $e->getMessage(),
                'facility_uuid' => $facility,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete facility',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null
            ], 500);
        }
    }

    /**
     * Permanently delete the specified facility.
     *
     * @param string $facility
     * @return JsonResponse
     */
    public function forceDelete(string $facility): JsonResponse
    {
        try {
            $facilityModel = \App\Models\Facility::withTrashed()->where('facility_uuid', $facility)->first();
            
            if (!$facilityModel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Facility not found',
                    'errors' => ['facility_uuid' => ['Facility not found']],
                    'data' => null
                ], 404);
            }
            
            // Authorize force delete action
            $this->authorize('forceDelete', $facilityModel);
            
            $result = $this->facilityService->forceDeleteFacility($facilityModel->id);
            
            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => $result['errors'],
                    'data' => null
                ], 422);
            }
            
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => null,
                'errors' => null
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to force delete facility', [
                'error' => $e->getMessage(),
                'facility_uuid' => $facility,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to permanently delete facility',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null
            ], 500);
        }
    }

    /**
     * Restore the specified soft-deleted facility.
     *
     * @param string $facility
     * @return JsonResponse
     */
    public function restore(string $facility): JsonResponse
    {
        try {
            $facilityModel = \App\Models\Facility::withTrashed()->where('facility_uuid', $facility)->first();
            
            if (!$facilityModel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Facility not found',
                    'errors' => ['facility_uuid' => ['Facility not found']],
                    'data' => null
                ], 404);
            }
            
            // Authorize restore action
            $this->authorize('restore', $facilityModel);
            
            $result = $this->facilityService->restoreFacility($facilityModel->id);
            
            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => $result['errors'],
                    'data' => null
                ], 422);
            }
            
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => new FacilityResource($result['facility'] ?? $facilityModel->fresh()),
                'errors' => null
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to restore facility', [
                'error' => $e->getMessage(),
                'facility_uuid' => $facility,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to restore facility',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null
            ], 500);
        }
    }

    /**
     * Search facilities by name or code.
     *
     * @param Request $request
     * @param string $query
     * @return JsonResponse
     */
    public function search(Request $request, string $query): JsonResponse
    {
        try {
            $limit = $request->get('limit', 10);
            
            $facilities = $this->facilityService->searchFacilities($query, $limit);
            
            return response()->json([
                'success' => true,
                'message' => 'Facilities search completed',
                'data' => FacilityResource::collection($facilities),
                'meta' => [
                    'query' => $query,
                    'limit' => $limit,
                    'count' => $facilities->count(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to search facilities', [
                'error' => $e->getMessage(),
                'query' => $query,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to search facilities',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null
            ], 500);
        }
    }

    /**
     * Get facilities by location.
     *
     * @param string $country_code
     * @param string|null $state_province
     * @param string|null $city
     * @return JsonResponse
     */
    public function byLocation(string $country_code, ?string $state_province = null, ?string $city = null): JsonResponse
    {
        try {
            $facilities = $this->facilityService->getFacilitiesByLocation($country_code, $state_province, $city);
            
            return response()->json([
                'success' => true,
                'message' => 'Facilities by location retrieved',
                'data' => FacilityResource::collection($facilities),
                'meta' => [
                    'country_code' => $country_code,
                    'state_province' => $state_province,
                    'city' => $city,
                    'count' => $facilities->count(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get facilities by location', [
                'error' => $e->getMessage(),
                'country_code' => $country_code,
                'state_province' => $state_province,
                'city' => $city,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get facilities by location',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null
            ], 500);
        }
    }

    /**
     * Get facilities by type and status.
     *
     * @param string $type
     * @param string $status
     * @return JsonResponse
     */
    public function byTypeAndStatus(string $type, string $status): JsonResponse
    {
        try {
            $facilities = $this->facilityService->getFacilitiesByTypeAndStatus($type, $status);
            
            return response()->json([
                'success' => true,
                'message' => 'Facilities by type and status retrieved',
                'data' => FacilityResource::collection($facilities),
                'meta' => [
                    'type' => $type,
                    'status' => $status,
                    'count' => $facilities->count(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get facilities by type and status', [
                'error' => $e->getMessage(),
                'type' => $type,
                'status' => $status,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get facilities by type and status',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null
            ], 500);
        }
    }

    /**
     * Get facilities with emergency departments.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function withEmergencyDepartments(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'country_code',
                'state_province',
                'city',
                'operational_status',
            ]);
            
            $facilities = $this->facilityService->getFacilitiesWithEmergencyDepartments($filters);
            
            return response()->json([
                'success' => true,
                'message' => 'Facilities with emergency departments retrieved',
                'data' => FacilityResource::collection($facilities),
                'meta' => [
                    'count' => $facilities->count(),
                    'filters' => $filters,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get facilities with emergency departments', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get facilities with emergency departments',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null
            ], 500);
        }
    }

    /**
     * Update facility metrics.
     *
     * @param Request $request
     * @param string $facility
     * @return JsonResponse
     */
    public function updateMetrics(Request $request, string $facility): JsonResponse
    {
        try {
            $facilityModel = $this->facilityService->getFacilityByUuid($facility);
            
            if (!$facilityModel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Facility not found',
                    'errors' => ['facility_uuid' => ['Facility not found']],
                    'data' => null
                ], 404);
            }
            
            // Authorize metrics update action
            $this->authorize('updateMetrics', $facilityModel);
            
            $validatedData = $request->validate([
                'average_wait_time_minutes' => 'nullable|numeric|min:0|max:999.99',
                'patient_satisfaction_score' => 'nullable|numeric|min:0|max:5',
                'monthly_patient_volume' => 'nullable|integer|min:0',
                'metrics_updated_at' => 'nullable|date',
            ]);
            
            $result = $this->facilityService->updateFacilityMetrics($facilityModel->id, $validatedData);
            
            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => $result['errors'],
                    'data' => null
                ], 422);
            }
            
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => new FacilityResource($result['facility']),
                'errors' => null
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update facility metrics', [
                'error' => $e->getMessage(),
                'facility_uuid' => $facility,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update facility metrics',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null
            ], 500);
        }
    }

    /**
     * Check facility operational status.
     *
     * @param string $facility
     * @return JsonResponse
     */
    public function operationalStatus(string $facility): JsonResponse
    {
        try {
            $facilityModel = $this->facilityService->getFacilityByUuid($facility);
            
            if (!$facilityModel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Facility not found',
                    'errors' => ['facility_uuid' => ['Facility not found']],
                    'data' => null
                ], 404);
            }
            
            // Authorize view operational status action
            $this->authorize('viewOperationalStatus', $facilityModel);
            
            $result = $this->facilityService->checkFacilityOperationalStatus($facilityModel->id);
            
            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => null,
                    'data' => $result['data']
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result['data'],
                'errors' => null
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to check facility operational status', [
                'error' => $e->getMessage(),
                'facility_uuid' => $facility,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to check operational status',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null
            ], 500);
        }
    }

    /**
     * Update facility operational status.
     *
     * @param Request $request
     * @param string $facility
     * @return JsonResponse
     */
    public function updateOperationalStatus(Request $request, string $facility): JsonResponse
    {
        try {
            $facilityModel = $this->facilityService->getFacilityByUuid($facility);
            
            if (!$facilityModel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Facility not found',
                    'errors' => ['facility_uuid' => ['Facility not found']],
                    'data' => null
                ], 404);
            }
            
            // Authorize update operational status action
            $this->authorize('updateOperationalStatus', $facilityModel);
            
            $validatedData = $request->validate([
                'operational_status' => 'required|in:fully_operational,limited_services,emergency_only,temporarily_closed,permanently_closed,under_construction',
                'status_change_reason' => 'nullable|string|max:500',
            ]);
            
            $result = $this->facilityService->updateFacility($facilityModel->id, [
                'operational_status' => $validatedData['operational_status'],
                'metadata' => array_merge($facilityModel->metadata ?? [], [
                    'status_change' => [
                        'previous_status' => $facilityModel->operational_status,
                        'new_status' => $validatedData['operational_status'],
                        'changed_at' => now()->toIso8601String(),
                        'changed_by' => $request->user()->id,
                        'reason' => $validatedData['status_change_reason'] ?? null,
                    ]
                ])
            ], $request->user()->id);
            
            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'errors' => $result['errors'],
                    'data' => null
                ], 422);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Operational status updated successfully',
                'data' => new FacilityResource($result['facility']),
                'errors' => null
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update facility operational status', [
                'error' => $e->getMessage(),
                'facility_uuid' => $facility,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update operational status',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null
            ], 500);
        }
    }
}