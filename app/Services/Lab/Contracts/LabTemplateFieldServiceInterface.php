<?php

declare(strict_types=1);

namespace App\Services\Lab\Contracts;

use App\Models\LabTemplateField;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface LabTemplateFieldServiceInterface
{
    /**
     * Get all fields with pagination.
     *
     * @param array $filters
     * @param int $perPage
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getAllFields(array $filters = [], int $perPage = 20): array;

    /**
     * Get field by UUID.
     *
     * @param string $uuid
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getFieldByUuid(string $uuid): array;

    /**
     * Get field by ID.
     *
     * @param int $id
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getFieldById(int $id): array;

    /**
     * Create a new field.
     *
     * @param array $data
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function createField(array $data): array;

    /**
     * Update an existing field.
     *
     * @param string $uuid
     * @param array $data
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function updateField(string $uuid, array $data): array;

    /**
     * Delete a field.
     *
     * @param string $uuid
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function deleteField(string $uuid): array;

    /**
     * Activate a field.
     *
     * @param string $uuid
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function activateField(string $uuid): array;

    /**
     * Deactivate a field.
     *
     * @param string $uuid
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function deactivateField(string $uuid): array;

    /**
     * Get fields by template.
     *
     * @param string $templateUuid
     * @param array $filters
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getFieldsByTemplate(string $templateUuid, array $filters = []): array;

    /**
     * Get active fields by template.
     *
     * @param string $templateUuid
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getActiveFieldsByTemplate(string $templateUuid): array;

    /**
     * Get required fields by template.
     *
     * @param string $templateUuid
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getRequiredFieldsByTemplate(string $templateUuid): array;

    /**
     * Get critical fields by template.
     *
     * @param string $templateUuid
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getCriticalFieldsByTemplate(string $templateUuid): array;

    /**
     * Bulk create fields for template.
     *
     * @param string $templateUuid
     * @param array $fields
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function bulkCreateFields(string $templateUuid, array $fields): array;

    /**
     * Bulk update field display orders.
     *
     * @param array $orders (field_uuid => display_order)
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function bulkUpdateDisplayOrders(array $orders): array;

    /**
     * Duplicate fields from one template to another.
     *
     * @param string $sourceTemplateUuid
     * @param string $targetTemplateUuid
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function duplicateFields(string $sourceTemplateUuid, string $targetTemplateUuid): array;

    /**
     * Validate field value against reference range.
     *
     * @param string $fieldUuid
     * @param mixed $value
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function validateFieldValue(string $fieldUuid, $value): array;
}