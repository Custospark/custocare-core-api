<?php

namespace App\Repositories\Contracts;

use App\Models\DataResidencyRule;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface DataResidencyRuleRepositoryInterface
{
    /**
     * Get all data residency rules
     *
     * @param array $filters
     * @param array $sort
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAll(array $filters = [], array $sort = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Find data residency rule by ID
     *
     * @param int $id
     * @return DataResidencyRule|null
     */
    public function findById(int $id): ?DataResidencyRule;

    /**
     * Find data residency rule by region code and data category
     *
     * @param string $regionCode
     * @param string $dataCategory
     * @return DataResidencyRule|null
     */
    public function findByRegionAndCategory(string $regionCode, string $dataCategory): ?DataResidencyRule;

    /**
     * Find active rules by region code
     *
     * @param string $regionCode
     * @return Collection
     */
    public function findActiveByRegion(string $regionCode): Collection;

    /**
     * Find rules by data category
     *
     * @param string $dataCategory
     * @param bool $activeOnly
     * @return Collection
     */
    public function findByDataCategory(string $dataCategory, bool $activeOnly = true): Collection;

    /**
     * Create a new data residency rule
     *
     * @param array $data
     * @return DataResidencyRule
     */
    public function create(array $data): DataResidencyRule;

    /**
     * Update an existing data residency rule
     *
     * @param DataResidencyRule $rule
     * @param array $data
     * @return DataResidencyRule
     */
    public function update(DataResidencyRule $rule, array $data): DataResidencyRule;

    /**
     * Delete a data residency rule
     *
     * @param DataResidencyRule $rule
     * @return bool
     */
    public function delete(DataResidencyRule $rule): bool;

    /**
     * Check if region code is unique
     *
     * @param string $regionCode
     * @param int|null $excludeId
     * @return bool
     */
    public function isRegionCodeUnique(string $regionCode, ?int $excludeId = null): bool;

    /**
     * Check if rule with region and category exists
     *
     * @param string $regionCode
     * @param string $dataCategory
     * @param int|null $excludeId
     * @return bool
     */
    public function existsByRegionAndCategory(string $regionCode, string $dataCategory, ?int $excludeId = null): bool;

    /**
     * Get all active rules
     *
     * @return Collection
     */
    public function getAllActive(): Collection;
}