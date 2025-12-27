<?php

namespace App\Repositories\Staff;

use App\Models\Staff;
use App\Repositories\Contracts\StaffRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class StaffRepository implements StaffRepositoryInterface
{
    /**
     * Find staff by ID.
     */
    public function find(int $id): ?Staff
    {
        try {
            return Staff::find($id);
        } catch (\Exception $e) {
            Log::error('Error finding staff by ID', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Find staff by UUID.
     */
    public function findByUuid(string $uuid): ?Staff
    {
        try {
            return Staff::where('staff_uuid', $uuid)->first();
        } catch (\Exception $e) {
            Log::error('Error finding staff by UUID', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Find staff by user ID.
     */
    public function findByUserId(int $userId): ?Staff
    {
        try {
            return Staff::where('user_id', $userId)->first();
        } catch (\Exception $e) {
            Log::error('Error finding staff by user ID', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Find staff by employee ID.
     */
    public function findByEmployeeId(string $employeeId): ?Staff
    {
        try {
            return Staff::where('employee_id', $employeeId)->first();
        } catch (\Exception $e) {
            Log::error('Error finding staff by employee ID', [
                'employee_id' => $employeeId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get all staff with pagination.
     */
    public function all(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        try {
            $query = Staff::query();
            
            // Apply filters
            if (!empty($filters['employment_status'])) {
                $query->where('employment_status', $filters['employment_status']);
            }
            
            if (!empty($filters['global_role_level'])) {
                $query->where('global_role_level', $filters['global_role_level']);
            }
            
            if (!empty($filters['search'])) {
                $query->where(function ($q) use ($filters) {
                    $q->where('professional_title', 'like', "%{$filters['search']}%")
                      ->orWhere('employee_id', 'like', "%{$filters['search']}%");
                });
            }
            
            if (!empty($filters['has_expired_license']) && $filters['has_expired_license'] === true) {
                $query->whereNotNull('license_expiry_date')
                      ->where('license_expiry_date', '<', now());
            }
            
            // Eager load relationships
            $query->with(['user', 'supervisor', 'createdBy', 'updatedBy']);
            
            return $query->paginate($perPage);
        } catch (\Exception $e) {
            Log::error('Error retrieving staff list', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return new LengthAwarePaginator([], 0, $perPage);
        }
    }

    /**
     * Create new staff record.
     */
    public function create(array $data): Staff
    {
        try {
            return Staff::create($data);
        } catch (\Exception $e) {
            Log::error('Error creating staff record', [
                'data' => array_keys($data),
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to create staff record.');
        }
    }

    /**
     * Update staff record.
     */
    public function update(int $id, array $data): bool
    {
        try {
            $staff = $this->find($id);
            
            if (!$staff) {
                return false;
            }
            
            return $staff->update($data);
        } catch (\Exception $e) {
            Log::error('Error updating staff record', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Delete staff record (soft delete).
     */
    public function delete(int $id): bool
    {
        try {
            $staff = $this->find($id);
            
            if (!$staff) {
                return false;
            }
            
            return $staff->delete();
        } catch (\Exception $e) {
            Log::error('Error deleting staff record', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Force delete staff record.
     */
    public function forceDelete(int $id): bool
    {
        try {
            $staff = Staff::withTrashed()->find($id);
            
            if (!$staff) {
                return false;
            }
            
            return $staff->forceDelete();
        } catch (\Exception $e) {
            Log::error('Error force deleting staff record', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Restore soft deleted staff record.
     */
    public function restore(int $id): bool
    {
        try {
            $staff = Staff::withTrashed()->find($id);
            
            if (!$staff) {
                return false;
            }
            
            return $staff->restore();
        } catch (\Exception $e) {
            Log::error('Error restoring staff record', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get staff by employment status.
     */
    public function getByEmploymentStatus(string $status): Collection
    {
        try {
            return Staff::where('employment_status', $status)
                ->with(['user', 'supervisor'])
                ->get();
        } catch (\Exception $e) {
            Log::error('Error getting staff by employment status', [
                'status' => $status,
                'error' => $e->getMessage()
            ]);
            return new Collection();
        }
    }

    /**
     * Get staff by role level.
     */
    public function getByRoleLevel(string $roleLevel): Collection
    {
        try {
            return Staff::where('global_role_level', $roleLevel)
                ->with(['user'])
                ->get();
        } catch (\Exception $e) {
            Log::error('Error getting staff by role level', [
                'role_level' => $roleLevel,
                'error' => $e->getMessage()
            ]);
            return new Collection();
        }
    }

    /**
     * Get staff with expiring licenses.
     */
    public function getWithExpiringLicenses(int $days = 30): Collection
    {
        try {
            $expiryDate = now()->addDays($days);
            
            return Staff::whereNotNull('license_expiry_date')
                ->where('license_expiry_date', '<=', $expiryDate)
                ->where('license_expiry_date', '>', now())
                ->where('employment_status', 'active')
                ->with(['user'])
                ->get();
        } catch (\Exception $e) {
            Log::error('Error getting staff with expiring licenses', [
                'days' => $days,
                'error' => $e->getMessage()
            ]);
            return new Collection();
        }
    }

    /**
     * Get staff with expiring DEA registrations.
     */
    public function getWithExpiringDEA(int $days = 30): Collection
    {
        try {
            $expiryDate = now()->addDays($days);
            
            return Staff::whereNotNull('dea_expiry_date')
                ->where('dea_expiry_date', '<=', $expiryDate)
                ->where('dea_expiry_date', '>', now())
                ->where('employment_status', 'active')
                ->with(['user'])
                ->get();
        } catch (\Exception $e) {
            Log::error('Error getting staff with expiring DEA registrations', [
                'days' => $days,
                'error' => $e->getMessage()
            ]);
            return new Collection();
        }
    }

    /**
     * Search staff by criteria.
     */
    public function search(array $criteria): Collection
    {
        try {
            $query = Staff::query();
            
            foreach ($criteria as $field => $value) {
                if (is_array($value)) {
                    $query->whereIn($field, $value);
                } else {
                    $query->where($field, $value);
                }
            }
            
            return $query->with(['user', 'supervisor'])->get();
        } catch (\Exception $e) {
            Log::error('Error searching staff', [
                'criteria' => $criteria,
                'error' => $e->getMessage()
            ]);
            return new Collection();
        }
    }

    /**
     * Update staff license information.
     */
    public function updateLicenseInfo(int $id, array $licenseData): bool
    {
        try {
            return DB::transaction(function () use ($id, $licenseData) {
                $staff = $this->find($id);
                
                if (!$staff) {
                    return false;
                }
                
                $updateData = [
                    'professional_license_number_encrypted' => $licenseData['license_number_encrypted'] ?? null,
                    'professional_license_number_hash' => $licenseData['license_number_hash'] ?? null,
                    'license_issuing_state' => $licenseData['issuing_state'] ?? null,
                    'license_expiry_date' => $licenseData['expiry_date'] ?? null,
                    'updated_by_staff_id' => auth::id()
                ];
                
                return $staff->update($updateData);
            });
        } catch (\Exception $e) {
            Log::error('Error updating staff license information', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}