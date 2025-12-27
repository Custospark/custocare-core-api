<?php

namespace App\Repositories\Contracts;

use App\Models\StaffCredential;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface StaffCredentialRepositoryInterface
{
    /**
     * Find credential by UUID
     *
     * @param string $uuid
     * @return StaffCredential|null
     */
    public function findByUuid(string $uuid): ?StaffCredential;

    /**
     * Get all credentials for a staff member
     *
     * @param int $staffId
     * @param array $filters
     * @return Collection
     */
    public function getByStaffId(int $staffId, array $filters = []): Collection;

    /**
     * Get current credentials for a staff member
     *
     * @param int $staffId
     * @return Collection
     */
    public function getCurrentByStaffId(int $staffId): Collection;

    /**
     * Get credentials by type
     *
     * @param string $type
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getByType(string $type, array $filters = []): LengthAwarePaginator;

    /**
     * Get expiring credentials
     *
     * @param int $days
     * @return Collection
     */
    public function getExpiringSoon(int $days = 30): Collection;

    /**
     * Get expired credentials
     *
     * @return Collection
     */
    public function getExpired(): Collection;

    /**
     * Create new credential
     *
     * @param array $data
     * @return StaffCredential
     */
    public function create(array $data): StaffCredential;

    /**
     * Update credential
     *
     * @param string $uuid
     * @param array $data
     * @return StaffCredential
     */
    public function update(string $uuid, array $data): StaffCredential;

    /**
     * Delete credential (soft delete)
     *
     * @param string $uuid
     * @return bool
     */
    public function delete(string $uuid): bool;

    /**
     * Restore soft-deleted credential
     *
     * @param string $uuid
     * @return bool
     */
    public function restore(string $uuid): bool;

    /**
     * Permanently delete credential
     *
     * @param string $uuid
     * @return bool
     */
    public function forceDelete(string $uuid): bool;

    /**
     * Verify credential
     *
     * @param string $uuid
     * @param array $verificationData
     * @return StaffCredential
     */
    public function verify(string $uuid, array $verificationData): StaffCredential;

    /**
     * Supersede credential with new one
     *
     * @param string $uuid
     * @param array $newCredentialData
     * @return array Contains old and new credential
     */
    public function supersede(string $uuid, array $newCredentialData): array;

    /**
     * Search credentials with filters
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function search(array $filters, int $perPage = 15): LengthAwarePaginator;
}