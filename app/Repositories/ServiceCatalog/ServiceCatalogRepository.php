<?php

namespace App\Repositories\ServiceCatalog;

use App\Models\ServiceCatalog;
use App\Repositories\Contracts\ServiceCatalogRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Repository implementation for ServiceCatalog database operations.
 * Handles all data persistence logic for ServiceCatalog entities.
 */
class ServiceCatalogRepository implements ServiceCatalogRepositoryInterface
{
    /**
     * Find a service catalog by its UUID.
     *
     * @param string $uuid
     * @return ServiceCatalog|null
     */
    public function findByUuid(string $uuid): ?ServiceCatalog
    {
        try {
            return ServiceCatalog::where('service_uuid', $uuid)->first();
        } catch (\Exception $e) {
            Log::error('Failed to find service catalog by UUID', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Find a service catalog by its service code.
     *
     * @param string $serviceCode
     * @return ServiceCatalog|null
     */
    public function findByServiceCode(string $serviceCode): ?ServiceCatalog
    {
        try {
            return ServiceCatalog::where('service_code', $serviceCode)->first();
        } catch (\Exception $e) {
            Log::error('Failed to find service catalog by service code', [
                'service_code' => $serviceCode,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get all service catalogs with pagination.
     *
     * @param int $perPage
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        try {
            $query = ServiceCatalog::query();

            // Apply filters
            $this->applyFilters($query, $filters);

            // Apply ordering
            $query->orderBy('service_name', 'asc');

            return $query->paginate($perPage);
        } catch (\Exception $e) {
            Log::error('Failed to paginate service catalogs', [
                'error' => $e->getMessage(),
                'filters' => $filters
            ]);
            
            // Return empty paginator instead of throwing exception
            return new LengthAwarePaginator([], 0, $perPage);
        }
    }

    /**
     * Get all service catalogs.
     *
     * @param array $filters
     * @return Collection
     */
    public function all(array $filters = []): Collection
    {
        try {
            $query = ServiceCatalog::query();

            // Apply filters
            $this->applyFilters($query, $filters);

            return $query->orderBy('service_name', 'asc')->get();
        } catch (\Exception $e) {
            Log::error('Failed to retrieve all service catalogs', [
                'error' => $e->getMessage(),
                'filters' => $filters
            ]);
            return new Collection();
        }
    }

    /**
     * Create a new service catalog.
     *
     * @param array $data
     * @return ServiceCatalog
     */
    public function create(array $data): ServiceCatalog
    {
        try {
            return DB::transaction(function () use ($data) {
                return ServiceCatalog::create($data);
            });
        } catch (\Exception $e) {
            Log::error('Failed to create service catalog', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw $e; // Re-throw for service layer to handle
        }
    }

    /**
     * Update an existing service catalog.
     *
     * @param ServiceCatalog $serviceCatalog
     * @param array $data
     * @return bool
     */
    public function update(ServiceCatalog $serviceCatalog, array $data): bool
    {
        try {
            return DB::transaction(function () use ($serviceCatalog, $data) {
                return $serviceCatalog->update($data);
            });
        } catch (\Exception $e) {
            Log::error('Failed to update service catalog', [
                'service_catalog_id' => $serviceCatalog->id,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw $e; // Re-throw for service layer to handle
        }
    }

    /**
     * Delete a service catalog.
     *
     * @param ServiceCatalog $serviceCatalog
     * @return bool|null
     */
    public function delete(ServiceCatalog $serviceCatalog): ?bool
    {
        try {
            return $serviceCatalog->delete();
        } catch (\Exception $e) {
            Log::error('Failed to delete service catalog', [
                'service_catalog_id' => $serviceCatalog->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Restore a soft-deleted service catalog.
     *
     * @param ServiceCatalog $serviceCatalog
     * @return bool
     */
    public function restore(ServiceCatalog $serviceCatalog): bool
    {
        try {
            return $serviceCatalog->restore();
        } catch (\Exception $e) {
            Log::error('Failed to restore service catalog', [
                'service_catalog_id' => $serviceCatalog->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Force delete a service catalog.
     *
     * @param ServiceCatalog $serviceCatalog
     * @return bool|null
     */
    public function forceDelete(ServiceCatalog $serviceCatalog): ?bool
    {
        try {
            return $serviceCatalog->forceDelete();
        } catch (\Exception $e) {
            Log::error('Failed to force delete service catalog', [
                'service_catalog_id' => $serviceCatalog->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get active service catalogs effective on a specific date.
     *
     * @param string $date
     * @param array $filters
     * @return Collection
     */
    public function getEffectiveServices(string $date, array $filters = []): Collection
    {
        try {
            $query = ServiceCatalog::active()
                ->effectiveOn($date);

            // Apply additional filters
            $this->applyFilters($query, $filters);

            return $query->orderBy('service_name', 'asc')->get();
        } catch (\Exception $e) {
            Log::error('Failed to get effective services', [
                'date' => $date,
                'error' => $e->getMessage(),
                'filters' => $filters
            ]);
            return new Collection();
        }
    }

    /**
     * Get service catalogs by code system.
     *
     * @param string $codeSystem
     * @param array $filters
     * @return Collection
     */
    public function getByCodeSystem(string $codeSystem, array $filters = []): Collection
    {
        try {
            $query = ServiceCatalog::byCodeSystem($codeSystem);

            // Apply filters
            $this->applyFilters($query, $filters);

            return $query->orderBy('service_code', 'asc')->get();
        } catch (\Exception $e) {
            Log::error('Failed to get service catalogs by code system', [
                'code_system' => $codeSystem,
                'error' => $e->getMessage(),
                'filters' => $filters
            ]);
            return new Collection();
        }
    }

    /**
     * Get service catalogs by category.
     *
     * @param string $category
     * @param array $filters
     * @return Collection
     */
    public function getByCategory(string $category, array $filters = []): Collection
    {
        try {
            $query = ServiceCatalog::byCategory($category);

            // Apply filters
            $this->applyFilters($query, $filters);

            return $query->orderBy('service_name', 'asc')->get();
        } catch (\Exception $e) {
            Log::error('Failed to get service catalogs by category', [
                'category' => $category,
                'error' => $e->getMessage(),
                'filters' => $filters
            ]);
            return new Collection();
        }
    }

    /**
     * Search service catalogs by name or code.
     *
     * @param string $searchTerm
     * @param array $filters
     * @return Collection
     */
    public function search(string $searchTerm, array $filters = []): Collection
    {
        try {
            $query = ServiceCatalog::where(function ($q) use ($searchTerm) {
                $q->where('service_name', 'like', "%{$searchTerm}%")
                  ->orWhere('service_code', 'like', "%{$searchTerm}%")
                  ->orWhere('service_description', 'like', "%{$searchTerm}%");
            });

            // Apply filters
            $this->applyFilters($query, $filters);

            return $query->orderBy('service_name', 'asc')->get();
        } catch (\Exception $e) {
            Log::error('Failed to search service catalogs', [
                'search_term' => $searchTerm,
                'error' => $e->getMessage(),
                'filters' => $filters
            ]);
            return new Collection();
        }
    }

    /**
     * Check if a service code already exists.
     *
     * @param string $serviceCode
     * @param string|null $excludeUuid
     * @return bool
     */
    public function serviceCodeExists(string $serviceCode, ?string $excludeUuid = null): bool
    {
        try {
            $query = ServiceCatalog::where('service_code', $serviceCode);

            if ($excludeUuid) {
                $query->where('service_uuid', '!=', $excludeUuid);
            }

            return $query->exists();
        } catch (\Exception $e) {
            Log::error('Failed to check if service code exists', [
                'service_code' => $serviceCode,
                'exclude_uuid' => $excludeUuid,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Apply filters to the query.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $filters
     * @return void
     */
    private function applyFilters($query, array $filters): void
    {
        if (isset($filters['status']) && $filters['status']) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['service_category']) && $filters['service_category']) {
            $query->where('service_category', $filters['service_category']);
        }

        if (isset($filters['code_system']) && $filters['code_system']) {
            $query->where('code_system', $filters['code_system']);
        }

        if (isset($filters['applicable_region']) && $filters['applicable_region']) {
            $query->where('applicable_region', $filters['applicable_region']);
        }

        if (isset($filters['risk_level']) && $filters['risk_level']) {
            $query->where('risk_level', $filters['risk_level']);
        }

        if (isset($filters['effective_date'])) {
            $query->effectiveOn($filters['effective_date']);
        }

        if (isset($filters['department_specialty']) && $filters['department_specialty']) {
            $query->where('department_specialty', 'like', "%{$filters['department_specialty']}%");
        }

        if (isset($filters['requires_consent'])) {
            $query->where('requires_informed_consent', (bool)$filters['requires_consent']);
        }

        if (isset($filters['min_duration']) && is_numeric($filters['min_duration'])) {
            $query->where('default_duration_minutes', '>=', (int)$filters['min_duration']);
        }

        if (isset($filters['max_duration']) && is_numeric($filters['max_duration'])) {
            $query->where('default_duration_minutes', '<=', (int)$filters['max_duration']);
        }
    }
}