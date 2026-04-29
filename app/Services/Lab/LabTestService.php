<?php

declare(strict_types=1);

namespace App\Services\Lab;

use App\Models\LabTest;
use App\Repositories\Lab\Contracts\LabTestRepositoryInterface;
use App\Repositories\Lab\Contracts\LabTemplateRepositoryInterface;
use App\Services\Lab\Contracts\LabTestServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LabTestService implements LabTestServiceInterface
{
    /**
     * @var LabTestRepositoryInterface
     */
    protected LabTestRepositoryInterface $testRepository;

    /**
     * @var LabTemplateRepositoryInterface
     */
    protected LabTemplateRepositoryInterface $templateRepository;

    /**
     * Constructor.
     *
     * @param LabTestRepositoryInterface $testRepository
     * @param LabTemplateRepositoryInterface $templateRepository
     */
    public function __construct(
        LabTestRepositoryInterface $testRepository,
        LabTemplateRepositoryInterface $templateRepository
    ) {
        $this->testRepository = $testRepository;
        $this->templateRepository = $templateRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function getAllTests(array $filters = [], int $perPage = 20): array
    {
        try {
            $tests = $this->testRepository->getAllPaginated($filters, $perPage);
            
            return [
                'success' => true,
                'message' => 'Tests retrieved successfully',
                'data' => [
                    'tests' => $tests,
                    'filters' => $filters,
                    'per_page' => $perPage,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve tests', [
                'filters' => $filters,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve tests',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getTestByUuid(string $uuid): array
    {
        try {
            $test = $this->testRepository->findByUuid($uuid);
            
            if (!$test) {
                return [
                    'success' => false,
                    'message' => 'Test not found',
                    'error' => 'The requested test does not exist',
                    'data' => [],
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Test retrieved successfully',
                'data' => [
                    'test' => $test,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve test', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve test',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getTestById(int $id): array
    {
        try {
            $test = $this->testRepository->findById($id);
            
            if (!$test) {
                return [
                    'success' => false,
                    'message' => 'Test not found',
                    'error' => 'The requested test does not exist',
                    'data' => [],
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Test retrieved successfully',
                'data' => [
                    'test' => $test,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve test', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve test',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }
/**
 * {@inheritdoc}
 */
public function createTest(array $data): array
{
    try {
        // Only validate template if template_id is provided and not null
        if (isset($data['template_id']) && !is_null($data['template_id'])) {
            $template = $this->templateRepository->findById($data['template_id']);
            if (!$template) {
                return [
                    'success' => false,
                    'message' => 'Template not found',
                    'error' => 'The specified template does not exist',
                    'data' => [],
                ];
            }
        } else {
            // Remove template_id from data if it's null or not set
            unset($data['template_id']);
        }
        
        // Validate name uniqueness
        if ($this->testRepository->existsByName($data['name'], $data['facility_id'] ?? null)) {
            return [
                'success' => false,
                'message' => 'Test name already exists',
                'error' => 'A test with this name already exists for this facility',
                'data' => [],
            ];
        }
        
        // Validate code uniqueness if provided
        if (!empty($data['code'])) {
            $existingTest = $this->testRepository->findByCode($data['code'], $data['facility_id'] ?? null);
            if ($existingTest) {
                return [
                    'success' => false,
                    'message' => 'Test code already exists',
                    'error' => 'A test with this code already exists',
                    'data' => [],
                ];
            }
        }
        
        $test = $this->testRepository->create($data);
        
        return [
            'success' => true,
            'message' => 'Test created successfully',
            'data' => [
                'test' => $test,
            ],
        ];
    } catch (\Exception $e) {
        Log::error('Failed to create test', [
            'data' => $data,
            'error' => $e->getMessage(),
        ]);
        
        return [
            'success' => false,
            'message' => 'Failed to create test',
            'error' => 'An internal server error occurred',
            'data' => [],
        ];
    }
}

    /**
     * {@inheritdoc}
     */
    public function updateTest(string $uuid, array $data): array
{
    try {
        $test = $this->testRepository->findByUuid($uuid);
        
        if (!$test) {
            return [
                'success' => false,
                'message' => 'Test not found',
                'error' => 'The requested test does not exist',
                'data' => [],
            ];
        }
        
        // Validate template exists if being updated and not null
        if (array_key_exists('template_id', $data)) {
            // If template_id is explicitly set to null, allow it (removes association)
            if (!is_null($data['template_id'])) {
                $template = $this->templateRepository->findById($data['template_id']);
                if (!$template) {
                    return [
                        'success' => false,
                        'message' => 'Template not found',
                        'error' => 'The specified template does not exist',
                        'data' => [],
                    ];
                }
            }
        }
        
        // Validate name uniqueness
        if (isset($data['name']) && $this->testRepository->existsByName($data['name'], $test->facility_id, $test->id)) {
            return [
                'success' => false,
                'message' => 'Test name already exists',
                'error' => 'A test with this name already exists for this facility',
                'data' => [],
            ];
        }
        
        // Validate code uniqueness if provided
        if (!empty($data['code'])) {
            $existingTest = $this->testRepository->findByCode($data['code'], $test->facility_id);
            if ($existingTest && $existingTest->id !== $test->id) {
                return [
                    'success' => false,
                    'message' => 'Test code already exists',
                    'error' => 'A test with this code already exists',
                    'data' => [],
                ];
            }
        }
        
        $updated = $this->testRepository->update($test, $data);
        
        if (!$updated) {
            return [
                'success' => false,
                'message' => 'Failed to update test',
                'error' => 'Unable to update test',
                'data' => [],
            ];
        }
        
        return [
            'success' => true,
            'message' => 'Test updated successfully',
            'data' => [
                'test' => $test->fresh(),
            ],
        ];
    } catch (\Exception $e) {
        Log::error('Failed to update test', [
            'uuid' => $uuid,
            'data' => $data,
            'error' => $e->getMessage(),
        ]);
        
        return [
            'success' => false,
            'message' => 'Failed to update test',
            'error' => 'An internal server error occurred',
            'data' => [],
        ];
    }
}

    /**
     * {@inheritdoc}
     */
    public function deleteTest(string $uuid): array
    {
        try {
            $test = $this->testRepository->findByUuid($uuid);
            
            if (!$test) {
                return [
                    'success' => false,
                    'message' => 'Test not found',
                    'error' => 'The requested test does not exist',
                    'data' => [],
                ];
            }
            
            // Check if test has associated request items
            if ($test->requestItems()->count() > 0) {
                return [
                    'success' => false,
                    'message' => 'Cannot delete test with associated lab requests',
                    'error' => 'This test has been used in lab requests and cannot be deleted',
                    'data' => [],
                ];
            }
            
            $deleted = $this->testRepository->delete($test);
            
            if (!$deleted) {
                return [
                    'success' => false,
                    'message' => 'Failed to delete test',
                    'error' => 'Unable to delete test',
                    'data' => [],
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Test deleted successfully',
                'data' => [],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to delete test', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to delete test',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function activateTest(string $uuid): array
    {
        try {
            $test = $this->testRepository->findByUuid($uuid);
            
            if (!$test) {
                return [
                    'success' => false,
                    'message' => 'Test not found',
                    'error' => 'The requested test does not exist',
                    'data' => [],
                ];
            }
            
            $activated = $this->testRepository->activate($test);
            
            if (!$activated) {
                return [
                    'success' => false,
                    'message' => 'Failed to activate test',
                    'error' => 'Unable to activate test',
                    'data' => [],
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Test activated successfully',
                'data' => [
                    'test' => $test->fresh(),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to activate test', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to activate test',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deactivateTest(string $uuid): array
    {
        try {
            $test = $this->testRepository->findByUuid($uuid);
            
            if (!$test) {
                return [
                    'success' => false,
                    'message' => 'Test not found',
                    'error' => 'The requested test does not exist',
                    'data' => [],
                ];
            }
            
            $deactivated = $this->testRepository->deactivate($test);
            
            if (!$deactivated) {
                return [
                    'success' => false,
                    'message' => 'Failed to deactivate test',
                    'error' => 'Unable to deactivate test',
                    'data' => [],
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Test deactivated successfully',
                'data' => [
                    'test' => $test->fresh(),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to deactivate test', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to deactivate test',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getTestsByTemplate(string $templateUuid, array $filters = []): array
    {
        try {
            $template = $this->templateRepository->findByUuid($templateUuid);
            
            if (!$template) {
                return [
                    'success' => false,
                    'message' => 'Template not found',
                    'error' => 'The requested template does not exist',
                    'data' => [],
                ];
            }
            
            $tests = $this->testRepository->getByTemplate($template->id, $filters);
            
            return [
                'success' => true,
                'message' => 'Tests retrieved successfully',
                'data' => [
                    'tests' => $tests,
                    'template' => $template,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve tests by template', [
                'template_uuid' => $templateUuid,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve tests',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getTestsByFacility(int $facilityId, array $filters = []): array
    {
        try {
            $tests = $this->testRepository->getByFacility($facilityId, $filters);
            
            return [
                'success' => true,
                'message' => 'Tests retrieved successfully',
                'data' => [
                    'tests' => $tests,
                    'facility_id' => $facilityId,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve tests by facility', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve tests',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getTestsByCategory(string $category, ?int $facilityId = null): array
    {
        try {
            $tests = $this->testRepository->getByCategory($category, $facilityId);
            
            return [
                'success' => true,
                'message' => 'Tests retrieved successfully',
                'data' => [
                    'tests' => $tests,
                    'category' => $category,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve tests by category', [
                'category' => $category,
                'facility_id' => $facilityId,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve tests',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getTestsRequiringFasting(?int $facilityId = null): array
    {
        try {
            $tests = $this->testRepository->getTestsRequiringFasting($facilityId);
            
            return [
                'success' => true,
                'message' => 'Tests requiring fasting retrieved successfully',
                'data' => [
                    'tests' => $tests,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve tests requiring fasting', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve tests',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getTestStatistics(string $uuid): array
    {
        try {
            $test = $this->testRepository->findByUuid($uuid);
            
            if (!$test) {
                return [
                    'success' => false,
                    'message' => 'Test not found',
                    'error' => 'The requested test does not exist',
                    'data' => [],
                ];
            }
            
            $statistics = $this->testRepository->getTestStatistics($test->id);
            
            return [
                'success' => true,
                'message' => 'Test statistics retrieved successfully',
                'data' => [
                    'test' => $test,
                    'statistics' => $statistics,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve test statistics', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve test statistics',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getPopularTests(int $facilityId, int $limit = 10): array
    {
        try {
            $tests = $this->testRepository->getPopularTests($facilityId, $limit);
            
            return [
                'success' => true,
                'message' => 'Popular tests retrieved successfully',
                'data' => [
                    'tests' => $tests,
                    'limit' => $limit,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve popular tests', [
                'facility_id' => $facilityId,
                'limit' => $limit,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve popular tests',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getTestWithTemplate(string $uuid): array
    {
        try {
            $test = $this->testRepository->findByUuid($uuid);
            
            if (!$test) {
                return [
                    'success' => false,
                    'message' => 'Test not found',
                    'error' => 'The requested test does not exist',
                    'data' => [],
                ];
            }
            
            $testWithTemplate = $this->testRepository->getWithTemplate($test->id);
            
            return [
                'success' => true,
                'message' => 'Test with template retrieved successfully',
                'data' => [
                    'test' => $testWithTemplate,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve test with template', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve test',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function bulkAssignToTemplate(string $templateUuid, array $testIds): array
    {
        try {
            $template = $this->templateRepository->findByUuid($templateUuid);
            
            if (!$template) {
                return [
                    'success' => false,
                    'message' => 'Template not found',
                    'error' => 'The requested template does not exist',
                    'data' => [],
                ];
            }
            
            $assignedTests = [];
            $failedTests = [];
            
            foreach ($testIds as $testId) {
                $test = $this->testRepository->findById($testId);
                if ($test) {
                    $updated = $this->testRepository->update($test, ['template_id' => $template->id]);
                    if ($updated) {
                        $assignedTests[] = $test;
                    } else {
                        $failedTests[] = $testId;
                    }
                } else {
                    $failedTests[] = $testId;
                }
            }
            
            return [
                'success' => true,
                'message' => count($assignedTests) . ' tests assigned successfully',
                'data' => [
                    'assigned_tests' => $assignedTests,
                    'failed_test_ids' => $failedTests,
                    'total_processed' => count($testIds),
                    'total_assigned' => count($assignedTests),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to bulk assign tests to template', [
                'template_uuid' => $templateUuid,
                'test_ids' => $testIds,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to assign tests to template',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }
}