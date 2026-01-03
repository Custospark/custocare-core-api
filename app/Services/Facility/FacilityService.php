<?php

namespace App\Services\Facility;

use App\Models\Facility;
use App\Repositories\Contracts\FacilityRepositoryInterface;
use App\Repositories\Contracts\FacilityStaffRoleRepositoryInterface;
use App\Services\Contracts\FacilityServiceInterface;
use App\Services\Contracts\FacilityStaffRoleServiceInterface;
use App\Support\HealthcareIdGenerator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Class FacilityService
 * 
 * Implementation of FacilityServiceInterface.
 * Handles all business logic for Facility entity.
 */
class FacilityService implements FacilityServiceInterface
{
    /**
     * Cache TTL in seconds (5 minutes for reference data)
     */
    private const CACHE_TTL = 300;
    protected FacilityRepositoryInterface $facilityRepository;
    protected FacilityStaffRoleServiceInterface $facilityStaffRoleService;

    public function __construct(
        FacilityRepositoryInterface $facilityRepository,
        FacilityStaffRoleServiceInterface $facilityStaffRoleService
    ) {
        $this->facilityRepository = $facilityRepository;
        $this->facilityStaffRoleService = $facilityStaffRoleService;
    }
    /**
     * Get all facilities with optional filters.
     *
     * @param array $filters
     * @return Collection
     */
    public function getAllFacilities(array $filters = []): Collection
    {
        try {
            $cacheKey = $this->generateCacheKey('all_facilities', $filters);
            
            return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($filters) {
                return $this->facilityRepository->getAll($filters, ['parentOrganization', 'createdBy', 'updatedBy']);
            });
        } catch (\Exception $e) {
            Log::error('Failed to get all facilities in service', [
                'error' => $e->getMessage(),
                'filters' => $filters,
                'trace' => $e->getTraceAsString()
            ]);
            
            return new Collection();
        }
    }

    /**
     * Get paginated facilities with optional filters.
     *
     * @param int $perPage
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getPaginatedFacilities(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        try {
            $cacheKey = $this->generateCacheKey('paginated_facilities', array_merge($filters, ['perPage' => $perPage]));
            
            return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($perPage, $filters) {
                return $this->facilityRepository->getPaginated($perPage, $filters, ['parentOrganization', 'createdBy', 'updatedBy']);
            });
        } catch (\Exception $e) {
            Log::error('Failed to get paginated facilities in service', [
                'error' => $e->getMessage(),
                'perPage' => $perPage,
                'filters' => $filters,
                'trace' => $e->getTraceAsString()
            ]);
            
            return new LengthAwarePaginator([], 0, $perPage);
        }
    }

    /**
     * Get facility by ID.
     *
     * @param int $id
     * @return Facility|null
     */
    public function getFacilityById(int $id): ?Facility
    {
        try {
            $cacheKey = $this->generateCacheKey('facility_by_id', ['id' => $id]);
            
            return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($id) {
                return $this->facilityRepository->findById($id, ['parentOrganization', 'createdBy', 'updatedBy']);
            });
        } catch (\Exception $e) {
            Log::error('Failed to get facility by ID in service', [
                'error' => $e->getMessage(),
                'facility_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return null;
        }
    }

    /**
     * Get facility by UUID.
     *
     * @param string $uuid
     * @return Facility|null
     */
    public function getFacilityByUuid(string $uuid): ?Facility
    {
        try {
            $cacheKey = $this->generateCacheKey('facility_by_uuid', ['uuid' => $uuid]);
            
            return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($uuid) {
                return $this->facilityRepository->findByUuid($uuid, ['parentOrganization', 'createdBy', 'updatedBy']);
            });
        } catch (\Exception $e) {
            Log::error('Failed to get facility by UUID in service', [
                'error' => $e->getMessage(),
                'facility_uuid' => $uuid,
                'trace' => $e->getTraceAsString()
            ]);
            
            return null;
        }
    }

    /**
     * Get facility by code.
     *
     * @param string $facilityCode
     * @return Facility|null
     */
    public function getFacilityByCode(string $facilityCode): ?Facility
    {
        try {
            $cacheKey = $this->generateCacheKey('facility_by_code', ['code' => $facilityCode]);
            
            return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($facilityCode) {
                return $this->facilityRepository->findByCode($facilityCode, ['parentOrganization', 'createdBy', 'updatedBy']);
            });
        } catch (\Exception $e) {
            Log::error('Failed to get facility by code in service', [
                'error' => $e->getMessage(),
                'facility_code' => $facilityCode,
                'trace' => $e->getTraceAsString()
            ]);
            
            return null;
        }
    }

    /**
     * Create a new facility.
     *
     * @param array $data
     * @param int $createdByStaffId
     * @return array
     */
    public function createFacility(array $data, int $createdByStaffId): ?Facility
    {  
        // Generate IDs
        $data['facility_uuid'] = HealthcareIdGenerator::generate('facility');
        $data['facility_code'] = HealthcareIdGenerator::generateRandomCode('HFC');
        
        // Add created_by_staff_id
        $data['created_by_staff_id'] = $createdByStaffId;
        $data['updated_by_staff_id'] = $createdByStaffId;
        
        // Ensure UUID is generated if not provided (fallback)
        if (empty($data['facility_uuid'])) {
            $data['facility_uuid'] = (string) \Illuminate\Support\Str::uuid();
        }
        
        // Create facility
        $facility = $this->facilityRepository->create($data);
        
        return $facility;
    }


    /**
     * Create Facility By Admin at UI Accountion
     */

 
    public function createFacilityByAdmin(array $data, int $createdByStaffId): Facility
    {
        return DB::transaction(function () use ($data, $createdByStaffId) {

            // Generate identifiers
            $data['facility_uuid'] ??= HealthcareIdGenerator::generateRandomCode();
            $data['facility_code'] ??= HealthcareIdGenerator::generate('facility');

            // Audit fields
            $data['created_by_staff_id'] = $createdByStaffId;
            $data['updated_by_staff_id'] = $createdByStaffId;

            // Fallback UUID safety
            // if (empty($data['facility_uuid'])) {
            //     $data['facility_uuid'] = (string) Str::uuid();
            // }

            // 1️⃣ Create Facility
            $facility = $this->facilityRepository->create($data);

            // 2️⃣ Auto-assign Facility Administrator role (SYSTEM RULE)
            $this->facilityStaffRoleService->createAssignment([
                'facility_id' => $facility->id,
                'staff_id' => $createdByStaffId,

                'role_code' => 'facility_administrator',

                'department_ids' => [],

                'is_primary_facility' => true,
                'effective_from' => now()->toDateString(),

                'created_by_staff_id' => $createdByStaffId,

                'metadata' => [
                    'assigned_by' => 'system',
                    'assignment_reason' => 'facility_creator'
                ]
            ]);

            return $facility;
        });
    }


    

    /**
     * Update an existing facility.
     *
     * @param int $id
     * @param array $data
     * @param int $updatedByStaffId
     * @return array
     */
    public function updateFacility(int $id, array $data, int $updatedByStaffId): array
    {
        try {
            // Check if facility exists
            $existingFacility = $this->getFacilityById($id);
            
            if (!$existingFacility) {
                return [
                    'success' => false,
                    'message' => 'Facility not found',
                    'errors' => ['id' => ['Facility with ID ' . $id . ' not found']],
                    'facility' => null
                ];
            }
            
            // Validate input data
            $validationResult = $this->validateFacilityData($data, $id);
            
            if (!$validationResult['valid']) {
                return [
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validationResult['errors'],
                    'facility' => null
                ];
            }
            
            // Add updated_by_staff_id
            $data['updated_by_staff_id'] = $updatedByStaffId;
            
            // Update facility
            $facility = $this->facilityRepository->update($id, $data);
            
            // Clear relevant caches
            $this->clearFacilityCaches();
            $this->clearFacilitySpecificCaches($id);
            
            return [
                'success' => true,
                'message' => 'Facility updated successfully',
                'facility' => $facility,
                'errors' => null
            ];
        } catch (\Exception $e) {
            Log::error('Failed to update facility in service', [
                'error' => $e->getMessage(),
                'facility_id' => $id,
                'updated_by_staff_id' => $updatedByStaffId,
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to update facility: ' . $e->getMessage(),
                'errors' => ['system' => ['An unexpected error occurred']],
                'facility' => null
            ];
        }
    }

    /**
     * Delete a facility (soft delete).
     *
     * @param int $id
     * @return array
     */
    public function deleteFacility(int $id): array
    {
        try {
            // Check if facility exists
            $existingFacility = $this->getFacilityById($id);
            
            if (!$existingFacility) {
                return [
                    'success' => false,
                    'message' => 'Facility not found',
                    'errors' => ['id' => ['Facility with ID ' . $id . ' not found']]
                ];
            }
            
            // Check if facility is already deleted
            if ($existingFacility->trashed()) {
                return [
                    'success' => false,
                    'message' => 'Facility is already deleted',
                    'errors' => ['id' => ['Facility is already deleted']]
                ];
            }
            
            // Delete facility
            $deleted = $this->facilityRepository->delete($id);
            
            if (!$deleted) {
                return [
                    'success' => false,
                    'message' => 'Failed to delete facility',
                    'errors' => ['system' => ['Failed to delete facility']]
                ];
            }
            
            // Clear relevant caches
            $this->clearFacilityCaches();
            $this->clearFacilitySpecificCaches($id);
            
            return [
                'success' => true,
                'message' => 'Facility deleted successfully',
                'errors' => null
            ];
        } catch (\Exception $e) {
            Log::error('Failed to delete facility in service', [
                'error' => $e->getMessage(),
                'facility_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to delete facility: ' . $e->getMessage(),
                'errors' => ['system' => ['An unexpected error occurred']]
            ];
        }
    }

    /**
     * Force delete a facility.
     *
     * @param int $id
     * @return array
     */
    public function forceDeleteFacility(int $id): array
    {
        try {
            // Check if facility exists (including trashed)
            $existingFacility = Facility::withTrashed()->find($id);
            
            if (!$existingFacility) {
                return [
                    'success' => false,
                    'message' => 'Facility not found',
                    'errors' => ['id' => ['Facility with ID ' . $id . ' not found']]
                ];
            }
            
            // Force delete facility
            $deleted = $this->facilityRepository->forceDelete($id);
            
            if (!$deleted) {
                return [
                    'success' => false,
                    'message' => 'Failed to permanently delete facility',
                    'errors' => ['system' => ['Failed to permanently delete facility']]
                ];
            }
            
            // Clear relevant caches
            $this->clearFacilityCaches();
            
            return [
                'success' => true,
                'message' => 'Facility permanently deleted successfully',
                'errors' => null
            ];
        } catch (\Exception $e) {
            Log::error('Failed to force delete facility in service', [
                'error' => $e->getMessage(),
                'facility_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to permanently delete facility: ' . $e->getMessage(),
                'errors' => ['system' => ['An unexpected error occurred']]
            ];
        }
    }

    /**
     * Restore a soft-deleted facility.
     *
     * @param int $id
     * @return array
     */
    public function restoreFacility(int $id): array
    {
        try {
            // Check if facility exists (including trashed)
            $existingFacility = Facility::withTrashed()->find($id);
            
            if (!$existingFacility) {
                return [
                    'success' => false,
                    'message' => 'Facility not found',
                    'errors' => ['id' => ['Facility with ID ' . $id . ' not found']]
                ];
            }
            
            // Check if facility is not deleted
            if (!$existingFacility->trashed()) {
                return [
                    'success' => false,
                    'message' => 'Facility is not deleted',
                    'errors' => ['id' => ['Facility is not deleted']]
                ];
            }
            
            // Restore facility
            $restored = $this->facilityRepository->restore($id);
            
            if (!$restored) {
                return [
                    'success' => false,
                    'message' => 'Failed to restore facility',
                    'errors' => ['system' => ['Failed to restore facility']]
                ];
            }
            
            // Clear relevant caches
            $this->clearFacilityCaches();
            $this->clearFacilitySpecificCaches($id);
            
            return [
                'success' => true,
                'message' => 'Facility restored successfully',
                'errors' => null
            ];
        } catch (\Exception $e) {
            Log::error('Failed to restore facility in service', [
                'error' => $e->getMessage(),
                'facility_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to restore facility: ' . $e->getMessage(),
                'errors' => ['system' => ['An unexpected error occurred']]
            ];
        }
    }

    /**
     * Validate facility data.
     *
     * @param array $data
     * @param int|null $excludeId
     * @return array
     */
    public function validateFacilityData(array $data, ?int $excludeId = null): array
    {
        try {
            // Get validation rules from model
            $rules = Facility::rules();
            
            // Adjust unique rules for update
            if ($excludeId) {
                if (isset($rules['facility_uuid'])) {
                    $rules['facility_uuid'] = str_replace(
                        'unique:facilities,facility_uuid',
                        'unique:facilities,facility_uuid,' . $excludeId,
                        $rules['facility_uuid']
                    );
                }
                
                if (isset($rules['facility_code'])) {
                    $rules['facility_code'] = str_replace(
                        'unique:facilities,facility_code',
                        'unique:facilities,facility_code,' . $excludeId,
                        $rules['facility_code']
                    );
                }
            }
            
            // Validate data
            $validator = Validator::make($data, $rules);
            
            if ($validator->fails()) {
                return [
                    'valid' => false,
                    'errors' => $validator->errors()->toArray()
                ];
            }
            
            // Additional business logic validation
            $additionalErrors = [];
            
            // Check if facility code is unique
            if (isset($data['facility_code'])) {
                $codeExists = $this->facilityRepository->codeExists($data['facility_code'], $excludeId);
                
                if ($codeExists) {
                    $additionalErrors['facility_code'] = ['The facility code has already been taken.'];
                }
            }
            
            // Validate trauma center level
            if (isset($data['has_trauma_center']) && $data['has_trauma_center'] === false && isset($data['trauma_center_level'])) {
                $additionalErrors['trauma_center_level'] = ['Trauma center level should be null when facility does not have a trauma center.'];
            }
            
            if (isset($data['has_trauma_center']) && $data['has_trauma_center'] === true && empty($data['trauma_center_level'])) {
                $additionalErrors['trauma_center_level'] = ['Trauma center level is required when facility has a trauma center.'];
            }
            
            // Validate 24/7 flag with operating hours
            if (isset($data['is_24_7']) && $data['is_24_7'] === true && isset($data['operating_hours'])) {
                // Check if operating hours actually indicate 24/7
                // This is a simplified check - implement based on your business logic
                if (!$this->validate24_7OperatingHours($data['operating_hours'])) {
                    $additionalErrors['is_24_7'] = ['Operating hours do not match 24/7 flag.'];
                }
            }
            
            if (!empty($additionalErrors)) {
                return [
                    'valid' => false,
                    'errors' => $additionalErrors
                ];
            }
            
            return [
                'valid' => true,
                'errors' => []
            ];
        } catch (\Exception $e) {
            Log::error('Failed to validate facility data', [
                'error' => $e->getMessage(),
                'exclude_id' => $excludeId,
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'valid' => false,
                'errors' => ['system' => ['Validation failed: ' . $e->getMessage()]]
            ];
        }
    }

    /**
     * Get facilities by location.
     *
     * @param string $countryCode
     * @param string|null $stateProvince
     * @param string|null $city
     * @return Collection
     */
    public function getFacilitiesByLocation(string $countryCode, ?string $stateProvince = null, ?string $city = null): Collection
    {
        try {
            $cacheKey = $this->generateCacheKey('facilities_by_location', [
                'country' => $countryCode,
                'state' => $stateProvince,
                'city' => $city
            ]);
            
            return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($countryCode, $stateProvince, $city) {
                return $this->facilityRepository->getByLocation($countryCode, $stateProvince, $city);
            });
        } catch (\Exception $e) {
            Log::error('Failed to get facilities by location in service', [
                'error' => $e->getMessage(),
                'country_code' => $countryCode,
                'state_province' => $stateProvince,
                'city' => $city,
                'trace' => $e->getTraceAsString()
            ]);
            
            return new Collection();
        }
    }

    /**
     * Get facilities by type and status.
     *
     * @param string $type
     * @param string $status
     * @return Collection
     */
    public function getFacilitiesByTypeAndStatus(string $type, string $status): Collection
    {
        try {
            $cacheKey = $this->generateCacheKey('facilities_by_type_status', [
                'type' => $type,
                'status' => $status
            ]);
            
            return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($type, $status) {
                return $this->facilityRepository->getByTypeAndStatus($type, $status);
            });
        } catch (\Exception $e) {
            Log::error('Failed to get facilities by type and status in service', [
                'error' => $e->getMessage(),
                'type' => $type,
                'status' => $status,
                'trace' => $e->getTraceAsString()
            ]);
            
            return new Collection();
        }
    }

    /**
     * Search facilities by name or code.
     *
     * @param string $query
     * @param int $limit
     * @return Collection
     */
    public function searchFacilities(string $query, int $limit = 10): Collection
    {
        try {
            $cacheKey = $this->generateCacheKey('search_facilities', [
                'query' => $query,
                'limit' => $limit
            ]);
            
            return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($query, $limit) {
                return $this->facilityRepository->search($query, $limit);
            });
        } catch (\Exception $e) {
            Log::error('Failed to search facilities in service', [
                'error' => $e->getMessage(),
                'query' => $query,
                'limit' => $limit,
                'trace' => $e->getTraceAsString()
            ]);
            
            return new Collection();
        }
    }

    /**
     * Get facilities with emergency departments.
     *
     * @param array $filters
     * @return Collection
     */
    public function getFacilitiesWithEmergencyDepartments(array $filters = []): Collection
    {
        try {
            $cacheKey = $this->generateCacheKey('facilities_with_ed', $filters);
            
            return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($filters) {
                return $this->facilityRepository->getWithEmergencyDepartments($filters);
            });
        } catch (\Exception $e) {
            Log::error('Failed to get facilities with emergency departments in service', [
                'error' => $e->getMessage(),
                'filters' => $filters,
                'trace' => $e->getTraceAsString()
            ]);
            
            return new Collection();
        }
    }

    /**
     * Update facility metrics.
     *
     * @param int $id
     * @param array $metrics
     * @return array
     */
    public function updateFacilityMetrics(int $id, array $metrics): array
    {
        try {
            // Check if facility exists
            $existingFacility = $this->getFacilityById($id);
            
            if (!$existingFacility) {
                return [
                    'success' => false,
                    'message' => 'Facility not found',
                    'errors' => ['id' => ['Facility with ID ' . $id . ' not found']]
                ];
            }
            
            // Validate metrics
            $validator = Validator::make($metrics, [
                'average_wait_time_minutes' => 'nullable|numeric|min:0|max:999.99',
                'patient_satisfaction_score' => 'nullable|numeric|min:0|max:5',
                'monthly_patient_volume' => 'nullable|integer|min:0',
            ]);
            
            if ($validator->fails()) {
                return [
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()->toArray()
                ];
            }
            
            // Update metrics
            $data = array_intersect_key($metrics, [
                'average_wait_time_minutes' => true,
                'patient_satisfaction_score' => true,
                'monthly_patient_volume' => true,
            ]);
            
            // Add metadata update
            if (isset($metrics['metrics_updated_at'])) {
                $metadata = $existingFacility->metadata ?? [];
                $metadata['metrics_updated_at'] = $metrics['metrics_updated_at'];
                $data['metadata'] = $metadata;
            }
            
            $facility = $this->facilityRepository->update($id, $data);
            
            // Clear relevant caches
            $this->clearFacilitySpecificCaches($id);
            
            return [
                'success' => true,
                'message' => 'Facility metrics updated successfully',
                'facility' => $facility,
                'errors' => null
            ];
        } catch (\Exception $e) {
            Log::error('Failed to update facility metrics in service', [
                'error' => $e->getMessage(),
                'facility_id' => $id,
                'metrics' => $metrics,
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to update facility metrics: ' . $e->getMessage(),
                'errors' => ['system' => ['An unexpected error occurred']]
            ];
        }
    }

    /**
     * Check facility operational status.
     *
     * @param int $id
     * @return array
     */
    public function checkFacilityOperationalStatus(int $id): array
    {
        try {
            $facility = $this->getFacilityById($id);
            
            if (!$facility) {
                return [
                    'success' => false,
                    'message' => 'Facility not found',
                    'data' => null
                ];
            }
            
            $status = [
                'is_fully_operational' => $facility->isFullyOperational(),
                'is_closed' => $facility->isClosed(),
                'operational_status' => $facility->operational_status,
                'has_emergency_department' => $facility->has_emergency_department,
                'is_24_7' => $facility->is_24_7,
            ];
            
            return [
                'success' => true,
                'message' => 'Operational status retrieved',
                'data' => $status
            ];
        } catch (\Exception $e) {
            Log::error('Failed to check facility operational status in service', [
                'error' => $e->getMessage(),
                'facility_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to check operational status: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * Generate cache key for facilities.
     *
     * @param string $prefix
     * @param array $params
     * @return string
     */
    private function generateCacheKey(string $prefix, array $params = []): string
    {
        $key = "facility:{$prefix}";
        
        if (!empty($params)) {
            $key .= ':' . md5(serialize($params));
        }
        
        return $key;
    }

    /**
     * Clear all facility-related caches.
     *
     * @return void
     */
    private function clearFacilityCaches(): void
    {
        try {
            Cache::tags(['facilities'])->flush();
        } catch (\Exception $e) {
            Log::warning('Failed to clear facility caches', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Clear caches for a specific facility.
     *
     * @param int $facilityId
     * @return void
     */
    private function clearFacilitySpecificCaches(int $facilityId): void
    {
        try {
            $patterns = [
                "facility:facility_by_id:*{$facilityId}*",
                "facility:*",
            ];
            
            foreach ($patterns as $pattern) {
                // This implementation depends on your cache driver
                // For Redis, you might use scan/delete pattern
                // For file/database cache, you might need a different approach
                
                // Simplified approach: clear tag-based cache
                Cache::tags(["facility_{$facilityId}"])->flush();
            }
        } catch (\Exception $e) {
            Log::warning('Failed to clear facility-specific caches', [
                'error' => $e->getMessage(),
                'facility_id' => $facilityId,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Validate if operating hours indicate 24/7 operation.
     *
     * @param array $operatingHours
     * @return bool
     */
    private function validate24_7OperatingHours(array $operatingHours): bool
    {
        // This is a simplified implementation
        // In production, you would have more complex logic to validate
        // that all days have 24-hour coverage
        
        if (!is_array($operatingHours)) {
            return false;
        }
        
        // Check if structure indicates 24/7
        // This should be customized based on your actual operating hours format
        foreach ($operatingHours as $daySchedule) {
            if (!isset($daySchedule['open_24_hours']) || $daySchedule['open_24_hours'] !== true) {
                return false;
            }
        }
        
        return true;
    }
}