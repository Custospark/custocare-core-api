<?php

namespace App\Services\Contracts;

use App\Models\DataResidencyRule;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface DataResidencyRuleServiceInterface
{
    /**
     * Get all data residency rules with pagination
     *
     * @param array $filters
     * @param array $sort
     * @param int $perPage
     * @return array
     */
    public function getAllRules(array $filters = [], array $sort = [], int $perPage = 20): array;

    /**
     * Get rule by ID
     *
     * @param int $id
     * @return array
     */
    public function getRuleById(int $id): array;

    /**
     * Get rule by region code and data category
     *
     * @param string $regionCode
     * @param string $dataCategory
     * @return array
     */
    public function getRuleByRegionAndCategory(string $regionCode, string $dataCategory): array;

    /**
     * Create a new data residency rule
     *
     * @param array $data
     * @return array
     */
    public function createRule(array $data): array;

    /**
     * Update an existing data residency rule
     *
     * @param int $id
     * @param array $data
     * @return array
     */
    public function updateRule(int $id, array $data): array;

    /**
     * Delete a data residency rule
     *
     * @param int $id
     * @return array
     */
    public function deleteRule(int $id): array;

    /**
     * Validate if data can be processed in a specific region
     *
     * @param string $dataCategory
     * @param string $processingRegion
     * @param string $storageRegion
     * @return array
     */
    public function validateDataProcessing(string $dataCategory, string $processingRegion, string $storageRegion): array;

    /**
     * Get applicable rules for a data category and region
     *
     * @param string $dataCategory
     * @param string $regionCode
     * @return array
     */
    public function getApplicableRules(string $dataCategory, string $regionCode): array;

    /**
     * Check if cross-border transfer is allowed
     *
     * @param string $sourceRegion
     * @param string $targetRegion
     * @param string $dataCategory
     * @return array
     */
    public function validateCrossBorderTransfer(string $sourceRegion, string $targetRegion, string $dataCategory): array;

    /**
     * Get active rules summary by region
     *
     * @return array
     */
    public function getRulesSummary(): array;
}