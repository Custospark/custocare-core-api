<?php

declare(strict_types=1);

namespace App\Services\Lab;

use App\Models\LabTemplateField;
use App\Repositories\Lab\Contracts\LabTemplateFieldRepositoryInterface;
use App\Repositories\Lab\Contracts\LabTemplateRepositoryInterface;
use App\Services\Lab\Contracts\LabTemplateFieldServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LabTemplateFieldService implements LabTemplateFieldServiceInterface
{
    /**
     * @var LabTemplateFieldRepositoryInterface
     */
    protected LabTemplateFieldRepositoryInterface $fieldRepository;

    /**
     * @var LabTemplateRepositoryInterface
     */
    protected LabTemplateRepositoryInterface $templateRepository;

    /**
     * Constructor.
     *
     * @param LabTemplateFieldRepositoryInterface $fieldRepository
     * @param LabTemplateRepositoryInterface $templateRepository
     */
    public function __construct(
        LabTemplateFieldRepositoryInterface $fieldRepository,
        LabTemplateRepositoryInterface $templateRepository
    ) {
        $this->fieldRepository = $fieldRepository;
        $this->templateRepository = $templateRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function getAllFields(array $filters = [], int $perPage = 20): array
    {
        try {
            $fields = $this->fieldRepository->getAllPaginated($filters, $perPage);
            
            return [
                'success' => true,
                'message' => 'Fields retrieved successfully',
                'data' => [
                    'fields' => $fields,
                    'filters' => $filters,
                    'per_page' => $perPage,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve fields', [
                'filters' => $filters,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve fields',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getFieldByUuid(string $uuid): array
    {
        try {
            $field = $this->fieldRepository->findByUuid($uuid);
            
            if (!$field) {
                return [
                    'success' => false,
                    'message' => 'Field not found',
                    'error' => 'The requested field does not exist',
                    'data' => [],
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Field retrieved successfully',
                'data' => [
                    'field' => $field,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve field', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve field',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getFieldById(int $id): array
    {
        try {
            $field = $this->fieldRepository->findById($id);
            
            if (!$field) {
                return [
                    'success' => false,
                    'message' => 'Field not found',
                    'error' => 'The requested field does not exist',
                    'data' => [],
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Field retrieved successfully',
                'data' => [
                    'field' => $field,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve field', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve field',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createField(array $data): array
    {
        try {
            // Validate template exists
            $template = $this->templateRepository->findById($data['template_id']);
            if (!$template) {
                return [
                    'success' => false,
                    'message' => 'Template not found',
                    'error' => 'The specified template does not exist',
                    'data' => [],
                ];
            }
            
            // Validate field name uniqueness within template
            if ($this->fieldRepository->existsByNameInTemplate($data['template_id'], $data['name'])) {
                return [
                    'success' => false,
                    'message' => 'Field name already exists in this template',
                    'error' => 'A field with this name already exists in the template',
                    'data' => [],
                ];
            }
            
            // Validate reference ranges for numeric fields
            if ($data['data_type'] === 'number') {
                if (isset($data['reference_min']) && isset($data['reference_max']) && 
                    $data['reference_min'] > $data['reference_max']) {
                    return [
                        'success' => false,
                        'message' => 'Invalid reference range',
                        'error' => 'Minimum reference value cannot be greater than maximum',
                        'data' => [],
                    ];
                }
            }
            
            $field = $this->fieldRepository->create($data);
            
            return [
                'success' => true,
                'message' => 'Field created successfully',
                'data' => [
                    'field' => $field,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to create field', [
                'data' => $data,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to create field',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function updateField(string $uuid, array $data): array
    {
        try {
            $field = $this->fieldRepository->findByUuid($uuid);
            
            if (!$field) {
                return [
                    'success' => false,
                    'message' => 'Field not found',
                    'error' => 'The requested field does not exist',
                    'data' => [],
                ];
            }
            
            // Validate field name uniqueness within template if name is being changed
            if (isset($data['name']) && $data['name'] !== $field->name) {
                if ($this->fieldRepository->existsByNameInTemplate($field->template_id, $data['name'], $field->id)) {
                    return [
                        'success' => false,
                        'message' => 'Field name already exists in this template',
                        'error' => 'A field with this name already exists in the template',
                        'data' => [],
                    ];
                }
            }
            
            // Validate reference ranges for numeric fields
            $dataType = $data['data_type'] ?? $field->data_type;
            if ($dataType === 'number') {
                $referenceMin = $data['reference_min'] ?? $field->reference_min;
                $referenceMax = $data['reference_max'] ?? $field->reference_max;
                
                if ($referenceMin !== null && $referenceMax !== null && $referenceMin > $referenceMax) {
                    return [
                        'success' => false,
                        'message' => 'Invalid reference range',
                        'error' => 'Minimum reference value cannot be greater than maximum',
                        'data' => [],
                    ];
                }
            }
            
            $updated = $this->fieldRepository->update($field, $data);
            
            if (!$updated) {
                return [
                    'success' => false,
                    'message' => 'Failed to update field',
                    'error' => 'Unable to update field',
                    'data' => [],
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Field updated successfully',
                'data' => [
                    'field' => $field->fresh(),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to update field', [
                'uuid' => $uuid,
                'data' => $data,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to update field',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteField(string $uuid): array
    {
        try {
            $field = $this->fieldRepository->findByUuid($uuid);
            
            if (!$field) {
                return [
                    'success' => false,
                    'message' => 'Field not found',
                    'error' => 'The requested field does not exist',
                    'data' => [],
                ];
            }
            
            // Check if field has associated results
            if ($field->results()->count() > 0) {
                return [
                    'success' => false,
                    'message' => 'Cannot delete field with associated results',
                    'error' => 'This field has been used in lab results and cannot be deleted',
                    'data' => [],
                ];
            }
            
            $deleted = $this->fieldRepository->delete($field);
            
            if (!$deleted) {
                return [
                    'success' => false,
                    'message' => 'Failed to delete field',
                    'error' => 'Unable to delete field',
                    'data' => [],
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Field deleted successfully',
                'data' => [],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to delete field', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to delete field',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function activateField(string $uuid): array
    {
        try {
            $field = $this->fieldRepository->findByUuid($uuid);
            
            if (!$field) {
                return [
                    'success' => false,
                    'message' => 'Field not found',
                    'error' => 'The requested field does not exist',
                    'data' => [],
                ];
            }
            
            $activated = $this->fieldRepository->activate($field);
            
            if (!$activated) {
                return [
                    'success' => false,
                    'message' => 'Failed to activate field',
                    'error' => 'Unable to activate field',
                    'data' => [],
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Field activated successfully',
                'data' => [
                    'field' => $field->fresh(),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to activate field', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to activate field',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deactivateField(string $uuid): array
    {
        try {
            $field = $this->fieldRepository->findByUuid($uuid);
            
            if (!$field) {
                return [
                    'success' => false,
                    'message' => 'Field not found',
                    'error' => 'The requested field does not exist',
                    'data' => [],
                ];
            }
            
            $deactivated = $this->fieldRepository->deactivate($field);
            
            if (!$deactivated) {
                return [
                    'success' => false,
                    'message' => 'Failed to deactivate field',
                    'error' => 'Unable to deactivate field',
                    'data' => [],
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Field deactivated successfully',
                'data' => [
                    'field' => $field->fresh(),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to deactivate field', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to deactivate field',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getFieldsByTemplate(string $templateUuid, array $filters = []): array
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
            
            $fields = $this->fieldRepository->getByTemplate($template->id, $filters);
            
            return [
                'success' => true,
                'message' => 'Fields retrieved successfully',
                'data' => [
                    'fields' => $fields,
                    'template' => $template,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve fields by template', [
                'template_uuid' => $templateUuid,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve fields',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getActiveFieldsByTemplate(string $templateUuid): array
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
            
            $fields = $this->fieldRepository->getActiveFieldsByTemplate($template->id);
            
            return [
                'success' => true,
                'message' => 'Active fields retrieved successfully',
                'data' => [
                    'fields' => $fields,
                    'template' => $template,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve active fields by template', [
                'template_uuid' => $templateUuid,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve active fields',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getRequiredFieldsByTemplate(string $templateUuid): array
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
            
            $fields = $this->fieldRepository->getRequiredFieldsByTemplate($template->id);
            
            return [
                'success' => true,
                'message' => 'Required fields retrieved successfully',
                'data' => [
                    'fields' => $fields,
                    'template' => $template,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve required fields by template', [
                'template_uuid' => $templateUuid,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve required fields',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getCriticalFieldsByTemplate(string $templateUuid): array
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
            
            $fields = $this->fieldRepository->getCriticalFieldsByTemplate($template->id);
            
            return [
                'success' => true,
                'message' => 'Critical fields retrieved successfully',
                'data' => [
                    'fields' => $fields,
                    'template' => $template,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve critical fields by template', [
                'template_uuid' => $templateUuid,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve critical fields',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function bulkCreateFields(string $templateUuid, array $fields): array
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
            
            // Validate field names uniqueness
            $existingNames = $this->fieldRepository->getByTemplate($template->id)
                ->pluck('name')
                ->toArray();
            
            $duplicates = [];
            foreach ($fields as $field) {
                if (in_array($field['name'], $existingNames) || in_array($field['name'], $duplicates)) {
                    return [
                        'success' => false,
                        'message' => 'Duplicate field name found',
                        'error' => "Field name '{$field['name']}' already exists in this template",
                        'data' => [],
                    ];
                }
                $duplicates[] = $field['name'];
            }
            
            // Validate reference ranges for numeric fields
            foreach ($fields as $field) {
                if (($field['data_type'] ?? 'text') === 'number') {
                    if (isset($field['reference_min']) && isset($field['reference_max']) && 
                        $field['reference_min'] > $field['reference_max']) {
                        return [
                            'success' => false,
                            'message' => 'Invalid reference range',
                            'error' => "Field '{$field['name']}' has invalid reference range",
                            'data' => [],
                        ];
                    }
                }
            }
            
            $createdFields = $this->fieldRepository->bulkCreate($template->id, $fields);
            
            return [
                'success' => true,
                'message' => count($createdFields) . ' fields created successfully',
                'data' => [
                    'fields' => $createdFields,
                    'template' => $template,
                    'total_created' => count($createdFields),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to bulk create fields', [
                'template_uuid' => $templateUuid,
                'fields_count' => count($fields),
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to create fields',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function bulkUpdateDisplayOrders(array $orders): array
    {
        try {
            if (empty($orders)) {
                return [
                    'success' => false,
                    'message' => 'No orders provided',
                    'error' => 'Please provide field display orders to update',
                    'data' => [],
                ];
            }
            
            $updated = $this->fieldRepository->bulkUpdateDisplayOrders($orders);
            
            if (!$updated) {
                return [
                    'success' => false,
                    'message' => 'Failed to update display orders',
                    'error' => 'Unable to update display orders',
                    'data' => [],
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Display orders updated successfully',
                'data' => [
                    'updated_count' => count($orders),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to bulk update display orders', [
                'orders_count' => count($orders),
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to update display orders',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function duplicateFields(string $sourceTemplateUuid, string $targetTemplateUuid): array
    {
        try {
            $sourceTemplate = $this->templateRepository->findByUuid($sourceTemplateUuid);
            if (!$sourceTemplate) {
                return [
                    'success' => false,
                    'message' => 'Source template not found',
                    'error' => 'The requested source template does not exist',
                    'data' => [],
                ];
            }
            
            $targetTemplate = $this->templateRepository->findByUuid($targetTemplateUuid);
            if (!$targetTemplate) {
                return [
                    'success' => false,
                    'message' => 'Target template not found',
                    'error' => 'The requested target template does not exist',
                    'data' => [],
                ];
            }
            
            $duplicatedFields = $this->fieldRepository->duplicateFromTemplate($sourceTemplate->id, $targetTemplate->id);
            
            return [
                'success' => true,
                'message' => count($duplicatedFields) . ' fields duplicated successfully',
                'data' => [
                    'fields' => $duplicatedFields,
                    'source_template' => $sourceTemplate,
                    'target_template' => $targetTemplate,
                    'total_duplicated' => count($duplicatedFields),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to duplicate fields', [
                'source_template_uuid' => $sourceTemplateUuid,
                'target_template_uuid' => $targetTemplateUuid,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to duplicate fields',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function validateFieldValue(string $fieldUuid, $value): array
    {
        try {
            $field = $this->fieldRepository->findByUuid($fieldUuid);
            
            if (!$field) {
                return [
                    'success' => false,
                    'message' => 'Field not found',
                    'error' => 'The requested field does not exist',
                    'data' => [],
                ];
            }
            
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
                        // For select fields, you might want to validate against allowed options
                        // This would require storing allowed options in metadata
                        break;
                }
            }
            
            return [
                'success' => true,
                'message' => $isValid ? 'Value is valid' : 'Validation failed',
                'data' => [
                    'is_valid' => $isValid,
                    'errors' => $errors,
                    'flag' => $flag,
                    'field' => $field,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to validate field value', [
                'field_uuid' => $fieldUuid,
                'value' => $value,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to validate field value',
                'error' => 'An internal server error occurred',
                'data' => [],
            ];
        }
    }
}