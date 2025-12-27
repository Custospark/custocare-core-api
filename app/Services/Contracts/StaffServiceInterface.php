<?php

namespace App\Services\Contracts;

use App\Models\Staff;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface StaffServiceInterface
{
    /**
     * Get staff by ID.
     */
    public function getStaffById(int $id): ?Staff;

    /**
     * Get staff by UUID.
     */
    public function getStaffByUuid(string $uuid): ?Staff;

    /**
     * Get all staff with pagination.
     */
    public function getAllStaff(array $filters = [], int $perPage = 20);

    /**
     * Create new staff.
     */
    public function createStaff(array $data): array;

    /**
     * Update staff.
     */
    public function updateStaff(int $id, array $data): array;

    /**
     * Delete staff.
     */
    public function deleteStaff(int $id): array;

    /**
     * Update staff license information.
     */
    public function updateLicenseInfo(int $id, array $licenseData): array;

    /**
     * Update employment status.
     */
    public function updateEmploymentStatus(int $id, string $status, ?string $reason = null): array;

    /**
     * Check staff credentials for specific privilege.
     */
    public function checkStaffPrivilege(int $staffId, string $privilege): bool;

    /**
     * Get staff with expiring credentials.
     */
    public function getStaffWithExpiringCredentials(int $days = 30): array;

    /**
     * Validate staff can perform action.
     */
    public function validateStaffAction(int $staffId, string $action): array;

    /**
     * Bulk update staff status.
     */
    public function bulkUpdateStatus(array $staffIds, string $status): array;
}