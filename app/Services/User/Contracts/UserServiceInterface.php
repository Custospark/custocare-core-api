<?php

declare(strict_types=1);

namespace App\Services\User\Contracts;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface UserServiceInterface
{
    /**
     * Get all users with pagination.
     *
     * @param int $perPage
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getAllUsers(int $perPage = 15, array $filters = []): LengthAwarePaginator;

    /**
     * Get user by ID.
     *
     * @param int $id
     * @return User
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function getUserById(int $id): User;

    /**
     * Get user by UUID.
     *
     * @param string $uuid
     * @return User
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function getUserByUuid(string $uuid): User;

    /**
     * Create a new user.
     *
     * @param array $data
     * @return User
     * @throws \Illuminate\Validation\ValidationException
     */
    public function createUser(array $data): User;

    /**
     * Update an existing user.
     *
     * @param int $id
     * @param array $data
     * @return User
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function updateUser(int $id, array $data): User;

    /**
     * Delete a user (soft delete).
     *
     * @param int $id
     * @return void
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function deleteUser(int $id): void;

    /**
     * Verify user identity.
     *
     * @param int $userId
     * @param int $staffId
     * @param string $method
     * @return User
     */
    public function verifyIdentity(int $userId, int $staffId, string $method): User;

    /**
     * Suspend user.
     *
     * @param int $userId
     * @return User
     */
    public function suspendUser(int $userId): User;

    /**
     * Restore suspended user.
     *
     * @param int $userId
     * @return User
     */
    public function restoreUser(int $userId): User;

    /**
     * Archive user.
     *
     * @param int $userId
     * @return User
     */
    public function archiveUser(int $userId): User;

    /**
     * Update password.
     *
     * @param int $userId
     * @param string $password
     * @return User
     */
    public function updatePassword(int $userId, string $password): User;

    /**
     * Record successful login.
     *
     * @param User $user
     * @param string $ip
     * @param string $userAgent
     * @return User
     */
    public function recordSuccessfulLogin(User $user, string $ip, string $userAgent): User;

    /**
     * Record failed login attempt.
     *
     * @param User $user
     * @return User
     */
    public function recordFailedLoginAttempt(User $user): User;

    /**
     * Get users by data residency region.
     *
     * @param string $region
     * @return Collection
     */
    public function getUsersByDataResidencyRegion(string $region): Collection;

    /**
     * Get users pending identity verification.
     *
     * @return Collection
     */
    public function getPendingIdentityVerificationUsers(): Collection;
}