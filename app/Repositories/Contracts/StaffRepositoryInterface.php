<?php

namespace App\Repositories\Contracts;

use App\Models\Staff;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface StaffRepositoryInterface
{
    /**
     * Find staff by ID.
     */
    public function find(int $id): ?Staff;

    /**
     * Find staff by UUID.
     */
    public function findByUuid(string $uuid): ?Staff;

    /**
     * Find staff by user ID.
     */
    public function findByUserId(int $userId): ?Staff;

    /**
     * Find staff by employee ID.
     */
    public function findByEmployeeId(string $employeeId): ?Staff;

    /**
     * Get all staff with pagination.
     */
    public function all(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Create new staff record.
     */
    public function create(array $data): Staff;

    /**
     * Update staff record.
     */
    public function update(int $id, array $data): bool;

    /**
     * Delete staff record (soft delete).
     */
    public function delete(int $id): bool;

    /**
     * Force delete staff record.
     */
    public function forceDelete(int $id): bool;

    /**
     * Restore soft deleted staff record.
     */
    public function restore(int $id): bool;

    /**
     * Get staff by employment status.
     */
    public function getByEmploymentStatus(string $status): Collection;

    /**
     * Get staff by role level.
     */
    public function getByRoleLevel(string $roleLevel): Collection;

    /**
     * Get staff with expiring licenses.
     */
    public function getWithExpiringLicenses(int $days = 30): Collection;

    /**
     * Get staff with expiring DEA registrations.
     */
    public function getWithExpiringDEA(int $days = 30): Collection;

    /**
     * Search staff by criteria.
     */
    public function search(array $criteria): Collection;

    /**
     * Update staff license information.
     */
    public function updateLicenseInfo(int $id, array $licenseData): bool;
}