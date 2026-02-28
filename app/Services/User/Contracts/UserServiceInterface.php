<?php

declare(strict_types=1);

namespace App\Services\User\Contracts;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface UserServiceInterface
{
    /**
     * Register a new user.
     *
     * @param array $data
     * @return User
     */
    public function register(array $data): User;

    /**
     * Authenticate user login.
     *
     * @param array $credentials
     * @param string $ip
     * @param string $userAgent
     * @return array
     * @throws \Exception
     */
    public function login(array $credentials, string $ip, string $userAgent): array;

    /**
     * Logout user.
     *
     * @param User $user
     * @return bool
     */
    public function logout(User $user): bool;

    /**
     * Get all users with pagination.
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllUsers(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    /**
     * Get user by ID.
     *
     * @param int $id
     * @return User
     * @throws \Exception
     */
    public function getUserById(int $id): User;

    /**
     * Get user by UUID.
     *
     * @param string $uuid
     * @return User
     * @throws \Exception
     */
    public function getUserByUuid(string $uuid): User;

    /**
     * Create a new user (admin function).
     *
     * @param array $data
     * @return User
     */
    public function createUser(array $data): User;

    /**
     * Update an existing user.
     *
     * @param int $id
     * @param array $data
     * @return User
     */
    public function updateUser(int $id, array $data): User;

    /**
     * Delete a user (soft delete).
     *
     * @param int $id
     * @return bool
     */
    public function deleteUser(int $id): bool;

    /**
     * Restore a soft-deleted user.
     *
     * @param int $id
     * @return User
     */
    public function restoreUser(int $id): User;

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
     * Update user password.
     *
     * @param int $userId
     * @param string $newPassword
     * @param string|null $currentPassword
     * @return bool
     */
    public function updatePassword(int $userId, string $newPassword, ?string $currentPassword = null): bool;

    /**
     * Enable MFA for user.
     *
     * @param int $userId
     * @return array
     */
    public function enableMfa(int $userId): array;

    /**
     * Disable MFA for user.
     *
     * @param int $userId
     * @return bool
     */
    public function disableMfa(int $userId): bool;

    /**
     * Validate MFA code.
     *
     * @param int $userId
     * @param string $code
     * @return bool
     */
    public function validateMfa(int $userId, string $code): bool;

        /**
     * Upload a profile photo for a user.
     *
     * @param User $user
     * @param \Illuminate\Http\UploadedFile $photo
     * @return string The path to the uploaded photo
     * @throws \Throwable
     */
    public function uploadProfilePhoto(User $user, \Illuminate\Http\UploadedFile $photo): string;
}