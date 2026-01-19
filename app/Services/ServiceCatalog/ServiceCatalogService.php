<?php

namespace App\Services\ServiceCatalog;

use App\Models\ServiceCatalog;
use App\Repositories\Contracts\ServiceCatalogRepositoryInterface;
use App\Services\Contracts\ServiceCatalogServiceInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Service implementation for ServiceCatalog business logic.
 * All operations are scoped by facility_id from request headers.
 */
class ServiceCatalogService implements ServiceCatalogServiceInterface
{
    /**
     * The service catalog repository instance.
     *
     * @var ServiceCatalogRepositoryInterface
     */
    protected ServiceCatalogRepositoryInterface $repository;

    /**
     * Current facility ID from headers.
     *
     * @var int|null
     */
    protected ?int $facilityId = null;

    /**
     * Create a new service instance.
     *
     * @param ServiceCatalogRepositoryInterface $repository
     */
    public function __construct(ServiceCatalogRepositoryInterface $repository)
    {
        $this->repository = $repository;
        $this->facilityId = $this->getCurrentFacilityId();
    }

    /**
     * Get current facility ID from request headers.
     *
     * @return int|null
     */
    protected function getCurrentFacilityId(): ?int
    {
        return request()->header('X-Facility-Id') 
            ? (int) request()->header('X-Facility-Id')
            : null;
    }

    /**
     * Validate facility ID is present.
     *
     * @return bool
     */
    protected function validateFacilityId(): bool
    {
        if (!$this->facilityId) {
            throw new \RuntimeException('Facility ID is required in request headers (X-Facility-Id)');
        }
        return true;
    }

