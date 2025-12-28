<?php

namespace App\Repositories\DataResidencyRule;

use App\Models\DataResidencyRule;
use App\Repositories\Contracts\DataResidencyRuleRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DataResidencyRuleRepository implements DataResidencyRuleRepositoryInterface
{
    /**
     * Get all data residency rules with filtering and sorting
     *
     * @param array $filters
     * @param array $sort
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAll(array $filters = [], array $sort = [], int $perPage = 20): LengthAwarePaginator
    {
        try {
            $query = DataResidencyRule::query();

            // Apply filters
            if (!empty($filters['region_code'])) {
                $query->where('region_code', 'LIKE', "%{$filters['region_code']}%");
            }

            if (!empty($filters['region_name'])) {
                $query->where('region_name', 'LIKE', "%{$filters['region_name']}%");
            }

            if (!empty($filters['data_category'])) {
                $query->where('data_category', $filters['data_category']);
            }

            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (!empty($filters['effective_from'])) {
                $query->where('effective_from', '>=', $filters['effective_from']);
            }

            if (!empty($filters['effective_to'])) {
                $query->where('effective_to', '<=', $filters['effective_to']);
            }

            if (!empty($filters['active_only'])) {
                $query->active();
            }

            // Apply sorting
            if (!empty($sort['field'])) {
                $direction = $sort['direction'] ?? 'asc';
                $query->orderBy($sort['field'], $direction);
            } else {
                $query->orderBy('region_code')->orderBy('data_category');
            }

            return $query->paginate($perPage);
        } catch (\Exception $e) {
            Log::error('Failed to fetch data residency rules', [
                'error' => $e->getMessage(),
                'filters' => $filters,
                'trace' => $e->getTraceAsString()
            ]);
            
            // Return empty paginator instead of throwing
            return new LengthAwarePaginator([], 0, $perPage);
        }
    }

    /**
     * Find data residency rule by ID
     *
     * @param int $id
     * @return DataResidencyRule|null
     */
    public function findById(int $id): ?DataResidencyRule
    {
        try {
            return DataResidencyRule::find($id);
        } catch (\Exception $e) {
            Log::error('Failed to find data residency rule by ID', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Find data residency rule by region code and data category
     *
     * @param string $regionCode
     * @param string $dataCategory
     * @return DataResidencyRule|null
     */
    public function findByRegionAndCategory(string $regionCode, string $dataCategory): ?DataResidencyRule
    {
        try {
            return DataResidencyRule::where('region_code', $regionCode)
                ->where('data_category', $dataCategory)
                ->first();
        } catch (\Exception $e) {
            Log::error('Failed to find rule by region and category', [
                'region_code' => $regionCode,
                'data_category' => $dataCategory,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Find active rules by region code
     *
     * @param string $regionCode
     * @return Collection
     */
    public function findActiveByRegion(string $regionCode): Collection
    {
        try {
            return DataResidencyRule::where('region_code', $regionCode)
                ->active()
                ->get();
        } catch (\Exception $e) {
            Log::error('Failed to find active rules by region', [
                'region_code' => $regionCode,
                'error' => $e->getMessage()
            ]);
            return collect();
        }
    }

    /**
     * Find rules by data category
     *
     * @param string $dataCategory
     * @param bool $activeOnly
     * @return Collection
     */
    public function findByDataCategory(string $dataCategory, bool $activeOnly = true): Collection
    {
        try {
            $query = DataResidencyRule::where('data_category', $dataCategory);

            if ($activeOnly) {
                $query->active();
            }

            return $query->get();
        } catch (\Exception $e) {
            Log::error('Failed to find rules by data category', [
                'data_category' => $dataCategory,
                'error' => $e->getMessage()
            ]);
            return collect();
        }
    }

    /**
     * Create a new data residency rule
     *
     * @param array $data
     * @return DataResidencyRule
     */
    public function create(array $data): DataResidencyRule
    {
        return DB::transaction(function () use ($data) {
            try {
                return DataResidencyRule::create($data);
            } catch (\Exception $e) {
                Log::error('Failed to create data residency rule', [
                    'data' => $data,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                throw $e; // Re-throw for service layer to handle
            }
        });
    }

    /**
     * Update an existing data residency rule
     *
     * @param DataResidencyRule $rule
     * @param array $data
     * @return DataResidencyRule
     */
    public function update(DataResidencyRule $rule, array $data): DataResidencyRule
    {
        return DB::transaction(function () use ($rule, $data) {
            try {
                $rule->update($data);
                return $rule->fresh();
            } catch (\Exception $e) {
                Log::error('Failed to update data residency rule', [
                    'rule_id' => $rule->id,
                    'data' => $data,
                    'error' => $e->getMessage()
                ]);
                
                throw $e; // Re-throw for service layer to handle
            }
        });
    }

    /**
     * Delete a data residency rule
     *
     * @param DataResidencyRule $rule
     * @return bool
     */
    public function delete(DataResidencyRule $rule): bool
    {
        try {
            return $rule->delete();
        } catch (\Exception $e) {
            Log::error('Failed to delete data residency rule', [
                'rule_id' => $rule->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Check if region code is unique
     *
     * @param string $regionCode
     * @param int|null $excludeId
     * @return bool
     */
    public function isRegionCodeUnique(string $regionCode, ?int $excludeId = null): bool
    {
        try {
            $query = DataResidencyRule::where('region_code', $regionCode);
            
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
            
            return !$query->exists();
        } catch (\Exception $e) {
            Log::error('Failed to check region code uniqueness', [
                'region_code' => $regionCode,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Check if rule with region and category exists
     *
     * @param string $regionCode
     * @param string $dataCategory
     * @param int|null $excludeId
     * @return bool
     */
    public function existsByRegionAndCategory(string $regionCode, string $dataCategory, ?int $excludeId = null): bool
    {
        try {
            $query = DataResidencyRule::where('region_code', $regionCode)
                ->where('data_category', $dataCategory);
            
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
            
            return $query->exists();
        } catch (\Exception $e) {
            Log::error('Failed to check rule existence', [
                'region_code' => $regionCode,
                'data_category' => $dataCategory,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get all active rules
     *
     * @return Collection
     */
    public function getAllActive(): Collection
    {
        try {
            return DataResidencyRule::active()->get();
        } catch (\Exception $e) {
            Log::error('Failed to get all active rules', [
                'error' => $e->getMessage()
            ]);
            return collect();
        }
    }
}