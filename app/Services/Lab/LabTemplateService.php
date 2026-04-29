<?php

declare(strict_types=1);

namespace App\Services\Lab;

use App\Models\LabTemplate;
use App\Repositories\Lab\Contracts\LabTemplateRepositoryInterface;
use App\Services\Lab\Contracts\LabTemplateServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LabTemplateService implements LabTemplateServiceInterface
{
    /**
     * @var LabTemplateRepositoryInterface
     */
    protected LabTemplateRepositoryInterface $templateRepository;

    /**
     * Constructor.
     *
     * @param LabTemplateRepositoryInterface $templateRepository
     */
    public function __construct(LabTemplateRepositoryInterface $templateRepository)
    {
        $this->templateRepository = $templateRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function getAllTemplates(array $filters = [], int $perPage = 20): array
    {
        try {
            $templates = $this->templateRepository->getAllPaginated($filters, $perPage);
            
            return [
                'success' => true,
                'message' => 'Templates retrieved successfully',
                'data' => [
                    'templates' => $templates,
                    'filters' => $filters,
                    'per_page' => $perPage,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve templates', [
                'filters' => $filters,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve templates',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getTemplateByUuid(string $uuid): array
    {
        try {
            $template = $this->templateRepository->findByUuid($uuid);
            
            if (!$template) {
                return [
                    'success' => false,
                    'message' => 'Template not found',
                    'error' => 'The requested template does not exist',
                    'data' => [],
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Template retrieved successfully',
                'data' => [
                    'template' => $template,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve template', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve template',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getTemplateById(int $id): array
    {
        try {
            $template = $this->templateRepository->findById($id);
            
            if (!$template) {
                return [
                    'success' => false,
                    'message' => 'Template not found',
                    'error' => 'The requested template does not exist',
                    'data' => [],
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Template retrieved successfully',
                'data' => [
                    'template' => $template,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve template', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve template',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createTemplate(array $data): array
{
    try {
        // Check if template already exists (including inactive ones)
        $existingTemplate = $this->templateRepository->findByNameAndFacility(
            $data['name'], 
            $data['facility_id'] ?? null
        );
        
        if ($existingTemplate) {
            // If template exists but is inactive, reactivate it
            if (!$existingTemplate->is_active) {
                $existingTemplate->is_active = true;
                $existingTemplate->save();
                
                Log::info('Reactivated existing template', [
                    'template_id' => $existingTemplate->id,
                    'name' => $existingTemplate->name,
                    'facility_id' => $existingTemplate->facility_id,
                ]);
                
                return [
                    'success' => true,
                    'message' => 'Template reactivated successfully',
                    'data' => [
                        'template' => $existingTemplate,
                        'was_reactivated' => true,
                    ],
                ];
            }
            
            // If template exists and is active, return error
            return [
                'success' => false,
                'message' => 'Template name already exists',
                'error' => 'An active template with this name already exists for this facility',
                'data' => [],
            ];
        }
        
        // Create new template
        $template = $this->templateRepository->create($data);
        
        Log::info('Created new template', [
            'template_id' => $template->id,
            'name' => $template->name,
            'facility_id' => $template->facility_id,
        ]);
        
        return [
            'success' => true,
            'message' => 'Template created successfully',
            'data' => [
                'template' => $template,
                'was_reactivated' => false,
            ],
        ];
    } catch (\Exception $e) {
        Log::error('Failed to create or reactivate template', [
            'data' => $data,
            'error' => $e->getMessage(),
        ]);
        
        return [
            'success' => false,
            'message' => 'Failed to process template',
            'error' => 'An internal server error occurred',
            'data' => [],
        ];
    }
}

    /**
     * {@inheritdoc}
     */
    public function updateTemplate(string $uuid, array $data): array
    {
        try {
            $template = $this->templateRepository->findByUuid($uuid);
            
            if (!$template) {
                return [
                    'success' => false,
                    'message' => 'Template not found',
                    'error' => 'The requested template does not exist',
                    'data' => [],
                ];
            }
            
            // Validate name uniqueness
            if (isset($data['name']) && $this->templateRepository->existsByName($data['name'], $template->facility_id, $template->id)) {
                return [
                    'success' => false,
                    'message' => 'Template name already exists',
                    'error' => 'A template with this name already exists for this facility',
                    'data' => [],
                ];
            }
            
            $updated = $this->templateRepository->update($template, $data);
            
            if (!$updated) {
                return [
                    'success' => false,
                    'message' => 'Failed to update template',
                    'error' => 'Unable to update template',
                    'data' => [],
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Template updated successfully',
                'data' => [
                    'template' => $template->fresh(),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to update template', [
                'uuid' => $uuid,
                'data' => $data,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to update template',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteTemplate(string $uuid): array
    {
        try {
            $template = $this->templateRepository->findByUuid($uuid);
            
            if (!$template) {
                return [
                    'success' => false,
                    'message' => 'Template not found',
                    'error' => 'The requested template does not exist',
                    'data' => [],
                ];
            }
            
            // Check if template has associated tests
            if ($template->tests()->count() > 0) {
                return [
                    'success' => false,
                    'message' => 'Cannot delete template with associated tests',
                    'error' => 'Please remove all tests from this template first',
                    'data' => [],
                ];
            }
            
            $deleted = $this->templateRepository->delete($template);
            
            if (!$deleted) {
                return [
                    'success' => false,
                    'message' => 'Failed to delete template',
                    'error' => 'Unable to delete template',
                    'data' => [],
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Template deleted successfully',
                'data' => [],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to delete template', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to delete template',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function activateTemplate(string $uuid): array
    {
        try {
            $template = $this->templateRepository->findByUuid($uuid);
            
            if (!$template) {
                return [
                    'success' => false,
                    'message' => 'Template not found',
                    'error' => 'The requested template does not exist',
                    'data' => [],
                ];
            }
            
            $activated = $this->templateRepository->activate($template);
            
            if (!$activated) {
                return [
                    'success' => false,
                    'message' => 'Failed to activate template',
                    'error' => 'Unable to activate template',
                    'data' => [],
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Template activated successfully',
                'data' => [
                    'template' => $template->fresh(),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to activate template', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to activate template',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deactivateTemplate(string $uuid): array
    {
        try {
            $template = $this->templateRepository->findByUuid($uuid);
            
            if (!$template) {
                return [
                    'success' => false,
                    'message' => 'Template not found',
                    'error' => 'The requested template does not exist',
                    'data' => [],
                ];
            }
            
            $deactivated = $this->templateRepository->deactivate($template);
            
            if (!$deactivated) {
                return [
                    'success' => false,
                    'message' => 'Failed to deactivate template',
                    'error' => 'Unable to deactivate template',
                    'data' => [],
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Template deactivated successfully',
                'data' => [
                    'template' => $template->fresh(),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to deactivate template', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to deactivate template',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getActiveTemplates(?int $facilityId = null): array
    {
        try {
            $templates = $this->templateRepository->getActiveTemplates($facilityId);
            
            return [
                'success' => true,
                'message' => 'Active templates retrieved successfully',
                'data' => [
                    'templates' => $templates,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve active templates', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve active templates',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getSharedTemplates(): array
    {
        try {
            $templates = $this->templateRepository->getSharedTemplates();
            
            return [
                'success' => true,
                'message' => 'Shared templates retrieved successfully',
                'data' => [
                    'templates' => $templates,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve shared templates', [
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve shared templates',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getTemplatesByFacility(int $facilityId, array $filters = []): array
    {
        try {
            $templates = $this->templateRepository->getByFacility($facilityId, $filters);
            
            return [
                'success' => true,
                'message' => 'Templates retrieved successfully',
                'data' => [
                    'templates' => $templates,
                    'facility_id' => $facilityId,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve templates by facility', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve templates',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getTemplatesByStructureType(string $structureType, ?int $facilityId = null): array
    {
        try {
            $templates = $this->templateRepository->getByStructureType($structureType, $facilityId);
            
            return [
                'success' => true,
                'message' => 'Templates retrieved successfully',
                'data' => [
                    'templates' => $templates,
                    'structure_type' => $structureType,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve templates by structure type', [
                'structure_type' => $structureType,
                'facility_id' => $facilityId,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve templates',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getTemplateWithRelations(string $uuid): array
    {
        try {
            $template = $this->templateRepository->findByUuid($uuid);
            
            if (!$template) {
                return [
                    'success' => false,
                    'message' => 'Template not found',
                    'error' => 'The requested template does not exist',
                    'data' => [],
                ];
            }
            
            $templateWithRelations = $this->templateRepository->getWithRelations($template->id);
            
            return [
                'success' => true,
                'message' => 'Template with relations retrieved successfully',
                'data' => [
                    'template' => $templateWithRelations,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve template with relations', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve template',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function copyTemplateToFacility(string $templateUuid, int $facilityId): array
    {
        try {
            $sourceTemplate = $this->templateRepository->findByUuid($templateUuid);
            
            if (!$sourceTemplate) {
                return [
                    'success' => false,
                    'message' => 'Source template not found',
                    'error' => 'The requested template does not exist',
                    'data' => [],
                ];
            }
            
            // Create new template for the facility
            $newTemplateData = [
                'name' => $sourceTemplate->name . ' (Copy)',
                'description' => $sourceTemplate->description,
                'facility_id' => $facilityId,
                'is_shared' => false,
                'structure_type' => $sourceTemplate->structure_type,
                'is_active' => true,
                'metadata' => $sourceTemplate->metadata,
            ];
            
            $newTemplate = $this->templateRepository->create($newTemplateData);
            
            return [
                'success' => true,
                'message' => 'Template copied successfully',
                'data' => [
                    'template' => $newTemplate,
                    'source_template_id' => $sourceTemplate->id,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to copy template to facility', [
                'template_uuid' => $templateUuid,
                'facility_id' => $facilityId,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to copy template',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }
}