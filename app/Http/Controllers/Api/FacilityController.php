<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Facility\StoreFacilityRequest;
use App\Http\Requests\Facility\UpdateFacilityRequest;
use App\Http\Requests\Facility\UpdateFacilitySettingsRequest;
use App\Http\Resources\FacilityResource;
use App\Http\Resources\FacilityCollection;
use App\Models\Facility;
use App\Services\Contracts\FacilityServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
     * Get facility identity information using facility ID from header.
     * Returns essential facility identity fields including address information.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getFacilityDetails(Request $request): JsonResponse
    {
        try {
            // Get facility ID from header
            $facilityId = $request->header('X-Facility-ID');
            
            // Validate if facility ID is provided in header
            if (!$facilityId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Facility ID not provided in header',
                    'errors' => ['header' => ['X-Facility-ID header is required']],
                    'data' => null
                ], 400);
            }
            
            // Validate that facility ID is numeric
            if (!is_numeric($facilityId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid facility ID format',
                    'errors' => ['facility_id' => ['Facility ID must be a number']],
                    'data' => null
                ], 400);
            }
            
            // Direct database query to get essential identity fields including address
            $facility = DB::table('facilities')
                ->where('id', $facilityId)
                ->whereNull('deleted_at')
                ->select([
                    'id',
                    'facility_uuid',
                    'facility_code',
                    'facility_name',
                    'legal_entity_name',
                    'facility_type',
                    'facility_tier',
                    'operational_status',
                    'main_phone',
                    'email',
                    // Address fields
                    'address_line1',
                    'address_line2',
                    'city',
                    'state_province',
                    'postal_code',
                    'country_code'
                ])
                ->first();
            
            // Check if facility exists
            if (!$facility) {
                return response()->json([
                    'success' => false,
                    'message' => 'Facility not found',
                    'errors' => ['facility_id' => ['Facility not found with ID: ' . $facilityId]],
                    'data' => null
                ], 404);
            }
            
            // Construct full address
            $fullAddress = $facility->address_line1;
            if ($facility->address_line2) {
                $fullAddress .= ', ' . $facility->address_line2;
            }
            $fullAddress .= ', ' . $facility->city;
            $fullAddress .= ', ' . $facility->state_province;
            $fullAddress .= ' ' . $facility->postal_code;
            $fullAddress .= ', ' . $facility->country_code;
            
            // Log the access for audit purposes
            Log::info('Facility identity details accessed via header', [
                'facility_id' => $facilityId,
                'facility_uuid' => $facility->facility_uuid,
                'facility_name' => $facility->facility_name,
                'user_id' => $request->user()?->id
            ]);
            
            // Return facility identity information with address
            return response()->json([
                'success' => true,
                'message' => 'Facility identity details retrieved successfully',
                'data' => [
                    'facility' => [
                        // Identity fields
                        'id' => $facility->id,
                        'uuid' => $facility->facility_uuid,
                        'code' => $facility->facility_code,
                        'name' => $facility->facility_name,
                        'legal_name' => $facility->legal_entity_name,
                        'type' => $facility->facility_type,
                        'tier' => $facility->facility_tier,
                        'status' => $facility->operational_status,
                        'phone' => $facility->main_phone,
                        'email' => $facility->email,
                        // Address fields
                        'address' => [
                            'line1' => $facility->address_line1,
                            'line2' => $facility->address_line2,
                            'city' => $facility->city,
                            'state' => $facility->state_province,
                            'postal_code' => $facility->postal_code,
                            'country' => $facility->country_code,
                            'formatted' => $fullAddress
                        ]
                    ],
                    'retrieved_via' => 'header',
                    'header_used' => 'X-Facility-ID',
                    'timestamp' => now()->toIso8601String()
                ],
                'errors' => null
            ]);
            
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('Database error while retrieving facility identity details', [
                'error' => $e->getMessage(),
                'facility_id' => $request->header('X-Facility-ID')
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Database error occurred',
                'errors' => ['database' => ['Failed to retrieve facility details']],
                'data' => null
            ], 500);
            
        } catch (\Exception $e) {
            Log::error('Failed to retrieve facility identity details from header', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'facility_id' => $request->header('X-Facility-ID')
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve facility details',
                'errors' => ['system' => ['An unexpected error occurred']],
                'data' => null
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
            // $this->authorize('view', $facilityModel); //TODO:Implement the functionality in the future.
            
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


    // ══════════════════════════════════════════════════════════════════════════
    // FACILITY SETTINGS
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * GET /facilities/{facility}/settings
     *
     * Retrieve all editable settings for the given facility, returned as a
     * structured object keyed by logical group (CoreIdentity, Location, …).
     *
     * Fields that cannot be changed through settings (e.g. facility_uuid,
     * facility_code, shard columns, audit stamps) are intentionally absent.
     *
     * @param ReadFacilitySettingsRequest $request
     * @param Facility $facility
     * @return JsonResponse
     */
    public function getSettings(Facility $facility): JsonResponse
    {
        try {
            $settings = $this->facilityService->getFacilitySettings($facility);

            return response()->json([
                'success' => true,
                'message' => 'Facility settings retrieved successfully.',
                'data'    => $settings,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve facility settings.',
                'errors'  => ['server' => [$e->getMessage()]],
                'data'    => null,
            ], 500);
        }
    }

    /**
     * PUT /facilities/{facility}/settings
     *
     * Update one or more editable settings fields for the given facility.
     * The request body is flat (individual fields, not grouped objects).
     * Only fields explicitly sent in the request are updated; all others
     * remain unchanged.
     *
     * The response returns the full grouped settings snapshot after the update,
     * matching exactly what GET /settings returns.
     *
     * @param UpdateFacilitySettingsRequest $request
     * @param Facility $facility
     * @return JsonResponse
     */
    public function updateSettings(UpdateFacilitySettingsRequest $request, Facility $facility): JsonResponse
    {
        try {
            $updated = $this->facilityService->updateFacilitySettings($facility, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Facility settings updated successfully.',
                'data'    => $this->facilityService->getFacilitySettings($updated),
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update facility settings.',
                'errors'  => ['server' => [$e->getMessage()]],
                'data'    => null,
            ], 500);
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // FACILITY LOGO
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * POST /facilities/{facility}/settings/logo
     *
     * Upload (or replace) the logo image for the given facility.
     * The service layer handles:
     *   – deleting the existing logo from storage if one is already set,
     *   – storing the new file under facility-logos/{id}/,
     *   – persisting the resulting path to facility_logo_path on the model.
     *
     * The response returns both the raw storage path and a fully-qualified
     * public URL so the client does not need to build the URL itself.
     *
     * @param Request  $request
     * @param Facility $facility
     * @return JsonResponse
     */
    public function uploadFacilityLogo(Request $request, Facility $facility): JsonResponse
    {
        try {
            $request->validate([
                'logo' => 'required|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            ]);

            $logoPath = $this->facilityService->uploadFacilityLogo($facility, $request->file('logo'));

            return response()->json([
                'success' => true,
                'message' => 'Facility logo uploaded successfully.',
                'data'    => [
                    'facility_logo_path' => $logoPath,
                    'facility_logo_url'  => asset('storage/' . $logoPath),
                ],
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Logo upload validation failed.',
                'errors'  => $e->errors(),
                'data'    => null,
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload facility logo.',
                'errors'  => ['server' => [$e->getMessage()]],
                'data'    => null,
            ], 500);
        }
    }

}