    /**
     * Get all service catalogs for current facility with pagination.
     *
     * @param array $filters
     * @param int $perPage
     * @return array
     */
    public function getAllServiceCatalogs(array $filters = [], int $perPage = 15): array
    {
        try {
            $this->validateFacilityId();
            
            // Always scope by current facility
            $filters['facility_id'] = $this->facilityId;
            
            $paginator = $this->repository->paginate($perPage, $filters);

            return [
                'success' => true,
                'message' => 'Service catalogs retrieved successfully.',
                'data' => [
                    'services' => $paginator->items(),
                    'pagination' => [
                        'total' => $paginator->total(),
                        'per_page' => $paginator->perPage(),
                        'current_page' => $paginator->currentPage(),
                        'last_page' => $paginator->lastPage(),
                        'from' => $paginator->firstItem(),
                        'to' => $paginator->lastItem(),
                    ]
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve service catalogs', [
                'facility_id' => $this->facilityId,
                'error' => $e->getMessage(),
                'filters' => $filters,
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Unable to retrieve service catalogs at this time. Please try again later.',
                'data' => []
            ];
        }
    }

    /**
     * Get a service catalog by UUID for current facility.
     *
     * @param string $uuid
     * @return array
     */
    public function getServiceCatalogByUuid(string $uuid): array
    {
        try {
            $this->validateFacilityId();
            
            $serviceCatalog = $this->repository->findByUuidAndFacility($uuid, $this->facilityId);

            if (!$serviceCatalog) {
                return [
                    'success' => false,
                    'message' => 'Service catalog not found.',
                    'data' => []
                ];
            }

            return [
                'success' => true,
                'message' => 'Service catalog retrieved successfully.',
                'data' => $serviceCatalog
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve service catalog by UUID', [
                'uuid' => $uuid,
                'facility_id' => $this->facilityId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Unable to retrieve service catalog. Please try again later.',
                'data' => []
            ];
        }
    }

    /**
     * Get a service catalog by service code for current facility.
     *
     * @param string $serviceCode
     * @return array
     */
    public function getServiceCatalogByCode(string $serviceCode): array
    {
        try {
            $this->validateFacilityId();
            
            $serviceCatalog = $this->repository->findByServiceCodeAndFacility($serviceCode, $this->facilityId);

            if (!$serviceCatalog) {
                return [
                    'success' => false,
                    'message' => 'Service catalog not found.',
                    'data' => []
                ];
            }

            return [
                'success' => true,
                'message' => 'Service catalog retrieved successfully.',
                'data' => $serviceCatalog
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve service catalog by code', [
                'service_code' => $serviceCode,
                'facility_id' => $this->facilityId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Unable to retrieve service catalog. Please try again later.',
                'data' => []
            ];
        }
    }

    /**
     * Create a new service catalog for current facility.
     *
     * @param array $data
     * @return array
     */
    public function createServiceCatalog(array $data): array
    {
        // Never allow callers to set auto-increment PK
        unset($data['id']);

        try {
            $this->validateFacilityId();
            
            /**
             * 1) Normalize + provide safe defaults BEFORE validation
             */

            // Auto-generate service_code if missing/empty
            if (!isset($data['service_code']) || trim((string) $data['service_code']) === '') {
                $data['service_code'] = $this->generateUniqueServiceCode($this->facilityId);
            } else {
                $data['service_code'] = trim((string) $data['service_code']);
            }

            // Ensure service_uuid exists
            if (!isset($data['service_uuid']) || trim((string) $data['service_uuid']) === '') {
                $data['service_uuid'] = (string) Str::uuid();
            } else {
                $data['service_uuid'] = trim((string) $data['service_uuid']);
            }

            // Set facility_id from headers (cannot be overridden)
            $data['facility_id'] = $this->facilityId;

            // If effective_from is missing, start today (YYYY-MM-DD)
            if (!isset($data['effective_from']) || trim((string) $data['effective_from']) === '') {
                $data['effective_from'] = Carbon::now()->toDateString();
            }

            // Set created_by_staff_id if missing and user is authenticated
            if (!isset($data['created_by_staff_id']) && Auth::check()) {
                $data['created_by_staff_id'] = Auth::id();
            }

            // Validate business rules before creation
            $validationResult = $this->validateServiceCatalogData($data);
            if (!$validationResult['success']) {
                return $validationResult;
            }

            /**
             * 3) Cross-field validation
             */
            if (isset($data['effective_from'], $data['effective_to']) &&
                trim((string) $data['effective_to']) !== '') {

                try {
                    $effectiveFrom = Carbon::parse($data['effective_from']);
                    $effectiveTo   = Carbon::parse($data['effective_to']);

                    if ($effectiveTo->lessThan($effectiveFrom)) {
                        return [
                            'success' => false,
                            'message' => 'Effective to date must be after effective from date.',
                            'data' => []
                        ];
                    }
                } catch (\Exception $e) {
                    return [
                        'success' => false,
                        'message' => 'Invalid effective_to date format. Please use YYYY-MM-DD format.',
                        'data' => []
                    ];
                }
            }

            /**
             * 4) Persist
             */
            $serviceCatalog = DB::transaction(function () use ($data) {
                return $this->repository->create($data);
            });

            Log::info('Service catalog created successfully', [
                'facility_id' => $this->facilityId,
                'service_uuid' => $serviceCatalog->service_uuid,
                'service_code' => $serviceCatalog->service_code,
            ]);

            return [
                'success' => true,
                'message' => 'Service catalog created successfully.',
                'data' => $serviceCatalog
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to create service catalog', [
                'facility_id' => $this->facilityId,
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to create service catalog. Please try again.',
                'data' => []
            ];
        }
    }

    /**
     * Update an existing service catalog for current facility.
     *
     * @param string $uuid
     * @param array $data
     * @return array
     */
    public function updateServiceCatalog(string $uuid, array $data): array
    {
        try {
            $this->validateFacilityId();
            
            $serviceCatalog = $this->repository->findByUuidAndFacility($uuid, $this->facilityId);

            if (!$serviceCatalog) {
                return [
                    'success' => false,
                    'message' => 'Service catalog not found.',
                    'data' => []
                ];
            }

            // Validate business rules before update
            $validationResult = $this->validateServiceCatalogData($data, $uuid);
            if (!$validationResult['success']) {
                return $validationResult;
            }

            // Prevent updating service code if it would cause a duplicate within same facility
            if (isset($data['service_code']) && $data['service_code'] !== $serviceCatalog->service_code) {
                if ($this->repository->serviceCodeExists($data['service_code'], $this->facilityId, $uuid)) {
                    return [
                        'success' => false,
                        'message' => 'Service code already exists in this facility. Please use a different code.',
                        'data' => []
                    ];
                }
            }

            // Ensure effective_to is after effective_from if both are set
            $effectiveFrom = isset($data['effective_from']) 
                ? \Carbon\Carbon::parse($data['effective_from'])
                : $serviceCatalog->effective_from;
            
            if (isset($data['effective_to'])) {
                $effectiveTo = \Carbon\Carbon::parse($data['effective_to']);
                if ($effectiveTo->lessThan($effectiveFrom)) {
                    return [
                        'success' => false,
                        'message' => 'Effective to date must be after effective from date.',
                        'data' => []
                    ];
                }
            }

            DB::beginTransaction();

            $updated = $this->repository->update($serviceCatalog, $data);

            if (!$updated) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Failed to update service catalog.',
                    'data' => []
                ];
            }

            DB::commit();

            // Refresh the model to get updated attributes
            $serviceCatalog->refresh();

            Log::info('Service catalog updated successfully', [
                'facility_id' => $this->facilityId,
                'service_uuid' => $uuid,
                'service_code' => $serviceCatalog->service_code
            ]);

            return [
                'success' => true,
                'message' => 'Service catalog updated successfully.',
                'data' => $serviceCatalog
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to update service catalog', [
                'facility_id' => $this->facilityId,
                'uuid' => $uuid,
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to update service catalog. Please try again.',
                'data' => []
            ];
        }
    }

    /**
     * Delete a service catalog for current facility.
     *
     * @param string $uuid
     * @return array
     */
    public function deleteServiceCatalog(string $uuid): array
    {
        try {
            $this->validateFacilityId();
            
            $serviceCatalog = $this->repository->findByUuidAndFacility($uuid, $this->facilityId);

            if (!$serviceCatalog) {
                return [
                    'success' => false,
                    'message' => 'Service catalog not found.',
                    'data' => []
                ];
            }

            // Check if service is currently in use (you would need to implement this check based on your business rules)
            // For example: if ($this->isServiceInUse($serviceCatalog)) { ... }

            $deleted = $this->repository->delete($serviceCatalog);

            if (!$deleted) {
                return [
                    'success' => false,
                    'message' => 'Failed to delete service catalog.',
                    'data' => []
                ];
            }

            Log::info('Service catalog deleted successfully', [
                'facility_id' => $this->facilityId,
                'service_uuid' => $uuid,
                'service_code' => $serviceCatalog->service_code
            ]);

            return [
                'success' => true,
                'message' => 'Service catalog deleted successfully.',
                'data' => []
            ];
        } catch (\Exception $e) {
            Log::error('Failed to delete service catalog', [
                'facility_id' => $this->facilityId,
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to delete service catalog. Please try again.',
                'data' => []
            ];
        }
    }

    /**
     * Restore a soft-deleted service catalog for current facility.
     *
     * @param string $uuid
     * @return array
     */
    public function restoreServiceCatalog(string $uuid): array
    {
        try {
            $this->validateFacilityId();
            
            // Find including trashed, scoped by facility
            $serviceCatalog = ServiceCatalog::withTrashed()
                ->where('service_uuid', $uuid)
                ->where('facility_id', $this->facilityId)
                ->first();

            if (!$serviceCatalog) {
                return [
                    'success' => false,
                    'message' => 'Service catalog not found.',
                    'data' => []
                ];
            }

            if (!$serviceCatalog->trashed()) {
                return [
                    'success' => false,
                    'message' => 'Service catalog is not deleted.',
                    'data' => []
                ];
            }

            $restored = $this->repository->restore($serviceCatalog);

            if (!$restored) {
                return [
                    'success' => false,
                    'message' => 'Failed to restore service catalog.',
                    'data' => []
                ];
            }

            $serviceCatalog->refresh();

            Log::info('Service catalog restored successfully', [
                'facility_id' => $this->facilityId,
                'service_uuid' => $uuid,
                'service_code' => $serviceCatalog->service_code
            ]);

            return [
                'success' => true,
                'message' => 'Service catalog restored successfully.',
                'data' => $serviceCatalog
            ];
        } catch (\Exception $e) {
            Log::error('Failed to restore service catalog', [
                'facility_id' => $this->facilityId,
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to restore service catalog. Please try again.',
                'data' => []
            ];
        }
    }

    /**
     * Get active service catalogs effective on a specific date for current facility.
     *
     * @param string $date
     * @param array $filters
     * @return array
     */
    public function getEffectiveServices(string $date, array $filters = []): array
    {
        try {
            $this->validateFacilityId();
            
            // Validate date format
            if (!\Carbon\Carbon::canBeCreatedFromFormat($date, 'Y-m-d')) {
                return [
                    'success' => false,
                    'message' => 'Invalid date format. Please use YYYY-MM-DD format.',
                    'data' => []
                ];
            }

            // Always scope by current facility
            $filters['facility_id'] = $this->facilityId;
            $filters['effective_date'] = $date;

            $services = $this->repository->getEffectiveServices($date, $filters);

            return [
                'success' => true,
                'message' => 'Effective services retrieved successfully.',
                'data' => $services
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get effective services', [
                'facility_id' => $this->facilityId,
                'date' => $date,
                'error' => $e->getMessage(),
                'filters' => $filters
            ]);

            return [
                'success' => false,
                'message' => 'Unable to retrieve effective services. Please try again later.',
                'data' => []
            ];
        }
    }

    /**
     * Get service catalogs by code system for current facility.
     *
     * @param string $codeSystem
     * @param array $filters
     * @return array
     */
    public function getByCodeSystem(string $codeSystem, array $filters = []): array
    {
        try {
            $this->validateFacilityId();
            
            $validCodeSystems = ['cpt', 'hcpcs', 'icd_10_pcs', 'cdt', 'local_custom'];
            
            if (!in_array($codeSystem, $validCodeSystems)) {
                return [
                    'success' => false,
                    'message' => 'Invalid code system. Valid systems are: ' . implode(', ', $validCodeSystems),
                    'data' => []
                ];
            }

            // Always scope by current facility
            $filters['facility_id'] = $this->facilityId;
            $filters['code_system'] = $codeSystem;

            $services = $this->repository->getByCodeSystem($codeSystem, $filters);

            return [
                'success' => true,
                'message' => 'Service catalogs retrieved successfully.',
                'data' => $services
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get service catalogs by code system', [
                'facility_id' => $this->facilityId,
                'code_system' => $codeSystem,
                'error' => $e->getMessage(),
                'filters' => $filters
            ]);

            return [
                'success' => false,
                'message' => 'Unable to retrieve service catalogs. Please try again later.',
                'data' => []
            ];
        }
    }

    /**
     * Get service catalogs by category for current facility.
     *
     * @param string $category
     * @param array $filters
     * @return array
     */
    public function getByCategory(string $category, array $filters = []): array
    {
        try {
            $this->validateFacilityId();
            
            $validCategories = [
                'evaluation_management',
                'diagnostic_imaging',
                'laboratory_test',
                'surgical_procedure',
                'medical_procedure',
                'therapy_session',
                'preventive_care',
                'vaccination',
                'medication_administration',
                'emergency_service',
                'consultation',
                'anesthesia',
                'pathology',
                'radiology',
                'facility_fee'
            ];
            
            if (!in_array($category, $validCategories)) {
                return [
                    'success' => false,
                    'message' => 'Invalid service category. Valid categories are: ' . implode(', ', $validCategories),
                    'data' => []
                ];
            }

            // Always scope by current facility
            $filters['facility_id'] = $this->facilityId;
            $filters['service_category'] = $category;

            $services = $this->repository->getByCategory($category, $filters);

            return [
                'success' => true,
                'message' => 'Service catalogs retrieved successfully.',
                'data' => $services
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get service catalogs by category', [
                'facility_id' => $this->facilityId,
                'category' => $category,
                'error' => $e->getMessage(),
                'filters' => $filters
            ]);

            return [
                'success' => false,
                'message' => 'Unable to retrieve service catalogs. Please try again later.',
                'data' => []
            ];
        }
    }

    /**
     * Search service catalogs by name or code for current facility.
     *
     * @param string $searchTerm
     * @param array $filters
     * @return array
     */
    public function searchServiceCatalogs(string $searchTerm, array $filters = []): array
    {
        try {
            $this->validateFacilityId();
            
            if (strlen($searchTerm) < 2) {
                return [
                    'success' => false,
                    'message' => 'Search term must be at least 2 characters long.',
                    'data' => []
                ];
            }

            // Always scope by current facility
            $filters['facility_id'] = $this->facilityId;

            $services = $this->repository->search($searchTerm, $filters);

            return [
                'success' => true,
                'message' => 'Search completed successfully.',
                'data' => $services
            ];
        } catch (\Exception $e) {
            Log::error('Failed to search service catalogs', [
                'facility_id' => $this->facilityId,
                'search_term' => $searchTerm,
                'error' => $e->getMessage(),
                'filters' => $filters
            ]);

            return [
                'success' => false,
                'message' => 'Search failed. Please try again later.',
                'data' => []
            ];
        }
    }

    /**
     * Validate service catalog data before creation/update.
     *
     * @param array $data
     * @param string|null $excludeUuid
     * @return array
     */
    public function validateServiceCatalogData(array $data, ?string $excludeUuid = null): array
    {
        try {
            // Check required fields for creation
            if (!isset($data['service_code']) || empty(trim($data['service_code']))) {
                return [
                    'success' => false,
                    'message' => 'Service code is required.',
                    'data' => []
                ];
            }

            if (!isset($data['service_name']) || empty(trim($data['service_name']))) {
                return [
                    'success' => false,
                    'message' => 'Service name is required.',
                    'data' => []
                ];
            }

            if (!isset($data['service_category']) || empty(trim($data['service_category']))) {
                return [
                    'success' => false,
                    'message' => 'Service category is required.',
                    'data' => []
                ];
            }

            // Validate service code uniqueness within facility
            if (isset($data['service_code'])) {
                $serviceCode = trim($data['service_code']);
                $facilityId = $data['facility_id'] ?? $this->facilityId;
                
                if ($this->repository->serviceCodeExists($serviceCode, $facilityId, $excludeUuid)) {
                    return [
                        'success' => false,
                        'message' => 'Service code already exists in this facility. Please use a different code.',
                        'data' => []
                    ];
                }

                // Validate service code length
                if (strlen($serviceCode) > 50) {
                    return [
                        'success' => false,
                        'message' => 'Service code must not exceed 50 characters.',
                        'data' => []
                    ];
                }
            }

            // Validate code system
            if (isset($data['code_system'])) {
                $validCodeSystems = ['cpt', 'hcpcs', 'icd_10_pcs', 'cdt', 'local_custom'];
                if (!in_array($data['code_system'], $validCodeSystems)) {
                    return [
                        'success' => false,
                        'message' => 'Invalid code system. Valid systems are: ' . implode(', ', $validCodeSystems),
                        'data' => []
                    ];
                }
            }

            // Validate service category
            if (isset($data['service_category'])) {
                $validCategories = [
                    'evaluation_management',
                    'diagnostic_imaging',
                    'laboratory_test',
                    'surgical_procedure',
                    'medical_procedure',
                    'therapy_session',
                    'preventive_care',
                    'vaccination',
                    'medication_administration',
                    'emergency_service',
                    'consultation',
                    'anesthesia',
                    'pathology',
                    'radiology',
                    'facility_fee'
                ];
                
                if (!in_array($data['service_category'], $validCategories)) {
                    return [
                        'success' => false,
                        'message' => 'Invalid service category. Valid categories are: ' . implode(', ', $validCategories),
                        'data' => []
                    ];
                }
            }

            // Validate risk level
            if (isset($data['risk_level'])) {
                $validRiskLevels = ['low', 'moderate', 'high', 'critical'];
                if (!in_array($data['risk_level'], $validRiskLevels)) {
                    return [
                        'success' => false,
                        'message' => 'Invalid risk level. Valid levels are: ' . implode(', ', $validRiskLevels),
                        'data' => []
                    ];
                }
            }

            // Validate status
            if (isset($data['status'])) {
                $validStatuses = ['active', 'inactive', 'deprecated', 'under_review'];
                if (!in_array($data['status'], $validStatuses)) {
                    return [
                        'success' => false,
                        'message' => 'Invalid status. Valid statuses are: ' . implode(', ', $validStatuses),
                        'data' => []
                    ];
                }
            }

            // Validate dates (auto-fill effective_from if missing)
            try {
                // Provide default "today" if missing/empty
                if (!isset($data['effective_from']) || trim((string) $data['effective_from']) === '') {
                    $data['effective_from'] = \Carbon\Carbon::now()->toDateString(); // YYYY-MM-DD
                }

                $effectiveFrom = \Carbon\Carbon::parse($data['effective_from']);
            } catch (\Exception $e) {
                return [
                    'success' => false,
                    'message' => 'Invalid effective from date format. Please use YYYY-MM-DD format.',
                    'data' => []
                ];
            }

            if (isset($data['effective_to'])) {
                try {
                    \Carbon\Carbon::parse($data['effective_to']);
                } catch (\Exception $e) {
                    return [
                        'success' => false,
                        'message' => 'Invalid effective to date format. Please use YYYY-MM-DD format.',
                        'data' => []
                    ];
                }
            }

            // Validate JSON fields if provided
            $jsonFields = [
                'alternate_names',
                'service_subcategories',
                'regulatory_approval_status',
                'required_certifications',
                'minimum_required_credentials',
                'required_equipment',
                'required_facility_capabilities',
                'typical_indications',
                'contraindications',
                'prerequisites',
                'commonly_paired_services',
                'approved_countries',
                'state_specific_regulations',
                'metadata'
            ];

            foreach ($jsonFields as $field) {
                if (isset($data[$field]) && !is_array($data[$field]) && !is_null($data[$field])) {
                    // Try to decode if it's a JSON string
                    if (is_string($data[$field])) {
                        json_decode($data[$field], true);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            return [
                                'success' => false,
                                'message' => "Invalid JSON format for field: {$field}.",
                                'data' => []
                            ];
                        }
                    } else {
                        return [
                            'success' => false,
                            'message' => "Field {$field} must be a valid JSON array or object.",
                            'data' => []
                        ];
                    }
                }
            }

            return [
                'success' => true,
                'message' => 'Validation successful.',
                'data' => []
            ];
        } catch (\Exception $e) {
            Log::error('Failed to validate service catalog data', [
                'facility_id' => $this->facilityId,
                'data' => $data,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Data validation failed. Please check your input.',
                'data' => []
            ];
        }
    }

    /**
     * Generate unique service code for facility.
     *
     * @param int $facilityId
     * @return string
     */
    private function generateUniqueServiceCode(int $facilityId): string
    {
        do {
            $code = 'SVC-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        } while ($this->repository->serviceCodeExists($code, $facilityId));

        return $code;
    }

    /**
     * Check if a service is currently effective for current facility.
     *
     * @param string $uuid
     * @param string|null $date
     * @return array
     */
    public function checkServiceEffectiveness(string $uuid, ?string $date = null): array
    {
        try {
            $this->validateFacilityId();
            
            $serviceCatalog = $this->repository->findByUuidAndFacility($uuid, $this->facilityId);

            if (!$serviceCatalog) {
                return [
                    'success' => false,
                    'message' => 'Service catalog not found.',
                    'data' => []
                ];
            }

            $date = $date ?: now()->toDateString();
            $isEffective = $serviceCatalog->isEffective($date);

            return [
                'success' => true,
                'message' => 'Service effectiveness check completed.',
                'data' => [
                    'is_effective' => $isEffective,
                    'service_uuid' => $uuid,
                    'check_date' => $date,
                    'effective_from' => $serviceCatalog->effective_from,
                    'effective_to' => $serviceCatalog->effective_to,
                    'status' => $serviceCatalog->status
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Failed to check service effectiveness', [
                'facility_id' => $this->facilityId,
                'uuid' => $uuid,
                'date' => $date,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Unable to check service effectiveness. Please try again later.',
                'data' => []
            ];
        }
    }
}