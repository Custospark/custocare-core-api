<?php

namespace App\Repositories\ServiceVersion;

use App\Models\ServiceVersion;
use App\Repositories\Contracts\ServiceVersionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ServiceVersion Repository Implementation
 * 
 * Handles all database operations for ServiceVersion entities.
 * Implements the repository interface for data persistence.
 */
class ServiceVersionRepository implements ServiceVersionRepositoryInterface
{
    /**
     * Model instance.
     *
     * @var ServiceVersion
     */
    protected $model;

    /**
     * Constructor.
     *
     * @param ServiceVersion $model
     */
    public function __construct(ServiceVersion $model)
    {
        $this->model = $model;
    }

    /**
     * Find a service version by ID.
     *
     * @param int $id
     * @return ServiceVersion|null
     */
    public function findById(int $id): ?ServiceVersion
    {
        try {
            return $this->model->with(['serviceCatalog', 'facility', 'createdBy'])->find($id);
        } catch (\Exception $e) {
            Log::error('Error finding service version by ID', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Find a service version by UUID.
     *
     * @param string $uuid
     * @return ServiceVersion|null
     */
    public function findByUuid(string $uuid): ?ServiceVersion
    {
        try {
            return $this->model->with(['serviceCatalog', 'facility', 'createdBy'])
                ->where('version_uuid', $uuid)
                ->first();
        } catch (\Exception $e) {
            Log::error('Error finding service version by UUID', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get all service versions.
     *
     * @param array $filters
     * @return Collection
     */
    public function getAll(array $filters = []): Collection
    {
        try {
            $query = $this->model->with(['serviceCatalog', 'facility', 'createdBy']);
            
            // Apply filters
            if (!empty($filters['service_catalog_id'])) {
                $query->where('service_catalog_id', $filters['service_catalog_id']);
            }
            
            if (!empty($filters['facility_id'])) {
                $query->where('facility_id', $filters['facility_id']);
            }
            
            if (isset($filters['is_current'])) {
                $query->where('is_current', (bool) $filters['is_current']);
            }
            
            if (!empty($filters['valid_on'])) {
                $query->where('valid_from', '<=', $filters['valid_on'])
                      ->where(function ($q) use ($filters) {
                          $q->where('valid_to', '>=', $filters['valid_on'])
                            ->orWhereNull('valid_to');
                      });
            }
            
            if (!empty($filters['is_billable'])) {
                $query->where('is_billable', (bool) $filters['is_billable']);
            }
            
            if (!empty($filters['currency_code'])) {
                $query->where('currency_code', $filters['currency_code']);
            }
            
            return $query->orderBy('valid_from', 'desc')->get();
        } catch (\Exception $e) {
            Log::error('Error getting all service versions', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return new Collection();
        }
    }

    /**
     * Get paginated service versions.
     *
     * @param int $perPage
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getPaginated(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        try {
            $query = $this->model->with(['serviceCatalog', 'facility', 'createdBy']);
            
            // Apply filters
            if (!empty($filters['service_catalog_id'])) {
                $query->where('service_catalog_id', $filters['service_catalog_id']);
            }
            
            if (!empty($filters['facility_id'])) {
                $query->where('facility_id', $filters['facility_id']);
            }
            
            if (isset($filters['is_current'])) {
                $query->where('is_current', (bool) $filters['is_current']);
            }
            
            if (!empty($filters['valid_from_start'])) {
                $query->where('valid_from', '>=', $filters['valid_from_start']);
            }
            
            if (!empty($filters['valid_from_end'])) {
                $query->where('valid_from', '<=', $filters['valid_from_end']);
            }
            
            if (!empty($filters['search'])) {
                $search = $filters['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('version_number', 'like', "%{$search}%")
                      ->orWhere('change_notes', 'like', "%{$search}%");
                });
            }
            
            return $query->orderBy('valid_from', 'desc')->paginate($perPage);
        } catch (\Exception $e) {
            Log::error('Error getting paginated service versions', [
                'perPage' => $perPage,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            
            // Return empty paginator on error
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }
    }

    /**
     * Get current versions by service catalog.
     *
     * @param int $serviceCatalogId
     * @param int|null $facilityId
     * @return Collection
     */
    public function getCurrentVersions(int $serviceCatalogId, ?int $facilityId = null): Collection
    {
        try {
            $query = $this->model->with(['serviceCatalog', 'facility'])
                ->where('service_catalog_id', $serviceCatalogId)
                ->where('is_current', true);
            
            if ($facilityId !== null) {
                $query->where('facility_id', $facilityId);
            } else {
                $query->whereNull('facility_id');
            }
            
            return $query->get();
        } catch (\Exception $e) {
            Log::error('Error getting current service versions', [
                'service_catalog_id' => $serviceCatalogId,
                'facility_id' => $facilityId,
                'error' => $e->getMessage()
            ]);
            return new Collection();
        }
    }

    /**
     * Get versions valid on a specific date.
     *
     * @param string $date Date in Y-m-d format
     * @param int|null $serviceCatalogId
     * @param int|null $facilityId
     * @return Collection
     */
    public function getValidOnDate(string $date, ?int $serviceCatalogId = null, ?int $facilityId = null): Collection
    {
        try {
            $query = $this->model->with(['serviceCatalog', 'facility'])
                ->where('valid_from', '<=', $date)
                ->where(function ($q) use ($date) {
                    $q->where('valid_to', '>=', $date)
                      ->orWhereNull('valid_to');
                });
            
            if ($serviceCatalogId !== null) {
                $query->where('service_catalog_id', $serviceCatalogId);
            }
            
            if ($facilityId !== null) {
                $query->where('facility_id', $facilityId);
            }
            
            return $query->orderBy('valid_from', 'desc')->get();
        } catch (\Exception $e) {
            Log::error('Error getting versions valid on date', [
                'date' => $date,
                'service_catalog_id' => $serviceCatalogId,
                'facility_id' => $facilityId,
                'error' => $e->getMessage()
            ]);
            return new Collection();
        }
    }

    /**
     * Create a new service version.
     *
     * @param array $data
     * @return ServiceVersion
     */
    public function create(array $data): ServiceVersion
    {
        try {
            DB::beginTransaction();
            
            // Calculate final price if not provided
            if (!isset($data['final_price_amount']) && isset($data['base_price_amount'])) {
                $data['final_price_amount'] = $this->calculateFinalPrice(
                    $data['base_price_amount'],
                    $data['facility_markup_percentage'] ?? null
                );
            }
            
            // Generate version hash
            if (!isset($data['version_hash'])) {
                $data['version_hash'] = $this->generateVersionHash($data);
            }
            
            $serviceVersion = $this->model->create($data);
            
            // If this version is marked as current, update other versions
            if ($serviceVersion->is_current) {
                $this->updateCurrentVersion(
                    $serviceVersion->service_catalog_id,
                    $serviceVersion->facility_id,
                    $serviceVersion->id
                );
            }
            
            DB::commit();
            
            return $serviceVersion->load(['serviceCatalog', 'facility', 'createdBy']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating service version', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Update an existing service version.
     *
     * @param ServiceVersion $serviceVersion
     * @param array $data
     * @return ServiceVersion
     */
    public function update(ServiceVersion $serviceVersion, array $data): ServiceVersion
    {
        try {
            DB::beginTransaction();
            
            // Recalculate final price if base price or markup changed
            if (isset($data['base_price_amount']) || isset($data['facility_markup_percentage'])) {
                $basePrice = $data['base_price_amount'] ?? $serviceVersion->base_price_amount;
                $markup = $data['facility_markup_percentage'] ?? $serviceVersion->facility_markup_percentage;
                $data['final_price_amount'] = $this->calculateFinalPrice($basePrice, $markup);
            }
            
            // Regenerate version hash if any data changed
            if (!empty($data)) {
                $data['version_hash'] = $this->generateVersionHash(
                    array_merge($serviceVersion->toArray(), $data)
                );
            }
            
            $serviceVersion->update($data);
            
            // If this version is marked as current, update other versions
            if (isset($data['is_current']) && $data['is_current']) {
                $this->updateCurrentVersion(
                    $serviceVersion->service_catalog_id,
                    $serviceVersion->facility_id,
                    $serviceVersion->id
                );
            }
            
            DB::commit();
            
            return $serviceVersion->load(['serviceCatalog', 'facility', 'createdBy']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating service version', [
                'service_version_id' => $serviceVersion->id,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Delete a service version.
     *
     * @param ServiceVersion $serviceVersion
     * @return bool
     */
    public function delete(ServiceVersion $serviceVersion): bool
    {
        try {
            DB::beginTransaction();
            
            // If deleting current version, find and set another version as current
            if ($serviceVersion->is_current) {
                $alternativeVersion = $this->model
                    ->where('service_catalog_id', $serviceVersion->service_catalog_id)
                    ->where('facility_id', $serviceVersion->facility_id)
                    ->where('id', '!=', $serviceVersion->id)
                    ->orderBy('valid_from', 'desc')
                    ->first();
                
                if ($alternativeVersion) {
                    $alternativeVersion->update(['is_current' => true]);
                }
            }
            
            $deleted = $serviceVersion->delete();
            
            DB::commit();
            
            return $deleted;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting service version', [
                'service_version_id' => $serviceVersion->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Update the current version flag for a service.
     *
     * @param int $serviceCatalogId
     * @param int|null $facilityId
     * @param int $newCurrentVersionId
     * @return bool
     */
    public function updateCurrentVersion(int $serviceCatalogId, ?int $facilityId, int $newCurrentVersionId): bool
    {
        try {
            DB::beginTransaction();
            
            // Set all versions for this service/facility as not current
            $this->model
                ->where('service_catalog_id', $serviceCatalogId)
                ->where('facility_id', $facilityId)
                ->update(['is_current' => false]);
            
            // Set the new version as current
            $this->model
                ->where('id', $newCurrentVersionId)
                ->update(['is_current' => true]);
            
            DB::commit();
            
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating current version', [
                'service_catalog_id' => $serviceCatalogId,
                'facility_id' => $facilityId,
                'new_current_version_id' => $newCurrentVersionId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Check if a version number already exists for service catalog and facility.
     *
     * @param int $serviceCatalogId
     * @param int|null $facilityId
     * @param string $versionNumber
     * @param int|null $excludeId
     * @return bool
     */
    public function versionNumberExists(int $serviceCatalogId, ?int $facilityId, string $versionNumber, ?int $excludeId = null): bool
    {
        try {
            $query = $this->model
                ->where('service_catalog_id', $serviceCatalogId)
                ->where('facility_id', $facilityId)
                ->where('version_number', $versionNumber);
            
            if ($excludeId !== null) {
                $query->where('id', '!=', $excludeId);
            }
            
            return $query->exists();
        } catch (\Exception $e) {
            Log::error('Error checking version number existence', [
                'service_catalog_id' => $serviceCatalogId,
                'facility_id' => $facilityId,
                'version_number' => $versionNumber,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get version history for a service.
     *
     * @param int $serviceCatalogId
     * @param int|null $facilityId
     * @return Collection
     */
    public function getVersionHistory(int $serviceCatalogId, ?int $facilityId = null): Collection
    {
        try {
            $query = $this->model->with(['serviceCatalog', 'facility', 'createdBy'])
                ->where('service_catalog_id', $serviceCatalogId)
                ->orderBy('valid_from', 'desc');
            
            if ($facilityId !== null) {
                $query->where('facility_id', $facilityId);
            }
            
            return $query->get();
        } catch (\Exception $e) {
            Log::error('Error getting version history', [
                'service_catalog_id' => $serviceCatalogId,
                'facility_id' => $facilityId,
                'error' => $e->getMessage()
            ]);
            return new Collection();
        }
    }

    /**
     * Calculate and update final price based on markup.
     *
     * @param ServiceVersion $serviceVersion
     * @return ServiceVersion
     */
    public function recalculateFinalPrice(ServiceVersion $serviceVersion): ServiceVersion
    {
        try {
            $finalPrice = $this->calculateFinalPrice(
                $serviceVersion->base_price_amount,
                $serviceVersion->facility_markup_percentage
            );
            
            $serviceVersion->update(['final_price_amount' => $finalPrice]);
            
            return $serviceVersion;
        } catch (\Exception $e) {
            Log::error('Error recalculating final price', [
                'service_version_id' => $serviceVersion->id,
                'error' => $e->getMessage()
            ]);
            return $serviceVersion;
        }
    }

    /**
     * Calculate final price based on base price and markup.
     *
     * @param float $basePrice
     * @param float|null $markupPercentage
     * @return float
     */
    private function calculateFinalPrice(float $basePrice, ?float $markupPercentage): float
    {
        if (empty($markupPercentage)) {
            return $basePrice;
        }
        
        $markupAmount = ($basePrice * $markupPercentage) / 100;
        return $basePrice + $markupAmount;
    }

    /**
     * Generate version hash for data integrity.
     *
     * @param array $data
     * @return string
     */
    private function generateVersionHash(array $data): string
    {
        // Remove hash from data to avoid circular dependency
        unset($data['version_hash']);
        
        // Sort array to ensure consistent hashing
        ksort($data);
        
        return hash('sha256', json_encode($data, JSON_NUMERIC_CHECK));
    }
}