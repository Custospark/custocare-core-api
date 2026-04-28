<?php

declare(strict_types=1);

namespace App\Services\Lab\Contracts;

use App\Models\LabTest;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface LabTestServiceInterface
{
    /**
     * Get all tests with pagination.
     *
     * @param array $filters
     * @param int $perPage
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getAllTests(array $filters = [], int $perPage = 20): array;

    /**
     * Get test by UUID.
     *
     * @param string $uuid
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getTestByUuid(string $uuid): array;

    /**
     * Get test by ID.
     *
     * @param int $id
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getTestById(int $id): array;

    /**
     * Create a new test.
     *
     * @param array $data
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function createTest(array $data): array;

    /**
     * Update an existing test.
     *
     * @param string $uuid
     * @param array $data
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function updateTest(string $uuid, array $data): array;

    /**
     * Delete a test.
     *
     * @param string $uuid
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function deleteTest(string $uuid): array;

    /**
     * Activate a test.
     *
     * @param string $uuid
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function activateTest(string $uuid): array;

    /**
     * Deactivate a test.
     *
     * @param string $uuid
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function deactivateTest(string $uuid): array;

    /**
     * Get tests by template.
     *
     * @param string $templateUuid
     * @param array $filters
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getTestsByTemplate(string $templateUuid, array $filters = []): array;

    /**
     * Get tests by facility.
     *
     * @param int $facilityId
     * @param array $filters
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getTestsByFacility(int $facilityId, array $filters = []): array;

    /**
     * Get tests by category.
     *
     * @param string $category
     * @param int|null $facilityId
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getTestsByCategory(string $category, ?int $facilityId = null): array;

    /**
     * Get tests requiring fasting.
     *
     * @param int|null $facilityId
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getTestsRequiringFasting(?int $facilityId = null): array;

    /**
     * Get test statistics.
     *
     * @param string $uuid
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getTestStatistics(string $uuid): array;

    /**
     * Get popular tests.
     *
     * @param int $facilityId
     * @param int $limit
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getPopularTests(int $facilityId, int $limit = 10): array;

    /**
     * Get test with template details.
     *
     * @param string $uuid
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function getTestWithTemplate(string $uuid): array;

    /**
     * Bulk assign tests to template.
     *
     * @param string $templateUuid
     * @param array $testIds
     * @return array{success: bool, data: array, message: string, error?: string}
     */
    public function bulkAssignToTemplate(string $templateUuid, array $testIds): array;
}