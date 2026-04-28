<?php

declare(strict_types=1);

namespace App\Repositories\Lab\Contracts;

use App\Models\LabTemplateField;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface LabTemplateFieldRepositoryInterface
{
    /**
     * Find field by ID.
     *
     * @param int $id
     * @return LabTemplateField|null
     */
    public function findById(int $id): ?LabTemplateField;

    /**
     * Find field by UUID.
     *
     * @param string $uuid
     * @return LabTemplateField|null
     */
    public function findByUuid(string $uuid): ?LabTemplateField;

    /**
     * Find field by code.
     *
     * @param string $code
     * @param int $templateId
     * @return LabTemplateField|null
     */
    public function findByCode(string $code, int $templateId): ?LabTemplateField;

    /**
     * Get all fields with pagination.
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllPaginated(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Get fields by template.
     *
     * @param int $templateId
     * @param array $filters
     * @return Collection
     */
    public function getByTemplate(int $templateId, array $filters = []): Collection;

    /**
     * Get active fields by template.
     *
     * @param int $templateId
     * @return Collection
     */
    public function getActiveFieldsByTemplate(int $templateId): Collection;

    /**
     * Get required fields by template.
     *
     * @param int $templateId
     * @return Collection
     */
    public function getRequiredFieldsByTemplate(int $templateId): Collection;

    /**
     * Get critical fields by template.
     *
     * @param int $templateId
     * @return Collection
     */
    public function getCriticalFieldsByTemplate(int $templateId): Collection;

    /**
     * Create a new field.
     *
     * @param array $data
     * @return LabTemplateField
     */
    public function create(array $data): LabTemplateField;

    /**
     * Update an existing field.
     *
     * @param LabTemplateField $field
     * @param array $data
     * @return bool
     */
    public function update(LabTemplateField $field, array $data): bool;

    /**
     * Delete a field (soft delete).
     *
     * @param LabTemplateField $field
     * @return bool
     */
    public function delete(LabTemplateField $field): bool;

    /**
     * Restore a soft-deleted field.
     *
     * @param int $id
     * @return bool
     */
    public function restore(int $id): bool;

    /**
     * Activate a field.
     *
     * @param LabTemplateField $field
     * @return bool
     */
    public function activate(LabTemplateField $field): bool;

    /**
     * Deactivate a field.
     *
     * @param LabTemplateField $field
     * @return bool
     */
    public function deactivate(LabTemplateField $field): bool;

    /**
     * Bulk create fields for a template.
     *
     * @param int $templateId
     * @param array $fields
     * @return Collection
     */
    public function bulkCreate(int $templateId, array $fields): Collection;

    /**
     * Bulk update display orders.
     *
     * @param array $orders (field_id => display_order)
     * @return bool
     */
    public function bulkUpdateDisplayOrders(array $orders): bool;

    /**
     * Check if field exists by name in template.
     *
     * @param int $templateId
     * @param string $name
     * @param int|null $excludeId
     * @return bool
     */
    public function existsByNameInTemplate(int $templateId, string $name, ?int $excludeId = null): bool;

    /**
     * Get field with its template.
     *
     * @param int $id
     * @return LabTemplateField|null
     */
    public function getWithTemplate(int $id): ?LabTemplateField;

    /**
     * Duplicate fields from one template to another.
     *
     * @param int $sourceTemplateId
     * @param int $targetTemplateId
     * @return Collection
     */
    public function duplicateFromTemplate(int $sourceTemplateId, int $targetTemplateId): Collection;
}