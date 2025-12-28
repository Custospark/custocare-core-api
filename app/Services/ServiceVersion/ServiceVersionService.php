<?php

namespace App\Services\ServiceVersion;

use App\Models\ServiceVersion;
use App\Repositories\Contracts\ServiceVersionRepositoryInterface;
use App\Services\Contracts\ServiceVersionServiceInterface as ContractsServiceVersionServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * ServiceVersion Service Implementation
 * 
 * Contains all business logic for ServiceVersion operations.
 * Orchestrates repository calls and applies business rules.
 */
class ServiceVersionService implements ContractsServiceVersionServiceInterface
{
    /**
     * Repository instance.
     *
     * @var ServiceVersionRepositoryInterface
     */
    protected $repository;

    /**
     * Constructor with dependency injection.
     *
     * @param ServiceVersionRepositoryInterface $repository
     */
    public function __construct(ServiceVersionRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Get a service version by ID.
     *
     * @param int $id
     * @return array
     */
    public function getServiceVersion(int $id): array
    {
        try {
            $serviceVersion = $this->repository->findById($id);
            
            if (!$serviceVersion) {
                return [
                    'success' => false,
                    'message' => 'Service version not found',
                    'data' => null,
                    'status' => 404
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Service version retrieved successfully',
                'data' => $serviceVersion,
                'status' => 200
            ];
        } catch (\Exception $e) {
            Log::error('Error retrieving service version', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve service version',
                'data' => null,
                'status' => 500
            ];
        }
    }

    /**
     * Get a service version by UUID.
     *
     * @param string $uuid
     * @return array
     */
    public function getServiceVersionByUuid(string $uuid): array
    {
        try {
            $serviceVersion = $this->repository->findByUuid($uuid);
            
            if (!$serviceVersion) {
                return [
                    'success' => false,
                    'message' => 'Service version not found',
                    'data' => null,
                    'status' => 404
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Service version retrieved successfully',
                'data' => $serviceVersion,
                'status' => 200
            ];
        } catch (\Exception $e) {
            Log::error('Error retrieving service version by UUID', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve service version',
                'data' => null,
                'status' => 500
            ];
        }
    }

    /**
     * Get all service versions with optional filters.
     *
     * @param array $filters
     * @return array
     */
    public function getAllServiceVersions(array $filters = []): array
    {
        try {
            $serviceVersions = $this->repository->getAll($filters);
            
            return [
                'success' => true,
                'message' => 'Service versions retrieved successfully',
                'data' => $serviceVersions,
                'count' => $serviceVersions->count(),
                'status' => 200
            ];
        } catch (\Exception $e) {
            Log::error('Error retrieving all service versions', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve service versions',
                'data' => [],
                'status' => 500
            ];
        }
    }

    /**
     * Get paginated service versions.
     *
     * @param int $perPage
     * @param array $filters
     * @return array
     */
    public function getPaginatedServiceVersions(int $perPage = 15, array $filters = []): array
    {
        try {
            $paginated = $this->repository->getPaginated($perPage, $filters);
            
            return [
                'success' => true,
                'message' => 'Service versions retrieved successfully',
                'data' => $paginated->items(),
                'pagination' => [
                    'total' => $paginated->total(),
                    'per_page' => $paginated->perPage(),
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    'from' => $paginated->firstItem(),
                    'to' => $paginated->lastItem()
                ],
                'status' => 200
            ];
        } catch (\Exception $e) {
            Log::error('Error retrieving paginated service versions', [
                'perPage' => $perPage,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve service versions',
                'data' => [],
                'pagination' => [],
                'status' => 500
            ];
        }
    }

    /**
     * Create a new service version.
     *
     * @param array $data
     * @return array
     */
    public function createServiceVersion(array $data): array
    {
        try {
            DB::beginTransaction();
            
            // Validate version data
            $validationResult = $this->validateVersionData($data);
            if (!$validationResult['success']) {
                return $validationResult;
            }
            
            // Check for duplicate version number
            $versionExists = $this->repository->versionNumberExists(
                $data['service_catalog_id'],
                $data['facility_id'] ?? null,
                $data['version_number']
            );
            
            if ($versionExists) {
                return [
                    'success' => false,
                    'message' => 'Version number already exists for this service catalog and facility',
                    'errors' => [
                        'version_number' => ['This version number already exists for the specified service catalog and facility.']
                    ],
                    'status' => 422
                ];
            }
            
            // Validate date ranges
            if (!empty($data['valid_to']) && $data['valid_from'] > $data['valid_to']) {
                return [
                    'success' => false,
                    'message' => 'Invalid date range',
                    'errors' => [
                        'valid_from' => ['Valid from date must be before valid to date.'],
                        'valid_to' => ['Valid to date must be after valid from date.']
                    ],
                    'status' => 422
                ];
            }
            
            // Validate price calculations
            if (!empty($data['base_price_amount']) && $data['base_price_amount'] <= 0) {
                return [
                    'success' => false,
                    'message' => 'Invalid base price',
                    'errors' => [
                        'base_price_amount' => ['Base price amount must be greater than 0.']
                    ],
                    'status' => 422
                ];
            }
            
            if (!empty($data['facility_markup_percentage']) && $data['facility_markup_percentage'] < 0) {
                return [
                    'success' => false,
                    'message' => 'Invalid markup percentage',
                    'errors' => [
                        'facility_markup_percentage' => ['Facility markup percentage cannot be negative.']
                    ],
                    'status' => 422
                ];
            }
            
            // Calculate final price if not provided
            if (!isset($data['final_price_amount'])) {
                $basePrice = $data['base_price_amount'];
                $markup = $data['facility_markup_percentage'] ?? 0;
                $data['final_price_amount'] = $basePrice + ($basePrice * $markup / 100);
            }
            
            // Ensure final price is not negative
            if ($data['final_price_amount'] < 0) {
                return [
                    'success' => false,
                    'message' => 'Invalid final price',
                    'errors' => [
                        'final_price_amount' => ['Final price amount cannot be negative.']
                    ],
                    'status' => 422
                ];
            }
            
            // Create the service version
            $serviceVersion = $this->repository->create($data);
            
            DB::commit();
            
            return [
                'success' => true,
                'message' => 'Service version created successfully',
                'data' => $serviceVersion,
                'status' => 201
            ];
        } catch (ValidationException $e) {
            DB::rollBack();
            
            return [
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'status' => 422
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating service version', [
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to create service version',
                'data' => null,
                'status' => 500
            ];
        }
    }

    /**
     * Update an existing service version.
     *
     * @param int $id
     * @param array $data
     * @return array
     */
    public function updateServiceVersion(int $id, array $data): array
    {
        try {
            DB::beginTransaction();
            
            // Find existing version
            $serviceVersion = $this->repository->findById($id);
            
            if (!$serviceVersion) {
                return [
                    'success' => false,
                    'message' => 'Service version not found',
                    'data' => null,
                    'status' => 404
                ];
            }
            
            // Validate version data
            $validationResult = $this->validateVersionData($data, $id);
            if (!$validationResult['success']) {
                return $validationResult;
            }
            
            // Check for duplicate version number if it's being changed
            if (isset($data['version_number']) && $data['version_number'] !== $serviceVersion->version_number) {
                $versionExists = $this->repository->versionNumberExists(
                    $data['service_catalog_id'] ?? $serviceVersion->service_catalog_id,
                    $data['facility_id'] ?? $serviceVersion->facility_id,
                    $data['version_number'],
                    $id
                );
                
                if ($versionExists) {
                    return [
                        'success' => false,
                        'message' => 'Version number already exists for this service catalog and facility',
                        'errors' => [
                            'version_number' => ['This version number already exists for the specified service catalog and facility.']
                        ],
                        'status' => 422
                    ];
                }
            }
            
            // Validate date ranges
            if (isset($data['valid_from']) || isset($data['valid_to'])) {
                $validFrom = $data['valid_from'] ?? $serviceVersion->valid_from;
                $validTo = $data['valid_to'] ?? $serviceVersion->valid_to;
                
                if ($validTo && $validFrom > $validTo) {
                    return [
                        'success' => false,
                        'message' => 'Invalid date range',
                        'errors' => [
                            'valid_from' => ['Valid from date must be before valid to date.'],
                            'valid_to' => ['Valid to date must be after valid from date.']
                        ],
                        'status' => 422
                    ];
                }
            }
            
            // Validate price calculations
            if (isset($data['base_price_amount']) && $data['base_price_amount'] <= 0) {
                return [
                    'success' => false,
                    'message' => 'Invalid base price',
                    'errors' => [
                        'base_price_amount' => ['Base price amount must be greater than 0.']
                    ],
                    'status' => 422
                ];
            }
            
            if (isset($data['facility_markup_percentage']) && $data['facility_markup_percentage'] < 0) {
                return [
                    'success' => false,
                    'message' => 'Invalid markup percentage',
                    'errors' => [
                        'facility_markup_percentage' => ['Facility markup percentage cannot be negative.']
                    ],
                    'status' => 422
                ];
            }
            
            // Update the service version
            $updatedVersion = $this->repository->update($serviceVersion, $data);
            
            DB::commit();
            
            return [
                'success' => true,
                'message' => 'Service version updated successfully',
                'data' => $updatedVersion,
                'status' => 200
            ];
        } catch (ValidationException $e) {
            DB::rollBack();
            
            return [
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'status' => 422
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating service version', [
                'id' => $id,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to update service version',
                'data' => null,
                'status' => 500
            ];
        }
    }

    /**
     * Delete a service version.
     *
     * @param int $id
     * @return array
     */
    public function deleteServiceVersion(int $id): array
    {
        try {
            DB::beginTransaction();
            
            $serviceVersion = $this->repository->findById($id);
            
            if (!$serviceVersion) {
                return [
                    'success' => false,
                    'message' => 'Service version not found',
                    'data' => null,
                    'status' => 404
                ];
            }
            
            // Check if this is the only version for this service/facility
            $versionCount = ServiceVersion::where('service_catalog_id', $serviceVersion->service_catalog_id)
                ->where('facility_id', $serviceVersion->facility_id)
                ->count();
            
            if ($versionCount === 1) {
                return [
                    'success' => false,
                    'message' => 'Cannot delete the only version for this service and facility',
                    'data' => null,
                    'status' => 422
                ];
            }
            
            $deleted = $this->repository->delete($serviceVersion);
            
            if (!$deleted) {
                return [
                    'success' => false,
                    'message' => 'Failed to delete service version',
                    'data' => null,
                    'status' => 500
                ];
            }
            
            DB::commit();
            
            return [
                'success' => true,
                'message' => 'Service version deleted successfully',
                'data' => null,
                'status' => 200
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting service version', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to delete service version',
                'data' => null,
                'status' => 500
            ];
        }
    }

    /**
     * Get current version for a service.
     *
     * @param int $serviceCatalogId
     * @param int|null $facilityId
     * @return array
     */
    public function getCurrentVersion(int $serviceCatalogId, ?int $facilityId = null): array
    {
        try {
            $currentVersions = $this->repository->getCurrentVersions($serviceCatalogId, $facilityId);
            
            if ($currentVersions->isEmpty()) {
                return [
                    'success' => false,
                    'message' => 'No current version found for the specified service catalog and facility',
                    'data' => null,
                    'status' => 404
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Current version retrieved successfully',
                'data' => $currentVersions->first(),
                'status' => 200
            ];
        } catch (\Exception $e) {
            Log::error('Error getting current version', [
                'service_catalog_id' => $serviceCatalogId,
                'facility_id' => $facilityId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve current version',
                'data' => null,
                'status' => 500
            ];
        }
    }

    /**
     * Set a version as current.
     *
     * @param int $versionId
     * @return array
     */
    public function setAsCurrentVersion(int $versionId): array
    {
        try {
            DB::beginTransaction();
            
            $serviceVersion = $this->repository->findById($versionId);
            
            if (!$serviceVersion) {
                return [
                    'success' => false,
                    'message' => 'Service version not found',
                    'data' => null,
                    'status' => 404
                ];
            }
            
            // Check if version is valid (not expired)
            if (!$serviceVersion->isCurrentlyValid()) {
                return [
                    'success' => false,
                    'message' => 'Cannot set expired version as current',
                    'data' => null,
                    'status' => 422
                ];
            }
            
            // Update current version
            $updated = $this->repository->updateCurrentVersion(
                $serviceVersion->service_catalog_id,
                $serviceVersion->facility_id,
                $serviceVersion->id
            );
            
            if (!$updated) {
                return [
                    'success' => false,
                    'message' => 'Failed to set version as current',
                    'data' => null,
                    'status' => 500
                ];
            }
            
            // Refresh the version to get updated data
            $serviceVersion->refresh();
            
            DB::commit();
            
            return [
                'success' => true,
                'message' => 'Version set as current successfully',
                'data' => $serviceVersion,
                'status' => 200
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error setting version as current', [
                'version_id' => $versionId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to set version as current',
                'data' => null,
                'status' => 500
            ];
        }
    }

    /**
     * Get versions valid on a specific date.
     *
     * @param string $date Date in Y-m-d format
     * @param int|null $serviceCatalogId
     * @param int|null $facilityId
     * @return array
     */
    public function getVersionsValidOnDate(string $date, ?int $serviceCatalogId = null, ?int $facilityId = null): array
    {
        try {
            // Validate date format
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                return [
                    'success' => false,
                    'message' => 'Invalid date format. Use YYYY-MM-DD',
                    'data' => null,
                    'status' => 422
                ];
            }
            
            $versions = $this->repository->getValidOnDate($date, $serviceCatalogId, $facilityId);
            
            return [
                'success' => true,
                'message' => 'Versions valid on date retrieved successfully',
                'data' => $versions,
                'count' => $versions->count(),
                'status' => 200
            ];
        } catch (\Exception $e) {
            Log::error('Error getting versions valid on date', [
                'date' => $date,
                'service_catalog_id' => $serviceCatalogId,
                'facility_id' => $facilityId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve versions valid on date',
                'data' => [],
                'status' => 500
            ];
        }
    }

    /**
     * Validate version data before creation/update.
     *
     * @param array $data
     * @param int|null $excludeId
     * @return array
     */
    public function validateVersionData(array $data, ?int $excludeId = null): array
    {
        try {
            $rules = [
                'service_catalog_id' => 'required|integer|exists:service_catalogs,id',
                'facility_id' => 'nullable|integer|exists:facilities,id',
                'version_number' => 'required|string|max:20',
                'valid_from' => 'required|date_format:Y-m-d',
                'valid_to' => 'nullable|date_format:Y-m-d',
                'is_current' => 'boolean',
                'currency_code' => 'required|string|size:3',
                'base_price_amount' => 'required|numeric|min:0|max:9999999999.99',
                'facility_markup_percentage' => 'nullable|numeric|min:-100|max:1000',
                'final_price_amount' => 'nullable|numeric|min:0|max:9999999999.99',
                'insurance_coverage_rates' => 'nullable|array',
                'requires_preauthorization' => 'boolean',
                'preauthorization_criteria' => 'nullable|array',
                'preauth_processing_days' => 'nullable|integer|min:0|max:365',
                'is_billable' => 'boolean',
                'billing_method' => 'required|in:per_service,per_unit,per_hour,per_day,flat_fee,bundled,not_separately_billable',
                'minimum_billable_units' => 'required|numeric|min:0|max:999999.99',
                'maximum_billable_units' => 'nullable|numeric|min:0|max:999999.99|gte:minimum_billable_units',
                'bundled_service_ids' => 'nullable|array',
                'allowed_modifiers' => 'nullable|array',
                'modifier_price_adjustments' => 'nullable|array',
                'documentation_requirements' => 'nullable|string',
                'medical_necessity_criteria' => 'nullable|string',
                'required_diagnosis_codes' => 'nullable|array',
                'direct_cost' => 'nullable|numeric|min:0|max:99999999.99',
                'indirect_cost' => 'nullable|numeric|min:0|max:99999999.99',
                'target_margin_percentage' => 'nullable|numeric|min:-100|max:1000',
                'version_snapshot' => 'required|array',
                'change_notes' => 'nullable|string',
                'created_by_staff_id' => 'nullable|integer|exists:staff,id',
                'metadata' => 'nullable|array',
            ];
            
            $validator = Validator::make($data, $rules);
            
            if ($validator->fails()) {
                return [
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()->toArray(),
                    'status' => 422
                ];
            }
            
            return ['success' => true];
        } catch (\Exception $e) {
            Log::error('Error validating version data', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Validation error occurred',
                'errors' => ['system' => ['An error occurred during validation.']],
                'status' => 500
            ];
        }
    }

    /**
     * Get price calculation for a service version.
     *
     * @param int $versionId
     * @return array
     */
    public function getPriceCalculation(int $versionId): array
    {
        try {
            $serviceVersion = $this->repository->findById($versionId);
            
            if (!$serviceVersion) {
                return [
                    'success' => false,
                    'message' => 'Service version not found',
                    'data' => null,
                    'status' => 404
                ];
            }
            
            $calculation = [
                'base_price' => $serviceVersion->base_price_amount,
                'currency' => $serviceVersion->currency_code,
                'facility_markup_percentage' => $serviceVersion->facility_markup_percentage,
                'facility_markup_amount' => $serviceVersion->calculateFacilityMarkupAmount(),
                'final_price' => $serviceVersion->final_price_amount,
                'display_price' => $serviceVersion->display_price,
                'cost_breakdown' => [
                    'direct_cost' => $serviceVersion->direct_cost,
                    'indirect_cost' => $serviceVersion->indirect_cost,
                    'total_cost' => ($serviceVersion->direct_cost ?? 0) + ($serviceVersion->indirect_cost ?? 0),
                    'target_margin_percentage' => $serviceVersion->target_margin_percentage,
                    'target_profit' => $serviceVersion->target_margin_percentage 
                        ? ($serviceVersion->final_price_amount * $serviceVersion->target_margin_percentage / 100)
                        : null
                ]
            ];
            
            return [
                'success' => true,
                'message' => 'Price calculation retrieved successfully',
                'data' => $calculation,
                'status' => 200
            ];
        } catch (\Exception $e) {
            Log::error('Error getting price calculation', [
                'version_id' => $versionId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to calculate price',
                'data' => null,
                'status' => 500
            ];
        }
    }

    /**
     * Get version history for a service.
     *
     * @param int $serviceCatalogId
     * @param int|null $facilityId
     * @return array
     */
    public function getVersionHistory(int $serviceCatalogId, ?int $facilityId = null): array
    {
        try {
            $history = $this->repository->getVersionHistory($serviceCatalogId, $facilityId);
            
            return [
                'success' => true,
                'message' => 'Version history retrieved successfully',
                'data' => $history,
                'count' => $history->count(),
                'status' => 200
            ];
        } catch (\Exception $e) {
            Log::error('Error getting version history', [
                'service_catalog_id' => $serviceCatalogId,
                'facility_id' => $facilityId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve version history',
                'data' => [],
                'status' => 500
            ];
        }
    }

    /**
     * Check if a version is billable under specific conditions.
     *
     * @param int $versionId
     * @param array $conditions
     * @return array
     */
    public function checkBillability(int $versionId, array $conditions = []): array
    {
        try {
            $serviceVersion = $this->repository->findById($versionId);
            
            if (!$serviceVersion) {
                return [
                    'success' => false,
                    'message' => 'Service version not found',
                    'data' => null,
                    'status' => 404
                ];
            }
            
            $billability = [
                'is_billable' => $serviceVersion->is_billable,
                'billing_method' => $serviceVersion->billing_method,
                'minimum_units' => $serviceVersion->minimum_billable_units,
                'maximum_units' => $serviceVersion->maximum_billable_units,
                'requires_preauthorization' => $serviceVersion->requires_preauthorization,
                'current_status' => null,
                'requirements_met' => [],
                'requirements_failed' => []
            ];
            
            // Check conditions
            if (!empty($conditions)) {
                // Check if service is currently valid
                $billability['current_status'] = $serviceVersion->isCurrentlyValid() ? 'valid' : 'expired';
                
                // Check preauthorization if required
                if ($serviceVersion->requires_preauthorization) {
                    if (!empty($conditions['has_preauthorization'])) {
                        $billability['requirements_met'][] = 'preauthorization';
                    } else {
                        $billability['requirements_failed'][] = 'preauthorization';
                    }
                }
                
                // Check diagnosis codes if required
                if (!empty($serviceVersion->required_diagnosis_codes)) {
                    $patientDiagnosis = $conditions['patient_diagnosis_codes'] ?? [];
                    $matchingCodes = array_intersect($serviceVersion->required_diagnosis_codes, $patientDiagnosis);
                    
                    if (!empty($matchingCodes)) {
                        $billability['requirements_met'][] = 'diagnosis_codes';
                    } else {
                        $billability['requirements_failed'][] = 'diagnosis_codes';
                    }
                }
                
                // Check units if provided
                if (isset($conditions['units'])) {
                    $units = (float) $conditions['units'];
                    $billability['provided_units'] = $units;
                    $billability['units_within_range'] = 
                        $units >= $serviceVersion->minimum_billable_units &&
                        (!$serviceVersion->maximum_billable_units || $units <= $serviceVersion->maximum_billable_units);
                }
            }
            
            return [
                'success' => true,
                'message' => 'Billability check completed',
                'data' => $billability,
                'status' => 200
            ];
        } catch (\Exception $e) {
            Log::error('Error checking billability', [
                'version_id' => $versionId,
                'conditions' => $conditions,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to check billability',
                'data' => null,
                'status' => 500
            ];
        }
    }

    /**
     * Calculate insurance coverage for a version.
     *
     * @param int $versionId
     * @param string $insuranceType
     * @return array
     */
    public function calculateInsuranceCoverage(int $versionId, string $insuranceType): array
    {
        try {
            $serviceVersion = $this->repository->findById($versionId);
            
            if (!$serviceVersion) {
                return [
                    'success' => false,
                    'message' => 'Service version not found',
                    'data' => null,
                    'status' => 404
                ];
            }
            
            $coverageRates = $serviceVersion->insurance_coverage_rates ?? [];
            $coveragePercentage = $coverageRates[$insuranceType] ?? null;
            
            if ($coveragePercentage === null) {
                return [
                    'success' => false,
                    'message' => 'Insurance type not covered by this service version',
                    'data' => null,
                    'status' => 404
                ];
            }
            
            $calculation = [
                'insurance_type' => $insuranceType,
                'coverage_percentage' => $coveragePercentage,
                'service_price' => $serviceVersion->final_price_amount,
                'currency' => $serviceVersion->currency_code,
                'insurance_portion' => $serviceVersion->final_price_amount * ($coveragePercentage / 100),
                'patient_portion' => $serviceVersion->final_price_amount * ((100 - $coveragePercentage) / 100),
                'requires_preauthorization' => $serviceVersion->requires_preauthorization,
                'preauth_processing_days' => $serviceVersion->preauth_processing_days
            ];
            
            return [
                'success' => true,
                'message' => 'Insurance coverage calculated successfully',
                'data' => $calculation,
                'status' => 200
            ];
        } catch (\Exception $e) {
            Log::error('Error calculating insurance coverage', [
                'version_id' => $versionId,
                'insurance_type' => $insuranceType,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to calculate insurance coverage',
                'data' => null,
                'status' => 500
            ];
        }
    }
}