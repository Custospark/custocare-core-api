<?php

namespace App\Repositories\Facility;

use App\Models\Facility;
use App\Repositories\Contracts\FacilityRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Class FacilityRepository
 * 
 * Implementation of FacilityRepositoryInterface.
 * Handles all database operations for Facility entity.
 */
class FacilityRepository implements FacilityRepositoryInterface
{
    /**
     * Get all facilities with optional filters.
     *
     * @param array $filters
     * @param array $relations
     * @return Collection
     */
    public function getAll(array $filters = [], array $relations = []): Collection
    {
        try {
            $query = Facility::with($relations);
            
            $this->applyFilters($query, $filters);
            
            return $query->orderBy('facility_name')->get();
        } catch (\Exception $e) {
            Log::error('Failed to get all facilities', [
                'error' => $e->getMessage(),
                'filters' => $filters,
                'trace' => $e->getTraceAsString()
            ]);
            
            return new Collection();
        }
    }

    /**
     * Get paginated facilities with optional filters.
     *
     * @param int $perPage
     * @param array $filters
     * @param array $relations
     * @return LengthAwarePaginator
     */
    public function getPaginated(int $perPage = 15, array $filters = [], array $relations = []): LengthAwarePaginator
    {
        try {
            $query = Facility::with($relations);
            
            $this->applyFilters($query, $filters);
            
            return $query->orderBy('facility_name')->paginate($perPage);
        } catch (\Exception $e) {
            Log::error('Failed to get paginated facilities', [
                'error' => $e->getMessage(),
                'filters' => $filters,
                'perPage' => $perPage,
                'trace' => $e->getTraceAsString()
            ]);
            
            // Return empty paginator on error
            return new LengthAwarePaginator([], 0, $perPage);
        }
    }

