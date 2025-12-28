<?php

namespace App\Services\DataResidencyRule;

use App\Models\DataResidencyRule;
use App\Repositories\Contracts\DataResidencyRuleRepositoryInterface;
use App\Services\Contracts\DataResidencyRuleServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class DataResidencyRuleService implements DataResidencyRuleServiceInterface
{
    /**
     * Repository instance
     *
     * @var DataResidencyRuleRepositoryInterface
     */
    protected DataResidencyRuleRepositoryInterface $repository;

    /**
     * Constructor with dependency injection
     *
     * @param DataResidencyRuleRepositoryInterface $repository
     */
    public function __construct(DataResidencyRuleRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Get all data residency rules with pagination
     *
     * @param array $filters
     * @param array $sort
     * @param int $perPage
     * @return array
     */
    public function getAllRules(array $filters = [], array $sort = [], int $perPage = 20): array
    {
        try {
            $paginator = $this->repository->getAll($filters, $sort, $perPage);
            
            return [
                'success' => true,
                'data' => [
                    'rules' => $paginator->items(),
                    'pagination' => [
                        'total' => $paginator->total(),
                        'per_page' => $paginator->perPage(),
                        'current_page' => $paginator->currentPage(),
                        'last_page' => $paginator->lastPage(),
                        'from' => $paginator->firstItem(),
                        'to' => $paginator->lastItem(),
                    ]
                ],
                'message' => 'Rules retrieved successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve data residency rules', [
                'error' => $e->getMessage(),
                'filters' => $filters,
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve data residency rules. Please try again later.',
                'error_code' => 'RULES_FETCH_ERROR'
            ];
        }
    }

    /**
     * Get rule by ID
     *
     * @param int $id
     * @return array
     */
    public function getRuleById(int $id): array
    {
        try {
            $rule = $this->repository->findById($id);
            
            if (!$rule) {
                return [
                    'success' => false,
                    'message' => 'Data residency rule not found',
                    'error_code' => 'RULE_NOT_FOUND'
                ];
            }
            
            return [
                'success' => true,
                'data' => $rule,
                'message' => 'Rule retrieved successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve data residency rule by ID', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve data residency rule. Please try again later.',
                'error_code' => 'RULE_FETCH_ERROR'
            ];
        }
    }

    /**
     * Get rule by region code and data category
     *
     * @param string $regionCode
     * @param string $dataCategory
     * @return array
     */
    public function getRuleByRegionAndCategory(string $regionCode, string $dataCategory): array
    {
        try {
            $rule = $this->repository->findByRegionAndCategory($regionCode, $dataCategory);
            
            if (!$rule) {
                return [
                    'success' => false,
                    'message' => 'No rule found for the specified region and data category',
                    'error_code' => 'RULE_NOT_FOUND'
                ];
            }
            
            return [
                'success' => true,
                'data' => $rule,
                'message' => 'Rule retrieved successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve rule by region and category', [
                'region_code' => $regionCode,
                'data_category' => $dataCategory,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve rule. Please try again later.',
                'error_code' => 'RULE_FETCH_ERROR'
            ];
        }
    }

    /**
     * Create a new data residency rule
     *
     * @param array $data
     * @return array
     */
    public function createRule(array $data): array
    {
        DB::beginTransaction();
        
        try {
            // Validate business rules
            $validationResult = $this->validateRuleData($data);
            if (!$validationResult['success']) {
                return $validationResult;
            }
            
            // Check uniqueness
            if ($this->repository->existsByRegionAndCategory($data['region_code'], $data['data_category'])) {
                return [
                    'success' => false,
                    'message' => 'A rule already exists for this region and data category',
                    'error_code' => 'RULE_ALREADY_EXISTS'
                ];
            }
            
            // Set default values for JSON fields
            $data = $this->setDefaultJsonValues($data);
            
            // Validate effective dates
            if (!$this->validateEffectiveDates($data)) {
                return [
                    'success' => false,
                    'message' => 'Effective from date must be before effective to date',
                    'error_code' => 'INVALID_DATE_RANGE'
                ];
            }
            
            // Validate retention periods
            if (!$this->validateRetentionPeriods($data)) {
                return [
                    'success' => false,
                    'message' => 'Maximum retention period must be greater than or equal to minimum retention period',
                    'error_code' => 'INVALID_RETENTION_PERIOD'
                ];
            }
            
            // Create the rule
            $rule = $this->repository->create($data);
            
            DB::commit();
            
            return [
                'success' => true,
                'data' => $rule,
                'message' => 'Data residency rule created successfully'
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to create data residency rule', [
                'error' => $e->getMessage(),
                'data' => $data,
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to create data residency rule. Please try again later.',
                'error_code' => 'RULE_CREATION_ERROR'
            ];
        }
    }

    /**
     * Update an existing data residency rule
     *
     * @param int $id
     * @param array $data
     * @return array
     */
    public function updateRule(int $id, array $data): array
    {
        DB::beginTransaction();
        
        try {
            $rule = $this->repository->findById($id);
            
            if (!$rule) {
                return [
                    'success' => false,
                    'message' => 'Data residency rule not found',
                    'error_code' => 'RULE_NOT_FOUND'
                ];
            }
            
            // Validate business rules
            $validationResult = $this->validateRuleData($data, $rule->id);
            if (!$validationResult['success']) {
                return $validationResult;
            }
            
            // Check uniqueness if region_code or data_category is being changed
            if (isset($data['region_code']) || isset($data['data_category'])) {
                $regionCode = $data['region_code'] ?? $rule->region_code;
                $dataCategory = $data['data_category'] ?? $rule->data_category;
                
                if ($this->repository->existsByRegionAndCategory($regionCode, $dataCategory, $rule->id)) {
                    return [
                        'success' => false,
                        'message' => 'Another rule already exists for this region and data category',
                        'error_code' => 'RULE_ALREADY_EXISTS'
                    ];
                }
            }
            
            // Set default values for JSON fields
            $data = $this->setDefaultJsonValues($data);
            
            // Validate effective dates
            if (isset($data['effective_from']) || isset($data['effective_to'])) {
                $effectiveFrom = $data['effective_from'] ?? $rule->effective_from;
                $effectiveTo = $data['effective_to'] ?? $rule->effective_to;
                
                if ($effectiveTo && $effectiveFrom > $effectiveTo) {
                    return [
                        'success' => false,
                        'message' => 'Effective from date must be before effective to date',
                        'error_code' => 'INVALID_DATE_RANGE'
                    ];
                }
            }
            
            // Validate retention periods
            if (isset($data['minimum_retention_period_years']) || isset($data['maximum_retention_period_years'])) {
                $minRetention = $data['minimum_retention_period_years'] ?? $rule->minimum_retention_period_years;
                $maxRetention = $data['maximum_retention_period_years'] ?? $rule->maximum_retention_period_years;
                
                if ($maxRetention && $minRetention > $maxRetention) {
                    return [
                        'success' => false,
                        'message' => 'Maximum retention period must be greater than or equal to minimum retention period',
                        'error_code' => 'INVALID_RETENTION_PERIOD'
                    ];
                }
            }
            
            // Update the rule
            $updatedRule = $this->repository->update($rule, $data);
            
            DB::commit();
            
            return [
                'success' => true,
                'data' => $updatedRule,
                'message' => 'Data residency rule updated successfully'
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to update data residency rule', [
                'id' => $id,
                'error' => $e->getMessage(),
                'data' => $data,
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to update data residency rule. Please try again later.',
                'error_code' => 'RULE_UPDATE_ERROR'
            ];
        }
    }

    /**
     * Delete a data residency rule
     *
     * @param int $id
     * @return array
     */
    public function deleteRule(int $id): array
    {
        DB::beginTransaction();
        
        try {
            $rule = $this->repository->findById($id);
            
            if (!$rule) {
                return [
                    'success' => false,
                    'message' => 'Data residency rule not found',
                    'error_code' => 'RULE_NOT_FOUND'
                ];
            }
            
            // Check if rule is active and still effective
            if ($rule->isEffective()) {
                return [
                    'success' => false,
                    'message' => 'Cannot delete an active and effective data residency rule',
                    'error_code' => 'RULE_ACTIVE'
                ];
            }
            
            $deleted = $this->repository->delete($rule);
            
            if (!$deleted) {
                return [
                    'success' => false,
                    'message' => 'Failed to delete data residency rule',
                    'error_code' => 'RULE_DELETION_ERROR'
                ];
            }
            
            DB::commit();
            
            return [
                'success' => true,
                'message' => 'Data residency rule deleted successfully'
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to delete data residency rule', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to delete data residency rule. Please try again later.',
                'error_code' => 'RULE_DELETION_ERROR'
            ];
        }
    }

    /**
     * Validate if data can be processed in a specific region
     *
     * @param string $dataCategory
     * @param string $processingRegion
     * @param string $storageRegion
     * @return array
     */
    public function validateDataProcessing(string $dataCategory, string $processingRegion, string $storageRegion): array
    {
        try {
            $rules = $this->repository->findByDataCategory($dataCategory);
            
            if ($rules->isEmpty()) {
                return [
                    'success' => false,
                    'message' => 'No data residency rules found for the specified data category',
                    'error_code' => 'NO_RULES_FOUND'
                ];
            }
            
            $applicableRules = $rules->filter(function ($rule) use ($processingRegion, $storageRegion) {
                $allowsProcessing = in_array($processingRegion, $rule->allowed_processing_regions);
                $allowsStorage = in_array($storageRegion, $rule->allowed_storage_regions);
                $notProhibited = !in_array($processingRegion, $rule->prohibited_regions ?? []) &&
                                !in_array($storageRegion, $rule->prohibited_regions ?? []);
                
                return $allowsProcessing && $allowsStorage && $notProhibited && $rule->isEffective();
            });
            
            if ($applicableRules->isEmpty()) {
                return [
                    'success' => false,
                    'message' => 'Data processing not allowed in the specified regions',
                    'error_code' => 'PROCESSING_NOT_ALLOWED',
                    'data' => [
                        'data_category' => $dataCategory,
                        'processing_region' => $processingRegion,
                        'storage_region' => $storageRegion,
                        'violations' => $this->getProcessingViolations($rules, $processingRegion, $storageRegion)
                    ]
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Data processing is allowed',
                'data' => [
                    'data_category' => $dataCategory,
                    'processing_region' => $processingRegion,
                    'storage_region' => $storageRegion,
                    'applicable_rules' => $applicableRules->values()
                ]
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to validate data processing', [
                'data_category' => $dataCategory,
                'processing_region' => $processingRegion,
                'storage_region' => $storageRegion,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to validate data processing. Please try again later.',
                'error_code' => 'VALIDATION_ERROR'
            ];
        }
    }

    /**
     * Get applicable rules for a data category and region
     *
     * @param string $dataCategory
     * @param string $regionCode
     * @return array
     */
    public function getApplicableRules(string $dataCategory, string $regionCode): array
    {
        try {
            $rules = $this->repository->findByDataCategory($dataCategory, true);
            
            $applicableRules = $rules->filter(function ($rule) use ($regionCode) {
                return in_array($regionCode, $rule->allowed_storage_regions) ||
                       in_array($regionCode, $rule->allowed_processing_regions) ||
                       in_array($regionCode, $rule->allowed_backup_regions);
            });
            
            return [
                'success' => true,
                'data' => [
                    'rules' => $applicableRules->values(),
                    'count' => $applicableRules->count()
                ],
                'message' => 'Applicable rules retrieved successfully'
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to get applicable rules', [
                'data_category' => $dataCategory,
                'region_code' => $regionCode,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve applicable rules. Please try again later.',
                'error_code' => 'RULES_FETCH_ERROR'
            ];
        }
    }

    /**
     * Check if cross-border transfer is allowed
     *
     * @param string $sourceRegion
     * @param string $targetRegion
     * @param string $dataCategory
     * @return array
     */
    public function validateCrossBorderTransfer(string $sourceRegion, string $targetRegion, string $dataCategory): array
    {
        try {
            $rules = $this->repository->findByDataCategory($dataCategory, true);
            
            $violations = [];
            $requirements = [];
            
            foreach ($rules as $rule) {
                if (!$rule->allowsCrossBorderTransferTo($targetRegion)) {
                    $violations[] = [
                        'rule_id' => $rule->id,
                        'rule_name' => $rule->display_name,
                        'reason' => 'Target region is prohibited',
                        'prohibited_regions' => $rule->prohibited_regions
                    ];
                }
                
                if ($rule->cross_border_transfer_approval_required) {
                    $requirements[] = [
                        'rule_id' => $rule->id,
                        'rule_name' => $rule->display_name,
                        'approval_required' => true,
                        'approval_authority' => $rule->approval_authority,
                        'transfer_mechanisms' => $rule->transfer_mechanisms
                    ];
                }
            }
            
            if (!empty($violations)) {
                return [
                    'success' => false,
                    'message' => 'Cross-border transfer is not allowed',
                    'error_code' => 'TRANSFER_NOT_ALLOWED',
                    'data' => [
                        'source_region' => $sourceRegion,
                        'target_region' => $targetRegion,
                        'data_category' => $dataCategory,
                        'violations' => $violations,
                        'requirements' => $requirements
                    ]
                ];
            }
            
            return [
                'success' => true,
                'message' => empty($requirements) ? 
                    'Cross-border transfer is allowed without additional requirements' :
                    'Cross-border transfer is allowed with additional requirements',
                'data' => [
                    'source_region' => $sourceRegion,
                    'target_region' => $targetRegion,
                    'data_category' => $dataCategory,
                    'requirements' => $requirements,
                    'allowed' => true
                ]
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to validate cross-border transfer', [
                'source_region' => $sourceRegion,
                'target_region' => $targetRegion,
                'data_category' => $dataCategory,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to validate cross-border transfer. Please try again later.',
                'error_code' => 'VALIDATION_ERROR'
            ];
        }
    }

    /**
     * Get active rules summary by region
     *
     * @return array
     */
    public function getRulesSummary(): array
    {
        try {
            $activeRules = $this->repository->getAllActive();
            
            $summary = [
                'total_rules' => $activeRules->count(),
                'by_region' => [],
                'by_category' => [],
                'by_status' => []
            ];
            
            foreach ($activeRules as $rule) {
                // Group by region
                $regionCode = $rule->region_code;
                if (!isset($summary['by_region'][$regionCode])) {
                    $summary['by_region'][$regionCode] = [
                        'region_name' => $rule->region_name,
                        'total_rules' => 0,
                        'categories' => []
                    ];
                }
                $summary['by_region'][$regionCode]['total_rules']++;
                $summary['by_region'][$regionCode]['categories'][] = $rule->data_category;
                
                // Group by category
                $category = $rule->data_category;
                if (!isset($summary['by_category'][$category])) {
                    $summary['by_category'][$category] = 0;
                }
                $summary['by_category'][$category]++;
                
                // Group by status
                $status = $rule->status;
                if (!isset($summary['by_status'][$status])) {
                    $summary['by_status'][$status] = 0;
                }
                $summary['by_status'][$status]++;
            }
            
            return [
                'success' => true,
                'data' => $summary,
                'message' => 'Rules summary retrieved successfully'
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to get rules summary', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve rules summary. Please try again later.',
                'error_code' => 'SUMMARY_FETCH_ERROR'
            ];
        }
    }

    /**
     * Validate rule data for business logic
     *
     * @param array $data
     * @param int|null $excludeId
     * @return array
     */
    private function validateRuleData(array $data, ?int $excludeId = null): array
    {
        // Validate region code format
        if (isset($data['region_code'])) {
            if (!preg_match('/^[A-Z]{2}(-[A-Z0-9]{2,7})?$/', $data['region_code'])) {
                return [
                    'success' => false,
                    'message' => 'Region code must be in format: XX or XX-XXXXX',
                    'error_code' => 'INVALID_REGION_CODE'
                ];
            }
        }
        
        // Validate data category
        if (isset($data['data_category']) && !array_key_exists($data['data_category'], DataResidencyRule::DATA_CATEGORIES)) {
            return [
                'success' => false,
                'message' => 'Invalid data category',
                'error_code' => 'INVALID_DATA_CATEGORY',
                'valid_categories' => array_keys(DataResidencyRule::DATA_CATEGORIES)
            ];
        }
        
        // Validate status
        if (isset($data['status']) && !array_key_exists($data['status'], DataResidencyRule::STATUSES)) {
            return [
                'success' => false,
                'message' => 'Invalid status',
                'error_code' => 'INVALID_STATUS',
                'valid_statuses' => array_keys(DataResidencyRule::STATUSES)
            ];
        }
        
        // Validate retention basis
        if (isset($data['retention_basis']) && !array_key_exists($data['retention_basis'], DataResidencyRule::RETENTION_BASIS)) {
            return [
                'success' => false,
                'message' => 'Invalid retention basis',
                'error_code' => 'INVALID_RETENTION_BASIS',
                'valid_bases' => array_keys(DataResidencyRule::RETENTION_BASIS)
            ];
        }
        
        // Validate JSON fields
        $jsonFields = [
            'allowed_storage_regions',
            'allowed_processing_regions',
            'allowed_backup_regions',
            'prohibited_regions',
            'encryption_requirements',
            'approval_authority',
            'transfer_mechanisms',
            'erasure_exceptions',
            'notification_authorities',
            'applicable_regulations'
        ];
        
        foreach ($jsonFields as $field) {
            if (isset($data[$field]) && !$this->isValidJson($data[$field])) {
                return [
                    'success' => false,
                    'message' => "Invalid JSON format for field: {$field}",
                    'error_code' => 'INVALID_JSON_FORMAT'
                ];
            }
        }
        
        return ['success' => true];
    }

    /**
     * Set default values for JSON fields
     *
     * @param array $data
     * @return array
     */
    private function setDefaultJsonValues(array $data): array
    {
        $defaults = [
            'allowed_storage_regions' => [],
            'allowed_processing_regions' => [],
            'allowed_backup_regions' => [],
            'prohibited_regions' => [],
            'encryption_requirements' => ['algorithm' => 'AES-256', 'key_length' => 256],
            'approval_authority' => [],
            'transfer_mechanisms' => [],
            'erasure_exceptions' => [],
            'notification_authorities' => [],
            'applicable_regulations' => []
        ];
        
        foreach ($defaults as $field => $defaultValue) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $data[$field] = json_encode($defaultValue);
            } elseif (is_array($data[$field])) {
                $data[$field] = json_encode($data[$field]);
            }
        }
        
        return $data;
    }

    /**
     * Validate effective dates
     *
     * @param array $data
     * @return bool
     */
    private function validateEffectiveDates(array $data): bool
    {
        if (isset($data['effective_to']) && isset($data['effective_from'])) {
            return $data['effective_from'] <= $data['effective_to'];
        }
        return true;
    }

    /**
     * Validate retention periods
     *
     * @param array $data
     * @return bool
     */
    private function validateRetentionPeriods(array $data): bool
    {
        if (isset($data['maximum_retention_period_years']) && 
            isset($data['minimum_retention_period_years'])) {
            return $data['minimum_retention_period_years'] <= $data['maximum_retention_period_years'];
        }
        return true;
    }

    /**
     * Check if value is valid JSON
     *
     * @param mixed $value
     * @return bool
     */
    private function isValidJson($value): bool
    {
        if (is_array($value)) {
            return true;
        }
        
        if (!is_string($value)) {
            return false;
        }
        
        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Get processing violations
     *
     * @param Collection $rules
     * @param string $processingRegion
     * @param string $storageRegion
     * @return array
     */
    private function getProcessingViolations( $rules, string $processingRegion, string $storageRegion): array
    {
        $violations = [];
        
        foreach ($rules as $rule) {
            $ruleViolations = [];
            
            if (!in_array($processingRegion, $rule->allowed_processing_regions)) {
                $ruleViolations[] = "Processing not allowed in region: {$processingRegion}";
            }
            
            if (!in_array($storageRegion, $rule->allowed_storage_regions)) {
                $ruleViolations[] = "Storage not allowed in region: {$storageRegion}";
            }
            
            if (in_array($processingRegion, $rule->prohibited_regions ?? [])) {
                $ruleViolations[] = "Region is prohibited: {$processingRegion}";
            }
            
            if (in_array($storageRegion, $rule->prohibited_regions ?? [])) {
                $ruleViolations[] = "Region is prohibited: {$storageRegion}";
            }
            
            if (!$rule->isEffective()) {
                $ruleViolations[] = "Rule is not currently effective";
            }
            
            if (!empty($ruleViolations)) {
                $violations[] = [
                    'rule_id' => $rule->id,
                    'rule_name' => $rule->display_name,
                    'violations' => $ruleViolations
                ];
            }
        }
        
        return $violations;
    }
}