<?php

declare(strict_types=1);

namespace App\Repositories\Lab\Contracts;

use App\Models\LabTest;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface LabTestRepositoryInterface
{
    /**
     * Find test by ID.
     *
     * @param int $id
     * @return LabTest|null
     */
    public function findById(int $id): ?LabTest;

    /**
     * Find test by UUID.
     *
     * @param string $uuid
     * @return LabTest|null
     */
    public function findByUuid(string $uuid): ?LabTest;

    /**
     * Find test by code.
     *
     * @param string $code
     * @param int|null $facilityId
     * @return LabTest|null
     */
    public function findByCode(string $code, ?int $facilityId = null): ?LabTest;

    /**
     * Get all tests with pagination.
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllPaginated(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Get all tests (without pagination).
     *
     * @param array $filters
     * @return Collection
     */
    public function getAll(array $filters = []): Collection;

    /**
     * Get tests by template.
     *
     * @param int $templateId
     * @param array $filters
     * @return Collection
     */
    public function getByTemplate(int $templateId, array $filters = []): Collection;

    /**
     * Get tests by facility.
     *
     * @param int $facilityId
     * @param array $filters
     * @return Collection
     */
    public function getByFacility(int $facilityId, array $filters = []): Collection;

    /**
     * Get active tests.
     *
     * @param int|null $facilityId
     * @return Collection
     */
    public function getActiveTests(?int $facilityId = null): Collection;

    /**
     * Get tests by category.
     *
     * @param string $category
     * @param int|null $facilityId
     * @return Collection
     */
    public function getByCategory(string $category, ?int $facilityId = null): Collection;

    /**
     * Get tests that require fasting.
     *
     * @param int|null $facilityId
     * @return Collection
     */
    public function getTestsRequiringFasting(?int $facilityId = null): Collection;

    /**
     * Create a new test.
     *
     * @param array $data
     * @return LabTest
     */
    public function create(array $data): LabTest;

    /**
     * Update an existing test.
     *
     * @param LabTest $test
     * @param array $data
     * @return bool
     */
    public function update(LabTest $test, array $data): bool;

    /**
     * Delete a test (soft delete).
     *
     * @param LabTest $test
     * @return bool
     */
    public function delete(LabTest $test): bool;

    /**
     * Restore a soft-deleted test.
     *
     * @param int $id
     * @return bool
     */
    public function restore(int $id): bool;

    /**
     * Activate a test.
     *
     * @param LabTest $test
     * @return bool
     */
    public function activate(LabTest $test): bool;

    /**
     * Deactivate a test.
     *
     * @param LabTest $test
     * @return bool
     */
    public function deactivate(LabTest $test): bool;

    /**
     * Check if test exists by name.
     *
     * @param string $name
     * @param int|null $facilityId
     * @param int|null $excludeId
     * @return bool
     */
    public function existsByName(string $name, ?int $facilityId = null, ?int $excludeId = null): bool;

    /**
     * Get test with its template.
     *
     * @param int $id
     * @return LabTest|null
     */
    public function getWithTemplate(int $id): ?LabTest;

    /**
     * Get test statistics.
     *
     * @param int $testId
     * @return array
     */
    public function getTestStatistics(int $testId): array;

    /**
     * Get popular tests.
     *
     * @param int $facilityId
     * @param int $limit
     * @return Collection
     */
    public function getPopularTests(int $facilityId, int $limit = 10): Collection;
}