    /**
     * Find a facility by ID.
     *
     * @param int $id
     * @param array $relations
     * @return Facility|null
     */
    public function findById(int $id, array $relations = []): ?Facility
    {
        try {
            return Facility::with($relations)->find($id);
        } catch (\Exception $e) {
            Log::error('Failed to find facility by ID', [
                'error' => $e->getMessage(),
                'facility_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return null;
        }
    }

    /**
     * Find a facility by UUID.
     *
     * @param string $uuid
     * @param array $relations
     * @return Facility|null
     */
    public function findByUuid(string $uuid, array $relations = []): ?Facility
    {
        try {
            return Facility::with($relations)->where('facility_uuid', $uuid)->first();
        } catch (\Exception $e) {
            Log::error('Failed to find facility by UUID', [
                'error' => $e->getMessage(),
                'facility_uuid' => $uuid,
                'trace' => $e->getTraceAsString()
            ]);
            
            return null;
        }
    }

    /**
     * Find a facility by facility code.
     *
     * @param string $facilityCode
     * @param array $relations
     * @return Facility|null
     */
    public function findByCode(string $facilityCode, array $relations = []): ?Facility
    {
        try {
            return Facility::with($relations)->where('facility_code', $facilityCode)->first();
        } catch (\Exception $e) {
            Log::error('Failed to find facility by code', [
                'error' => $e->getMessage(),
                'facility_code' => $facilityCode,
                'trace' => $e->getTraceAsString()
            ]);
            
            return null;
        }
    }

    /**
     * Create a new facility.
     *
     * @param array $data
     * @return Facility
     * @throws \RuntimeException
     */
    public function create(array $data): Facility
    {
        DB::beginTransaction();
        
        try {
            $facility = Facility::create($data);
            
            DB::commit();
            
            return $facility;
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to create facility', [
                'error' => $e->getMessage(),
                'data' => $this->sanitizeDataForLogging($data),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw new \RuntimeException('Failed to create facility: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Update an existing facility.
     *
     * @param int $id
     * @param array $data
     * @return Facility
     * @throws \RuntimeException
     */
    public function update(int $id, array $data): Facility
    {
        DB::beginTransaction();
        
        try {
            $facility = $this->findById($id);
            
            if (!$facility) {
                throw new \RuntimeException("Facility with ID {$id} not found");
            }
            
            $facility->update($data);
            
            DB::commit();
            
            return $facility->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to update facility', [
                'error' => $e->getMessage(),
                'facility_id' => $id,
                'data' => $this->sanitizeDataForLogging($data),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw new \RuntimeException('Failed to update facility: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Delete a facility (soft delete).
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        DB::beginTransaction();
        
        try {
            $facility = $this->findById($id);
            
            if (!$facility) {
                return false;
            }
            
            $result = $facility->delete();
            
            DB::commit();
            
            return $result;
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to delete facility', [
                'error' => $e->getMessage(),
                'facility_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return false;
        }
    }

    /**
     * Force delete a facility.
     *
     * @param int $id
     * @return bool
     */
    public function forceDelete(int $id): bool
    {
        DB::beginTransaction();
        
        try {
            $facility = Facility::withTrashed()->find($id);
            
            if (!$facility) {
                return false;
            }
            
            $result = $facility->forceDelete();
            
            DB::commit();
            
            return $result;
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to force delete facility', [
                'error' => $e->getMessage(),
                'facility_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return false;
        }
    }

    /**
     * Restore a soft-deleted facility.
     *
     * @param int $id
     * @return bool
     */
    public function restore(int $id): bool
    {
        DB::beginTransaction();
        
        try {
            $facility = Facility::withTrashed()->find($id);
            
            if (!$facility) {
                return false;
            }
            
            $result = $facility->restore();
            
            DB::commit();
            
            return $result;
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to restore facility', [
                'error' => $e->getMessage(),
                'facility_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return false;
        }
    }

    /**
     * Get facilities by location.
     *
     * @param string $countryCode
     * @param string|null $stateProvince
     * @param string|null $city
     * @return Collection
     */
    public function getByLocation(string $countryCode, ?string $stateProvince = null, ?string $city = null): Collection
    {
        try {
            $query = Facility::query();
            
            $query->where('country_code', $countryCode);
            
            if ($stateProvince) {
                $query->where('state_province', $stateProvince);
            }
            
            if ($city) {
                $query->where('city', $city);
            }
            
            return $query->orderBy('facility_name')->get();
        } catch (\Exception $e) {
            Log::error('Failed to get facilities by location', [
                'error' => $e->getMessage(),
                'country_code' => $countryCode,
                'state_province' => $stateProvince,
                'city' => $city,
                'trace' => $e->getTraceAsString()
            ]);
            
            return new Collection();
        }
    }

    /**
     * Get facilities by type and status.
     *
     * @param string $type
     * @param string $status
     * @return Collection
     */
    public function getByTypeAndStatus(string $type, string $status): Collection
    {
        try {
            return Facility::where('facility_type', $type)
                ->where('operational_status', $status)
                ->orderBy('facility_name')
                ->get();
        } catch (\Exception $e) {
            Log::error('Failed to get facilities by type and status', [
                'error' => $e->getMessage(),
                'type' => $type,
                'status' => $status,
                'trace' => $e->getTraceAsString()
            ]);
            
            return new Collection();
        }
    }

    /**
     * Search facilities by name or code.
     *
     * @param string $query
     * @param int $limit
     * @return Collection
     */
    public function search(string $query, int $limit = 10): Collection
    {
        try {
            return Facility::where('facility_name', 'LIKE', "%{$query}%")
                ->orWhere('facility_code', 'LIKE', "%{$query}%")
                ->orWhere('legal_entity_name', 'LIKE', "%{$query}%")
                ->limit($limit)
                ->orderBy('facility_name')
                ->get();
        } catch (\Exception $e) {
            Log::error('Failed to search facilities', [
                'error' => $e->getMessage(),
                'query' => $query,
                'limit' => $limit,
                'trace' => $e->getTraceAsString()
            ]);
            
            return new Collection();
        }
    }

    /**
     * Check if facility code already exists.
     *
     * @param string $facilityCode
     * @param int|null $excludeId
     * @return bool
     */
    public function codeExists(string $facilityCode, ?int $excludeId = null): bool
    {
        try {
            $query = Facility::where('facility_code', $facilityCode);
            
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
            
            return $query->exists();
        } catch (\Exception $e) {
            Log::error('Failed to check if facility code exists', [
                'error' => $e->getMessage(),
                'facility_code' => $facilityCode,
                'exclude_id' => $excludeId,
                'trace' => $e->getTraceAsString()
            ]);
            
            return false;
        }
    }

    /**
     * Get facilities with emergency departments.
     *
     * @param array $filters
     * @return Collection
     */
    public function getWithEmergencyDepartments(array $filters = []): Collection
    {
        try {
            $query = Facility::where('has_emergency_department', true);
            
            $this->applyFilters($query, $filters);
            
            return $query->orderBy('facility_name')->get();
        } catch (\Exception $e) {
            Log::error('Failed to get facilities with emergency departments', [
                'error' => $e->getMessage(),
                'filters' => $filters,
                'trace' => $e->getTraceAsString()
            ]);
            
            return new Collection();
        }
    }

    /**
     * Apply filters to query.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $filters
     * @return void
     */
    private function applyFilters($query, array $filters): void
    {
        if (isset($filters['facility_type'])) {
            $query->where('facility_type', $filters['facility_type']);
        }
        
        if (isset($filters['facility_tier'])) {
            $query->where('facility_tier', $filters['facility_tier']);
        }
        
        if (isset($filters['country_code'])) {
            $query->where('country_code', $filters['country_code']);
        }
        
        if (isset($filters['state_province'])) {
            $query->where('state_province', $filters['state_province']);
        }
        
        if (isset($filters['city'])) {
            $query->where('city', $filters['city']);
        }
        
        if (isset($filters['operational_status'])) {
            $query->where('operational_status', $filters['operational_status']);
        }
        
        if (isset($filters['data_residency_region'])) {
            $query->where('data_residency_region', $filters['data_residency_region']);
        }
        
        if (isset($filters['has_emergency_department']) && $filters['has_emergency_department']) {
            $query->where('has_emergency_department', true);
        }
        
        if (isset($filters['is_24_7']) && $filters['is_24_7']) {
            $query->where('is_24_7', true);
        }
        
        if (isset($filters['search']) && $filters['search']) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('facility_name', 'LIKE', "%{$search}%")
                  ->orWhere('facility_code', 'LIKE', "%{$search}%")
                  ->orWhere('legal_entity_name', 'LIKE', "%{$search}%");
            });
        }
    }

    /**
     * Sanitize sensitive data for logging.
     *
     * @param array $data
     * @return array
     */
    private function sanitizeDataForLogging(array $data): array
    {
        $sensitiveFields = [
            'tax_id_encrypted',
            'emergency_phone',
            'email',
            'license_number',
        ];
        
        foreach ($sensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '[REDACTED]';
            }
        }
        
        return $data;
    }
}