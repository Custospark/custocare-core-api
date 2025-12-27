<?php

namespace App\Services\Contracts;

use App\Models\StaffCredential;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface StaffCredentialServiceInterface
{
    /**
     * Get credential by UUID
     *
     * @param string $uuid
     * @return array
     */
    public function getCredential(string $uuid): array;

    /**
     * Get all credentials for a staff member
     *
     * @param int $staffId
     * @param array $filters
     * @return array
     */
    public function getStaffCredentials(int $staffId, array $filters = []): array;

    /**
     * Create new credential
     *
     * @param array $data
     * @return array
     */
    public function createCredential(array $data): array;

    /**
     * Update credential
     *
     * @param string $uuid
     * @param array $data
     * @return array
     */
    public function updateCredential(string $uuid, array $data): array;

    /**
     * Delete credential
     *
     * @param string $uuid
     * @return array
     */
    public function deleteCredential(string $uuid): array;

    /**
     * Verify credential
     *
     * @param string $uuid
     * @param array $verificationData
     * @param int $verifyingStaffId
     * @return array
     */
    public function verifyCredential(string $uuid, array $verificationData, int $verifyingStaffId): array;

    /**
     * Supersede credential with new version
     *
     * @param string $uuid
     * @param array $newCredentialData
     * @param int $createdByStaffId
     * @return array
     */
    public function supersedeCredential(string $uuid, array $newCredentialData, int $createdByStaffId): array;

    /**
     * Get expiring credentials
     *
     * @param int $days
     * @return array
     */
    public function getExpiringCredentials(int $days = 30): array;

    /**
     * Get expired credentials
     *
     * @return array
     */
    public function getExpiredCredentials(): array;

    /**
     * Search credentials with filters
     *
     * @param array $filters
     * @param int $perPage
     * @return array
     */
    public function searchCredentials(array $filters, int $perPage = 15): array;

    /**
     * Validate credential data
     *
     * @param array $data
     * @param bool $isUpdate
     * @return array Validation errors, empty if valid
     */
    public function validateCredentialData(array $data, bool $isUpdate = false): array;

    /**
     * Get credential statistics
     *
     * @param int|null $staffId
     * @return array
     */
    public function getStatistics(?int $staffId = null): array;
}