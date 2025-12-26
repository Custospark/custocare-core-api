<?php

declare(strict_types=1);

namespace App\Repositories\User\Contracts;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface UserRepositoryInterface
{
    /**
     * Find user by ID.
     *
     * @param int $id
     * @return User|null
     */
    public function findById(int $id): ?User;

    /**
     * Find user by global UUID.
     *
     * @param string $uuid
     * @return User|null
     */
    public function findByUuid(string $uuid): ?User;

    /**
     * Find user by email hash.
     *
     * @param string $emailHash
     * @return User|null
     */
    public function findByEmailHash(string $emailHash): ?User;

    /**
     * Find user by national ID hash.
     *
     * @param string $nationalIdHash
     * @return User|null
     */
    public function findByNationalIdHash(string $nationalIdHash): ?User;

    /**
     * Get all users with pagination.
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllPaginated(array $filters = [], int $perPage = 20): LengthAwarePaginator;

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
     * @return bool
     */
    public function update(User $user, array $data): bool;

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
     * Update user's last login information.
     *
     * @param User $user
     * @param string $ip
     * @param string $userAgent
     * @return bool
     */
    public function updateLastLogin(User $user, string $ip, string $userAgent): bool;

    /**
     * Increment failed login attempts.
     *
     * @param User $user
     * @return bool
     */
    public function incrementFailedAttempts(User $user): int;

    /**
     * Reset failed login attempts.
     *
     * @param User $user
     * @return bool
     */
    public function resetFailedAttempts(User $user): bool;

    /**
     * Lock user account.
     *
     * @param User $user
     * @param \DateTimeInterface $until
     * @return bool
     */
    public function lockAccount(User $user, \DateTimeInterface $until): bool;

    /**
     * Unlock user account.
     *
     * @param User $user
     * @return bool
     */
    public function unlockAccount(User $user): bool;
}