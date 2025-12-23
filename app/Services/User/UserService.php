<?php

declare(strict_types=1);

namespace App\Services\User;

use App\Models\User;
use App\Repositories\User\Contracts\UserRepositoryInterface;
use App\Services\User\Contracts\UserServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UserService implements UserServiceInterface
{
    /**
     * Constructor.
     *
     * @param UserRepositoryInterface $userRepository
     */
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {}

    /**
     * Get all users with pagination.
     *
     * @param int $perPage
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getAllUsers(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return $this->userRepository->paginate($perPage, $filters);
    }

    /**
     * Get user by ID.
     *
     * @param int $id
     * @return User
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function getUserById(int $id): User
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException("User with ID {$id} not found.");
        }

        return $user;
    }

    /**
     * Get user by UUID.
     *
     * @param string $uuid
     * @return User
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function getUserByUuid(string $uuid): User
    {
        $user = $this->userRepository->findByUuid($uuid);

        if (!$user) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException("User with UUID {$uuid} not found.");
        }

        return $user;
    }

    /**
     * Create a new user.
     *
     * @param array $data
     * @return User
     * @throws ValidationException
     */
    public function createUser(array $data): User
    {
        // Validate input data
        $validator = Validator::make($data, [
            'national_id_hash' => 'required|string|max:128|unique:users',
            'national_id_encrypted' => 'required|string|max:512',
            'national_id_country_code' => 'required|string|size:3',
            'data_residency_region' => 'required|string|max:10',
            'allowed_processing_regions' => 'nullable|array',
            'created_from_facility_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // Generate UUID if not provided
        if (!isset($data['global_user_uuid'])) {
            $data['global_user_uuid'] = \Illuminate\Support\Str::uuid()->toString();
        }

        // Set default identity state
        if (!isset($data['identity_state'])) {
            $data['identity_state'] = User::IDENTITY_STATE_PENDING;
        }

        return $this->userRepository->create($data);
    }

    /**
     * Update an existing user.
     *
     * @param int $id
     * @param array $data
     * @return User
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function updateUser(int $id, array $data): User
    {
        $user = $this->getUserById($id);

        // Validate update data
        $validator = Validator::make($data, [
            'national_id_hash' => 'sometimes|string|max:128|unique:users,national_id_hash,' . $id,
            'data_residency_region' => 'sometimes|string|max:10',
            'allowed_processing_regions' => 'nullable|array',
            'identity_state' => 'sometimes|in:pending,verified,suspended,archived',
            'email_hash' => 'nullable|string|max:128|unique:users,email_hash,' . $id,
            'phone_hash' => 'nullable|string|max:128|unique:users,phone_hash,' . $id,
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $this->userRepository->update($user, $data);
    }

    /**
     * Delete a user (soft delete).
     *
     * @param int $id
     * @return void
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function deleteUser(int $id): void
    {
        $user = $this->getUserById($id);
        $this->userRepository->delete($user);
    }

    /**
     * Verify user identity.
     *
     * @param int $userId
     * @param int $staffId
     * @param string $method
     * @return User
     */
    public function verifyIdentity(int $userId, int $staffId, string $method): User
    {
        $user = $this->getUserById($userId);

        $verificationData = [
            'identity_state' => User::IDENTITY_STATE_VERIFIED,
            'identity_verification_method' => $method,
            'identity_verified_by_staff_id' => $staffId,
        ];

        return $this->userRepository->updateIdentityVerification($user, $verificationData);
    }

    /**
     * Suspend user.
     *
     * @param int $userId
     * @return User
     */
    public function suspendUser(int $userId): User
    {
        $user = $this->getUserById($userId);
        return $this->userRepository->update($user, [
            'identity_state' => User::IDENTITY_STATE_SUSPENDED,
        ]);
    }

    /**
     * Restore suspended user.
     *
     * @param int $userId
     * @return User
     */
    public function restoreUser(int $userId): User
    {
        $user = $this->getUserById($userId);
        return $this->userRepository->update($user, [
            'identity_state' => User::IDENTITY_STATE_VERIFIED,
        ]);
    }

    /**
     * Archive user.
     *
     * @param int $userId
     * @return User
     */
    public function archiveUser(int $userId): User
    {
        $user = $this->getUserById($userId);
        return $this->userRepository->update($user, [
            'identity_state' => User::IDENTITY_STATE_ARCHIVED,
        ]);
    }

    /**
     * Update password.
     *
     * @param int $userId
     * @param string $password
     * @return User
     */
    public function updatePassword(int $userId, string $password): User
    {
        $user = $this->getUserById($userId);

        return $this->userRepository->update($user, [
            'password_hash' => Hash::make($password),
            'password_changed_at' => now(),
            'requires_password_change' => false,
        ]);
    }

    /**
     * Record successful login.
     *
     * @param User $user
     * @param string $ip
     * @param string $userAgent
     * @return User
     */
    public function recordSuccessfulLogin(User $user, string $ip, string $userAgent): User
    {
        $data = [
            'last_login_at' => now(),
            'last_login_ip' => $ip,
            'last_login_user_agent' => $userAgent,
            'failed_login_attempts' => 0,
            'account_locked_until' => null,
        ];

        return $this->userRepository->update($user, $data);
    }

    /**
     * Record failed login attempt.
     *
     * @param User $user
     * @return User
     */
    public function recordFailedLoginAttempt(User $user): User
    {
        $failedAttempts = $user->failed_login_attempts + 1;
        
        $data = [
            'failed_login_attempts' => $failedAttempts,
        ];

        // Lock account after 5 failed attempts for 15 minutes
        if ($failedAttempts >= 5) {
            $data['account_locked_until'] = now()->addMinutes(15);
        }

        return $this->userRepository->update($user, $data);
    }

    /**
     * Get users by data residency region.
     *
     * @param string $region
     * @return Collection
     */
    public function getUsersByDataResidencyRegion(string $region): Collection
    {
        return $this->userRepository->getByDataResidencyRegion($region);
    }

    /**
     * Get users pending identity verification.
     *
     * @return Collection
     */
    public function getPendingIdentityVerificationUsers(): Collection
    {
        return $this->userRepository->findByIdentityState(User::IDENTITY_STATE_PENDING);
    }
}