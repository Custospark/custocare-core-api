<?php

declare(strict_types=1);

namespace App\Services\User;

use App\Models\User;
use App\Repositories\User\Contracts\UserRepositoryInterface;
use App\Support\HealthcareIdGenerator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class UserService implements Contracts\UserServiceInterface
{
    /**
     * Maximum failed login attempts before lockout.
     */
    private const MAX_FAILED_ATTEMPTS = 5;

    /**
     * Account lockout duration in minutes.
     */
    private const LOCKOUT_DURATION = 30;

    /**
     * Create a new service instance.
     *
     * @param UserRepositoryInterface $userRepository
     */
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {}

    /**
     * Register a new user.
     *
     * @param array $data
     * @return User
     * @throws \Exception
     */
    public function register(array $data): User
{
    return DB::transaction(function () use ($data) {
        try {
            $email = strtolower($data['email']);
            Log::info("Email: ".$email);
            $emailHash = hash('sha256', $email);
            Log::info("Email hash: ".$emailHash);


            // Check for duplicate email
            if ($this->userRepository->findByEmailHash($emailHash)) {
                throw new \Exception('A user with this email already exists.');
            }
            

            // Check for duplicate national ID if provided
            if (!empty($data['national_id'])) {
                $nationalIdHash = hash('sha256', $data['national_id']);
                if ($this->userRepository->findByNationalIdHash($nationalIdHash)) {
                    throw new \Exception('A user with this national ID already exists.');
                }
                $data['national_id_hash'] = $nationalIdHash;
                $data['national_id_encrypted'] = encrypt($data['national_id']);
                unset($data['national_id']);
            }
            if(empty($data['password'])){
                $data['password']=HealthcareIdGenerator::generateRandomCode();
            }

            // Generate global UUID
            $data['global_user_uuid'] = Str::uuid()->toString();

            // Encrypt email & phone
            $data['email_hash'] = $emailHash;
            $data['email_encrypted'] = encrypt($email);
            unset($data['email']);

            $phone = $data['phone'];
            $data['phone_hash'] = hash('sha256', $phone);
            $data['phone_encrypted'] = encrypt($phone);
            unset($data['phone']);

            // Hash password
            $data['password_hash'] = Hash::make($data['password']);
            $data['password_changed_at'] = now();
            unset($data['password']);

            // Set defaults
            $data['identity_state'] = $data['identity_state'] ?? 'pending';
            $data['data_residency_region'] = $data['data_residency_region'] ?? 'US';

            return $this->userRepository->create($data);

        } catch (\Exception $e) {
            Log::warning('User registration failed', [
                'error' => $e->getMessage(),
                'email' => $email,
            ]);
            throw $e; // ensures transaction rollback
        }
    });
}


    /**
     * Authenticate user login.
     *
     * @param array $credentials
     * @param string $ip
     * @param string $userAgent
     * @return array
     */
    public function login(array $credentials, string $ip, string $userAgent): array
    {
        $emailHash = hash('sha256', strtolower($credentials['email']));
        $user = $this->userRepository->findByEmailHash($emailHash);

        if (!$user) {
            return [
                'success' => false,
                'code' => 'INVALID_CREDENTIALS',
                'message' => 'Invalid credentials',
                'requires_mfa' => false,
                'user' => null,
                'token' => null,
            ];
        }

        if ($user->isAccountLocked()) {
            return [
                'success' => false,
                'code' => 'ACCOUNT_LOCKED',
                'message' => 'Account is locked. Please try again later.',
                'requires_mfa' => false,
                'user' => null,
                'token' => null,
            ];
        }

        if (!Hash::check($credentials['password'], $user->password_hash)) {
            $this->userRepository->incrementFailedAttempts($user);

            if ($user->failed_login_attempts >= self::MAX_FAILED_ATTEMPTS) {
                $this->userRepository->lockAccount(
                    $user,
                    Carbon::now()->addMinutes(self::LOCKOUT_DURATION)
                );

                return [
                    'success' => false,
                    'code' => 'ACCOUNT_LOCKED',
                    'message' => 'Account locked due to too many failed attempts.',
                    'requires_mfa' => false,
                    'user' => null,
                    'token' => null,
                ];
            }

            return [
                'success' => false,
                'code' => 'INVALID_CREDENTIALS',
                'message' => 'Invalid credentials',
                'requires_mfa' => false,
                'user' => null,
                'token' => null,
            ];
        }

        // Success path
        $this->userRepository->resetFailedAttempts($user);
        $this->userRepository->updateLastLogin($user, $ip, $userAgent);

        // Check if MFA is required
        $requiresMfa = $user->mfa_enabled;

        if ($requiresMfa && !isset($credentials['mfa_code'])) {
            return [
                'success' => true,
                'code' => 'MFA_REQUIRED',
                'message' => 'Multi-factor authentication required',
                'requires_mfa' => true,
                'user' => $user,
                'token' => null,
            ];
        }

        // Validate MFA if code is provided
        if ($requiresMfa && isset($credentials['mfa_code'])) {
            if (!$this->validateMfa($user->id, $credentials['mfa_code'])) {
                return [
                    'success' => false,
                    'code' => 'INVALID_MFA',
                    'message' => 'Invalid MFA code',
                    'requires_mfa' => true,
                    'user' => null,
                    'token' => null,
                ];
            }
        }

        // Generate token for successful login
        $token = $user->createToken('auth-token')->plainTextToken;

        return [
            'success' => true,
            'code' => 'LOGIN_SUCCESS',
            'message' => 'Login successful',
            'requires_mfa' => false,
            'user' => $user,
            'token' => $token,
        ];
    }

    /**
     * Logout user.
     *
     * @param User $user
     * @return bool
     */
    public function logout(User $user): bool
    {
        $user->tokens()->delete();
        return true;
    }

    /**
     * Get all users with pagination.
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllUsers(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->userRepository->getAllPaginated($filters, $perPage);
    }

    /**
     * Get user by ID.
     *
     * @param int $id
     * @return User
     * @throws \Exception
     */
    public function getUserById(int $id): User
    {
        $user = $this->userRepository->findById($id);

        if (!$user) {
            throw new \Exception('User not found', 404);
        }

        return $user;
    }

    /**
     * Get user by UUID.
     *
     * @param string $uuid
     * @return User
     * @throws \Exception
     */
    public function getUserByUuid(string $uuid): User
    {
        $user = $this->userRepository->findByUuid($uuid);

        if (!$user) {
            throw new \Exception('User not found', 404);
        }

        return $user;
    }

    /**
     * Create a new user (admin function).
     *
     * @param array $data
     * @return User
     */
    public function createUser(array $data): User
    {
        return $this->register($data);
    }

    /**
     * Update an existing user.
     *
     * @param int $id
     * @param array $data
     * @return User
     * @throws \Exception
     */
    public function updateUser(int $id, array $data): User
    {
        $user = $this->getUserById($id);

        // Handle email update
        if (isset($data['email'])) {
            $data['email_hash'] = hash('sha256', strtolower($data['email']));
            $data['email_encrypted'] = encrypt($data['email']);
            unset($data['email']);
        }

        // Handle phone update
        if (isset($data['phone'])) {
            $data['phone_hash'] = hash('sha256', $data['phone']);
            $data['phone_encrypted'] = encrypt($data['phone']);
            unset($data['phone']);
        }

        $this->userRepository->update($user, $data);

        return $user->fresh();
    }

    /**
     * Delete a user (soft delete).
     *
     * @param int $id
     * @return bool
     * @throws \Exception
     */
    public function deleteUser(int $id): bool
    {
        $user = $this->getUserById($id);
        return $this->userRepository->delete($user);
    }

    /**
     * Restore a soft-deleted user.
     *
     * @param int $id
     * @return User
     * @throws \Exception
     */
    public function restoreUser(int $id): User
    {
        $user = User::withTrashed()->find($id);

        if (!$user) {
            throw new \Exception('User not found', 404);
        }

        $this->userRepository->restore($user);

        return $user->fresh();
    }

    /**
     * Verify user identity.
     *
     * @param int $userId
     * @param int $staffId
     * @param string $method
     * @return User
     * @throws \Exception
     */
    public function verifyIdentity(int $userId, int $staffId, string $method): User
    {
        $user = $this->getUserById($userId);

        $this->userRepository->update($user, [
            'identity_state' => 'verified',
            'identity_verified_at' => now(),
            'identity_verification_method' => $method,
            'identity_verified_by_staff_id' => $staffId,
        ]);

        return $user->fresh();
    }

    /**
     * Update user password.
     *
     * @param int $userId
     * @param string $newPassword
     * @param string|null $currentPassword
     * @return bool
     * @throws \Exception
     */
    public function updatePassword(int $userId, string $newPassword, ?string $currentPassword = null): bool
    {
        $user = $this->getUserById($userId);

        // Verify current password if provided (for self-password change)
        if ($currentPassword && !Hash::check($currentPassword, $user->password_hash)) {
            throw new \Exception('Current password is incorrect', 401);
        }

        return $this->userRepository->update($user, [
            'password_hash' => Hash::make($newPassword),
            'password_changed_at' => now(),
            'requires_password_change' => false,
        ]);
    }

    /**
     * Enable MFA for user.
     *
     * @param int $userId
     * @return array
     * @throws \Exception
     */
    public function enableMfa(int $userId): array
    {
        $user = $this->getUserById($userId);

        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();
        $qrCodeUrl = $google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email_hash,
            $secret
        );

        $this->userRepository->update($user, [
            'mfa_enabled' => true,
            'mfa_secret_encrypted' => encrypt($secret),
        ]);

        return [
            'secret' => $secret,
            'qr_code_url' => $qrCodeUrl,
        ];
    }

    /**
     * Disable MFA for user.
     *
     * @param int $userId
     * @return bool
     * @throws \Exception
     */
    public function disableMfa(int $userId): bool
    {
        $user = $this->getUserById($userId);

        return $this->userRepository->update($user, [
            'mfa_enabled' => false,
            'mfa_secret_encrypted' => null,
        ]);
    }

    /**
     * Validate MFA code.
     *
     * @param int $userId
     * @param string $code
     * @return bool
     * @throws \Exception
     */
    public function validateMfa(int $userId, string $code): bool
    {
        $user = $this->getUserById($userId);

        if (!$user->mfa_enabled || !$user->mfa_secret_encrypted) {
            throw new \Exception('MFA is not enabled for this user', 400);
        }

        $secret = decrypt($user->mfa_secret_encrypted);
        $google2fa = new Google2FA();

        return $google2fa->verifyKey($secret, $code);
    }
}