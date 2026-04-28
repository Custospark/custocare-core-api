<?php

declare(strict_types=1);

namespace App\Services\Lab\Contracts;

use App\Models\LabTemplate;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface LabTemplateServiceInterface
{
    /**
     * Get all templates with pagination.
     *
     * @param array $filters
     * @param int $perPage
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getAllTemplates(array $filters = [], int $perPage = 20): array;

    /**
     * Get template by UUID.
     *
     * @param string $uuid
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getTemplateByUuid(string $uuid): array;

    /**
     * Get template by ID.
     *
     * @param int $id
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getTemplateById(int $id): array;

    /**
     * Create a new template.
     *
     * @param array $data
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function createTemplate(array $data): array;

    /**
     * Update an existing template.
     *
     * @param string $uuid
     * @param array $data
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function updateTemplate(string $uuid, array $data): array;

    /**
     * Delete a template.
     *
     * @param string $uuid
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function deleteTemplate(string $uuid): array;

    /**
     * Activate a template.
     *
     * @param string $uuid
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function activateTemplate(string $uuid): array;

    /**
     * Deactivate a template.
     *
     * @param string $uuid
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function deactivateTemplate(string $uuid): array;

    /**
     * Get active templates.
     *
     * @param int|null $facilityId
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getActiveTemplates(?int $facilityId = null): array;

    /**
     * Get shared templates.
     *
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getSharedTemplates(): array;

    /**
     * Get templates by facility.
     *
     * @param int $facilityId
     * @param array $filters
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getTemplatesByFacility(int $facilityId, array $filters = []): array;

    /**
     * Get templates by structure type.
     *
     * @param string $structureType
     * @param int|null $facilityId
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getTemplatesByStructureType(string $structureType, ?int $facilityId = null): array;

    /**
     * Get template with its tests and fields.
     *
     * @param string $uuid
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getTemplateWithRelations(string $uuid): array;

    /**
     * Copy template to facility.
     *
     * @param string $templateUuid
     * @param int $facilityId
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function copyTemplateToFacility(string $templateUuid, int $facilityId): array;
}