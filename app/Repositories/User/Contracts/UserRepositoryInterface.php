<?php

declare(strict_types=1);

namespace App\Repositories\User\Contracts;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface UserRepositoryInterface
{
    /**
     * Find a user by ID.
     *
     * @param int $id
     * @return User|null
     */
    public function find(int $id): ?User;

    /**
     * Find a user by global UUID.
     *
     * @param string $uuid
     * @return User|null
     */
    public function findByUuid(string $uuid): ?User;

    /**
     * Find a user by national ID hash.
     *
     * @param string $nationalIdHash
     * @return User|null
     */
    public function findByNationalIdHash(string $nationalIdHash): ?User;

    /**
     * Find users by identity state.
     *
     * @param string $identityState
     * @param array $relations
     * @return Collection
     */
    public function findByIdentityState(string $identityState, array $relations = []): Collection;

    /**
     * Get all users with pagination.
     *
     * @param int $perPage
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator;

    /**
     * Create a new user.
     *
     * @param array $data
     * @return User
     */
    public function create(array $data): User;

    /**
     * Update an existing user.
     *
     * @param User $user
     * @param array $data
     * @return User
     */
    public function update(User $user, array $data): User;

    /**
     * Delete a user (soft delete).
     *
     * @param User $user
     * @return bool
     */
    public function delete(User $user): bool;

    /**
     * Restore a soft-deleted user.
     *
     * @param User $user
     * @return bool
     */
    public function restore(User $user): bool;

    /**
     * Permanently delete a user.
     *
     * @param User $user
     * @return bool
     */
    public function forceDelete(User $user): bool;

    /**
     * Update user's identity verification status.
     *
     * @param User $user
     * @param array $verificationData
     * @return User
     */
    public function updateIdentityVerification(User $user, array $verificationData): User;

    /**
     * Get users by data residency region.
     *
     * @param string $region
     * @return Collection
     */
    public function getByDataResidencyRegion(string $region): Collection;

    /**
     * Check if email hash exists.
     *
     * @param string $emailHash
     * @return bool
     */
    public function emailHashExists(string $emailHash): bool;

    /**
     * Check if phone hash exists.
     *
     * @param string $phoneHash
     * @return bool
     */
    public function phoneHashExists(string $phoneHash): bool;
}