<?php

declare(strict_types=1);

namespace App\Repositories\Lab\Contracts;

use App\Models\LabTemplate;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface LabTemplateRepositoryInterface
{
    /**
     * Find template by ID.
     *
     * @param int $id
     * @return LabTemplate|null
     */
    public function findById(int $id): ?LabTemplate;

    /**
     * Find template by UUID.
     *
     * @param string $uuid
     * @return LabTemplate|null
     */
    public function findByUuid(string $uuid): ?LabTemplate;

    /**
     * Get all templates with pagination.
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllPaginated(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Get all templates (without pagination).
     *
     * @param array $filters
     * @return Collection
     */
    public function getAll(array $filters = []): Collection;

    /**
     * Get templates by facility.
     *
     * @param int $facilityId
     * @param array $filters
     * @return Collection
     */
    public function getByFacility(int $facilityId, array $filters = []): Collection;

    /**
     * Get active templates.
     *
     * @param int|null $facilityId
     * @return Collection
     */
    public function getActiveTemplates(?int $facilityId = null): Collection;

    /**
     * Get shared templates.
     *
     * @return Collection
     */
    public function getSharedTemplates(): Collection;

    /**
     * Create a new template.
     *
     * @param array $data
     * @return LabTemplate
     */
    public function create(array $data): LabTemplate;

    /**
     * Update an existing template.
     *
     * @param LabTemplate $template
     * @param array $data
     * @return bool
     */
    public function update(LabTemplate $template, array $data): bool;

    /**
     * Delete a template (soft delete).
     *
     * @param LabTemplate $template
     * @return bool
     */
    public function delete(LabTemplate $template): bool;

    /**
     * Restore a soft-deleted template.
     *
     * @param int $id
     * @return bool
     */
    public function restore(int $id): bool;

    /**
     * Activate a template.
     *
     * @param LabTemplate $template
     * @return bool
     */
    public function activate(LabTemplate $template): bool;

    /**
     * Deactivate a template.
     *
     * @param LabTemplate $template
     * @return bool
     */
    public function deactivate(LabTemplate $template): bool;

    /**
     * Check if template exists by name.
     *
     * @param string $name
     * @param int|null $facilityId
     * @param int|null $excludeId
     * @return bool
     */
    public function existsByName(string $name, ?int $facilityId = null, ?int $excludeId = null): bool;

    /**
     * Get template with its tests and fields.
     *
     * @param int $id
     * @return LabTemplate|null
     */
    public function getWithRelations(int $id): ?LabTemplate;

    /**
     * Get templates by structure type.
     *
     * @param string $structureType
     * @param int|null $facilityId
     * @return Collection
     */
    public function getByStructureType(string $structureType, ?int $facilityId = null): Collection;


        public function findByNameAndFacility(string $name, ?int $facilityId = null): ?LabTemplate;

}