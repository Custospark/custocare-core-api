<?php

declare(strict_types=1);

namespace App\Services\Lab;

use App\Models\LabResult;
use App\Repositories\Lab\Contracts\LabResultRepositoryInterface;
use App\Repositories\Lab\Contracts\LabRequestItemRepositoryInterface;
use App\Repositories\Lab\Contracts\LabTemplateFieldRepositoryInterface;
use App\Services\Lab\Contracts\LabResultServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LabResultService implements LabResultServiceInterface
{
    /**
     * @var LabResultRepositoryInterface
     */
    protected LabResultRepositoryInterface $resultRepository;

    /**
     * @var LabRequestItemRepositoryInterface
     */
    protected LabRequestItemRepositoryInterface $itemRepository;

    /**
     * @var LabTemplateFieldRepositoryInterface
     */
    protected LabTemplateFieldRepositoryInterface $fieldRepository;

    /**
     * Constructor.
     *
     * @param LabResultRepositoryInterface $resultRepository
     * @param LabRequestItemRepositoryInterface $itemRepository
     * @param LabTemplateFieldRepositoryInterface $fieldRepository
     */
    public function __construct(
        LabResultRepositoryInterface $resultRepository,
        LabRequestItemRepositoryInterface $itemRepository,
        LabTemplateFieldRepositoryInterface $fieldRepository
    ) {
        $this->resultRepository = $resultRepository;
        $this->itemRepository = $itemRepository;
        $this->fieldRepository = $fieldRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function getAllResults(array $filters = [], int $perPage = 20): array
    {
        try {
            $results = $this->resultRepository->getAllPaginated($filters, $perPage);
            
            return [
                'success' => true,
                'message' => 'Lab results retrieved successfully',
                'data' => [
                    'results' => $results,
                    'filters' => $filters,
                    'per_page' => $perPage,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve lab results', [
                'filters' => $filters,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve lab results',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getResultByUuid(string $uuid): array
    {
        try {
            $result = $this->resultRepository->findByUuid($uuid);
            
            if (!$result) {
                return [
                    'success' => false,
                    'message' => 'Lab result not found',
                    'error' => 'The requested lab result does not exist',
                    'data' => [],
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Lab result retrieved successfully',
                'data' => [
                    'result' => $result,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve lab result', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve lab result',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getResultById(int $id): array
    {
        try {
            $result = $this->resultRepository->findById($id);
            
            if (!$result) {
                return [
                    'success' => false,
                    'message' => 'Lab result not found',
                    'error' => 'The requested lab result does not exist',
                    'data' => [],
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Lab result retrieved successfully',
                'data' => [
                    'result' => $result,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve lab result', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve lab result',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createResult(array $data): array
    {
        try {
            // Validate lab request item exists
            $item = $this->itemRepository->findById($data['lab_request_item_id']);
            if (!$item) {
                return [
                    'success' => false,
                    'message' => 'Lab request item not found',
                    'error' => 'The specified lab request item does not exist',
                    'data' => [],
                ];
            }
            
            // Validate template field exists
            $field = $this->fieldRepository->findById($data['template_field_id']);
            if (!$field) {
                return [
                    'success' => false,
                    'message' => 'Template field not found',
                    'error' => 'The specified template field does not exist',
                    'data' => [],
                ];
            }
            
            // Check if result already exists for this field and item
            $existingResults = $this->resultRepository->getByLabRequestItem($item->id);
            $existingResult = $existingResults->firstWhere('template_field_id', $field->id);
            if ($existingResult) {
                return [
                    'success' => false,
                    'message' => 'Result already exists',
                    'error' => 'A result for this field already exists for this lab request item',
                    'data' => [],
                ];
            }
            
            // Validate the value against the field
            $validationResult = $this->validateValueAgainstField($field, $data['value'] ?? null);
            if (!$validationResult['is_valid']) {
                return [
                    'success' => false,
                    'message' => 'Value validation failed',
                    'error' => $validationResult['errors'][0] ?? 'Invalid value for this field',
                    'data' => [],
                ];
            }
            
            // Set reference ranges from field if not provided
            if (!isset($data['reference_min']) && $field->reference_min !== null) {
                $data['reference_min'] = $field->reference_min;
            }
            if (!isset($data['reference_max']) && $field->reference_max !== null) {
                $data['reference_max'] = $field->reference_max;
            }
            if (!isset($data['unit']) && $field->unit !== null) {
                $data['unit'] = $field->unit;
            }
            
            // Set flag based on value
            if (!isset($data['flag']) && isset($data['value'])) {
                $data['flag'] = $validationResult['flag'] ?? 'pending';
            }
            
            $result = $this->resultRepository->create($data);
            
            // Update parent item's result flag
            $item->updateResultFlagFromResults();
            
            // Check if critical alert needs to be sent
            if ($result->isCritical() && !$result->is_critical_alert_sent) {
                // Trigger critical alert (this could be queued or sent via event)
                Log::warning('Critical lab result detected', [
                    'result_uuid' => $result->result_uuid,
                    'lab_request_item_id' => $item->id,
                    'value' => $result->value,
                ]);
            }
            
            return [
                'success' => true,
                'message' => 'Lab result created successfully',
                'data' => [
                    'result' => $result->fresh(),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to create lab result', [
                'data' => $data,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to create lab result',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function updateResult(string $uuid, array $data): array
    {
        try {
            $result = $this->resultRepository->findByUuid($uuid);
            
            if (!$result) {
                return [
                    'success' => false,
                    'message' => 'Lab result not found',
                    'error' => 'The requested lab result does not exist',
                    'data' => [],
                ];
            }
            
            // Cannot update verified results
            if ($result->isVerified()) {
                return [
                    'success' => false,
                    'message' => 'Cannot update verified result',
                    'error' => 'This result has already been verified and cannot be modified',
                    'data' => [],
                ];
            }
            
            // Validate value if being updated
            if (isset($data['value'])) {
                $field = $this->fieldRepository->findById($result->template_field_id);
                if ($field) {
                    $validationResult = $this->validateValueAgainstField($field, $data['value']);
                    if (!$validationResult['is_valid']) {
                        return [
                            'success' => false,
                            'message' => 'Value validation failed',
                            'error' => $validationResult['errors'][0] ?? 'Invalid value for this field',
                            'data' => [],
                        ];
                    }
                    
                    // Update flag based on new value
                    if (!isset($data['flag'])) {
                        $data['flag'] = $validationResult['flag'] ?? 'pending';
                    }
                }
            }
            
            $updated = $this->resultRepository->update($result, $data);
            
            if (!$updated) {
                return [
                    'success' => false,
                    'message' => 'Failed to update lab result',
                    'error' => 'Unable to update lab result',
                    'data' => [],
                ];
            }
            
            // Update parent item's result flag
            $item = $this->itemRepository->findById($result->lab_request_item_id);
            if ($item) {
                $item->updateResultFlagFromResults();
            }
            
            // Check if critical alert needs to be sent
            if ($result->isCritical() && !$result->is_critical_alert_sent) {
                Log::warning('Critical lab result detected after update', [
                    'result_uuid' => $result->result_uuid,
                    'lab_request_item_id' => $result->lab_request_item_id,
                    'value' => $result->value,
                ]);
            }
            
            return [
                'success' => true,
                'message' => 'Lab result updated successfully',
                'data' => [
                    'result' => $result->fresh(),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to update lab result', [
                'uuid' => $uuid,
                'data' => $data,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to update lab result',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteResult(string $uuid): array
    {
        try {
            $result = $this->resultRepository->findByUuid($uuid);
            
            if (!$result) {
                return [
                    'success' => false,
                    'message' => 'Lab result not found',
                    'error' => 'The requested lab result does not exist',
                    'data' => [],
                ];
            }
            
            // Cannot delete verified results
            if ($result->isVerified()) {
                return [
                    'success' => false,
                    'message' => 'Cannot delete verified result',
                    'error' => 'Verified results cannot be deleted',
                    'data' => [],
                ];
            }
            
            $deleted = $this->resultRepository->delete($result);
            
            if (!$deleted) {
                return [
                    'success' => false,
                    'message' => 'Failed to delete lab result',
                    'error' => 'Unable to delete lab result',
                    'data' => [],
                ];
            }
            
            // Update parent item's result flag
            $item = $this->itemRepository->findById($result->lab_request_item_id);
            if ($item) {
                $item->updateResultFlagFromResults();
            }
            
            return [
                'success' => true,
                'message' => 'Lab result deleted successfully',
                'data' => [],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to delete lab result', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to delete lab result',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function verifyResult(string $uuid, int $verifiedByStaffId): array
    {
        try {
            $result = $this->resultRepository->findByUuid($uuid);
            
            if (!$result) {
                return [
                    'success' => false,
                    'message' => 'Lab result not found',
                    'error' => 'The requested lab result does not exist',
                    'data' => [],
                ];
            }
            
            if ($result->isVerified()) {
                return [
                    'success' => false,
                    'message' => 'Result already verified',
                    'error' => 'This result has already been verified',
                    'data' => [],
                ];
            }
            
            $verified = $this->resultRepository->verify($result, $verifiedByStaffId);
            
            if (!$verified) {
                return [
                    'success' => false,
                    'message' => 'Failed to verify result',
                    'error' => 'Unable to verify result',
                    'data' => [],
                ];
            }
            
            // Update parent item's status if all results are verified
            $item = $this->itemRepository->findById($result->lab_request_item_id);
            if ($item && $item->areAllResultsVerified() && $item->isCompleted()) {
                $this->itemRepository->markVerified($item, $verifiedByStaffId);
            }
            
            return [
                'success' => true,
                'message' => 'Result verified successfully',
                'data' => [
                    'result' => $result->fresh(),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to verify result', [
                'uuid' => $uuid,
                'verified_by_staff_id' => $verifiedByStaffId,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to verify result',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getResultsByLabRequestItem(string $itemUuid): array
    {
        try {
            $item = $this->itemRepository->findByUuid($itemUuid);
            
            if (!$item) {
                return [
                    'success' => false,
                    'message' => 'Lab request item not found',
                    'error' => 'The requested lab request item does not exist',
                    'data' => [],
                ];
            }
            
            $results = $this->resultRepository->getByLabRequestItem($item->id);
            
            return [
                'success' => true,
                'message' => 'Results retrieved successfully',
                'data' => [
                    'results' => $results,
                    'item' => $item,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve results by lab request item', [
                'item_uuid' => $itemUuid,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve results',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getResultsByTemplateField(string $fieldUuid, array $filters = []): array
    {
        try {
            $field = $this->fieldRepository->findByUuid($fieldUuid);
            
            if (!$field) {
                return [
                    'success' => false,
                    'message' => 'Template field not found',
                    'error' => 'The requested template field does not exist',
                    'data' => [],
                ];
            }
            
            $results = $this->resultRepository->getByTemplateField($field->id, $filters);
            
            return [
                'success' => true,
                'message' => 'Results retrieved successfully',
                'data' => [
                    'results' => $results,
                    'field' => $field,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve results by template field', [
                'field_uuid' => $fieldUuid,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve results',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getResultsByFlag(string $flag, ?int $facilityId = null): array
    {
        try {
            $results = $this->resultRepository->getByFlag($flag, $facilityId);
            
            return [
                'success' => true,
                'message' => 'Results retrieved successfully',
                'data' => [
                    'results' => $results,
                    'flag' => $flag,
                    'facility_id' => $facilityId,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve results by flag', [
                'flag' => $flag,
                'facility_id' => $facilityId,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve results',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getAbnormalResults(?int $facilityId = null): array
    {
        try {
            $results = $this->resultRepository->getAbnormalResults($facilityId);
            
            return [
                'success' => true,
                'message' => 'Abnormal results retrieved successfully',
                'data' => [
                    'results' => $results,
                    'facility_id' => $facilityId,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve abnormal results', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve abnormal results',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getCriticalResults(?int $facilityId = null): array
    {
        try {
            $results = $this->resultRepository->getCriticalResults($facilityId);
            
            return [
                'success' => true,
                'message' => 'Critical results retrieved successfully',
                'data' => [
                    'results' => $results,
                    'facility_id' => $facilityId,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve critical results', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve critical results',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getCriticalResultsRequiringAttention(int $facilityId): array
    {
        try {
            $results = $this->resultRepository->getCriticalResultsRequiringAttention($facilityId);
            
            return [
                'success' => true,
                'message' => 'Critical results requiring attention retrieved successfully',
                'data' => [
                    'results' => $results,
                    'facility_id' => $facilityId,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve critical results requiring attention', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve critical results',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getUnverifiedResults(?int $facilityId = null): array
    {
        try {
            $results = $this->resultRepository->getUnverifiedResults($facilityId);
            
            return [
                'success' => true,
                'message' => 'Unverified results retrieved successfully',
                'data' => [
                    'results' => $results,
                    'facility_id' => $facilityId,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve unverified results', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve unverified results',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getResultsByPatient(int $patientId, array $filters = [], int $perPage = 20): array
    {
        try {
            $results = $this->resultRepository->getByPatient($patientId, $filters, $perPage);
            
            return [
                'success' => true,
                'message' => 'Patient results retrieved successfully',
                'data' => [
                    'results' => $results,
                    'patient_id' => $patientId,
                    'filters' => $filters,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve results by patient', [
                'patient_id' => $patientId,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve patient results',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function bulkCreateResults(string $itemUuid, array $results): array
    {
        try {
            $item = $this->itemRepository->findByUuid($itemUuid);
            
            if (!$item) {
                return [
                    'success' => false,
                    'message' => 'Lab request item not found',
                    'error' => 'The requested lab request item does not exist',
                    'data' => [],
                ];
            }
            
            // Validate all results before creating
            foreach ($results as $resultData) {
                $field = $this->fieldRepository->findById($resultData['template_field_id']);
                if (!$field) {
                    return [
                        'success' => false,
                        'message' => 'Template field not found',
                        'error' => "Template field with ID {$resultData['template_field_id']} does not exist",
                        'data' => [],
                    ];
                }
                
                $validationResult = $this->validateValueAgainstField($field, $resultData['value'] ?? null);
                if (!$validationResult['is_valid']) {
                    return [
                        'success' => false,
                        'message' => 'Value validation failed',
                        'error' => "Invalid value for field '{$field->name}': {$validationResult['errors'][0]}",
                        'data' => [],
                    ];
                }
            }
            
            // Prepare results data
            foreach ($results as &$resultData) {
                $field = $this->fieldRepository->findById($resultData['template_field_id']);
                if (!isset($resultData['reference_min']) && $field->reference_min !== null) {
                    $resultData['reference_min'] = $field->reference_min;
                }
                if (!isset($resultData['reference_max']) && $field->reference_max !== null) {
                    $resultData['reference_max'] = $field->reference_max;
                }
                if (!isset($resultData['unit']) && $field->unit !== null) {
                    $resultData['unit'] = $field->unit;
                }
                if (!isset($resultData['flag']) && isset($resultData['value'])) {
                    $validationResult = $this->validateValueAgainstField($field, $resultData['value']);
                    $resultData['flag'] = $validationResult['flag'] ?? 'pending';
                }
            }
            
            $createdResults = $this->resultRepository->bulkCreate($item->id, $results);
            
            return [
                'success' => true,
                'message' => count($createdResults) . ' results created successfully',
                'data' => [
                    'results' => $createdResults,
                    'item' => $item,
                    'total_created' => count($createdResults),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to bulk create results', [
                'item_uuid' => $itemUuid,
                'results_count' => count($results),
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to create results',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getResultWithRelations(string $uuid): array
    {
        try {
            $result = $this->resultRepository->findByUuid($uuid);
            
            if (!$result) {
                return [
                    'success' => false,
                    'message' => 'Lab result not found',
                    'error' => 'The requested lab result does not exist',
                    'data' => [],
                ];
            }
            
            $resultWithRelations = $this->resultRepository->getWithRelations($result->id);
            
            return [
                'success' => true,
                'message' => 'Result with relations retrieved successfully',
                'data' => [
                    'result' => $resultWithRelations,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve result with relations', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve result',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getResultStatistics(int $facilityId, string $startDate, string $endDate): array
    {
        try {
            $statistics = $this->resultRepository->getResultStatistics($facilityId, $startDate, $endDate);
            
            return [
                'success' => true,
                'message' => 'Result statistics retrieved successfully',
                'data' => $statistics,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve result statistics', [
                'facility_id' => $facilityId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve statistics',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getResultTrends(string $testUuid, int $patientId, int $limit = 10): array
    {
        try {
            $test = $this->itemRepository->findByUuid($testUuid);
            
            if (!$test) {
                return [
                    'success' => false,
                    'message' => 'Lab test not found',
                    'error' => 'The requested lab test does not exist',
                    'data' => [],
                ];
            }
            
            $trends = $this->resultRepository->getResultTrends($test->id, $patientId, $limit);
            
            return [
                'success' => true,
                'message' => 'Result trends retrieved successfully',
                'data' => [
                    'trends' => $trends,
                    'test' => $test,
                    'patient_id' => $patientId,
                    'limit' => $limit,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve result trends', [
                'test_uuid' => $testUuid,
                'patient_id' => $patientId,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve trends',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function markCriticalAlertSent(string $uuid): array
    {
        try {
            $result = $this->resultRepository->findByUuid($uuid);
            
            if (!$result) {
                return [
                    'success' => false,
                    'message' => 'Lab result not found',
                    'error' => 'The requested lab result does not exist',
                    'data' => [],
                ];
            }
            
            if (!$result->isCritical()) {
                return [
                    'success' => false,
                    'message' => 'Result is not critical',
                    'error' => 'Only critical results can have alerts marked as sent',
                    'data' => [],
                ];
            }
            
            $marked = $this->resultRepository->markCriticalAlertSent($result);
            
            if (!$marked) {
                return [
                    'success' => false,
                    'message' => 'Failed to mark critical alert as sent',
                    'error' => 'Unable to update result',
                    'data' => [],
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Critical alert marked as sent successfully',
                'data' => [
                    'result' => $result->fresh(),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to mark critical alert as sent', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to mark critical alert',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function recalculateResultFlag(string $uuid): array
    {
        try {
            $result = $this->resultRepository->findByUuid($uuid);
            
            if (!$result) {
                return [
                    'success' => false,
                    'message' => 'Lab result not found',
                    'error' => 'The requested lab result does not exist',
                    'data' => [],
                ];
            }
            
            $recalculated = $this->resultRepository->updateFlagFromValue($result);
            
            if (!$recalculated) {
                return [
                    'success' => false,
                    'message' => 'Failed to recalculate result flag',
                    'error' => 'Unable to update result flag',
                    'data' => [],
                ];
            }
            
            // Update parent item's result flag
            $item = $this->itemRepository->findById($result->lab_request_item_id);
            if ($item) {
                $item->updateResultFlagFromResults();
            }
            
            return [
                'success' => true,
                'message' => 'Result flag recalculated successfully',
                'data' => [
                    'result' => $result->fresh(),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to recalculate result flag', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to recalculate flag',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function exportResults(array $filters = []): array
    {
        try {
            $results = $this->resultRepository->getAllPaginated($filters, 10000);
            
            $exportData = [];
            foreach ($results as $result) {
                $exportData[] = [
                    'result_uuid' => $result->result_uuid,
                    'value' => $result->value,
                    'unit' => $result->unit,
                    'numeric_value' => $result->numeric_value,
                    'flag' => $result->flag,
                    'interpretation' => $result->interpretation,
                    'recorded_at' => $result->recorded_at,
                    'verified_at' => $result->verified_at,
                    'field_name' => $result->templateField->name ?? null,
                    'test_name' => $result->labRequestItem->labTest->name ?? null,
                    'patient_id' => $result->labRequestItem->labRequest->patient_id ?? null,
                    'facility_id' => $result->labRequestItem->labRequest->facility_id ?? null,
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Results exported successfully',
                'data' => [
                    'export_data' => $exportData,
                    'total_records' => count($exportData),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to export results', [
                'filters' => $filters,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to export results',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * Validate value against field.
     *
     * @param mixed $field
     * @param mixed $value
     * @return array
     */
    protected function validateValueAgainstField($field, $value): array
    {
        $isValid = true;
        $errors = [];
        $flag = 'normal';
        
        // Check if field is required and value is empty
        if ($field->is_required && ($value === null || $value === '')) {
            $isValid = false;
            $errors[] = 'This field is required';
        }
        
        // Validate based on data type
        if ($value !== null && $value !== '') {
            switch ($field->data_type) {
                case 'number':
                    if (!is_numeric($value)) {
                        $isValid = false;
                        $errors[] = 'Value must be a number';
                    } else {
                        $numericValue = (float) $value;
                        $flag = $field->determineFlag($numericValue);
                        
                        if ($flag !== 'normal') {
                            $errors[] = "Value is {$flag}";
                        }
                    }
                    break;
                    
                case 'boolean':
                    if (!in_array($value, [true, false, 0, 1, '0', '1', 'true', 'false'])) {
                        $isValid = false;
                        $errors[] = 'Value must be a boolean';
                    }
                    break;
                    
                case 'select':
                    // For select fields, validate against allowed options from metadata
                    // This is a placeholder - implement based on future requirements
                    break;
            }
        }
        
        return [
            'is_valid' => $isValid,
            'errors' => $errors,
            'flag' => $flag,
        ];
    }
}