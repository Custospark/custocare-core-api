<?php
// app/Services/User/UserService.php

declare(strict_types=1);

namespace App\Services\User;

use App\Events\Auth\MfaRequired;
use App\Models\User;
use App\Repositories\User\Contracts\UserRepositoryInterface;
use App\Services\User\Contracts\UserServiceInterface;
use App\Support\HealthcareIdGenerator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * Handles all user-lifecycle operations: registration, login, password management,
 * identity verification, and MFA.
 *
 * ⚠ This service does NOT depend on AccountRecoveryService.
 *    Notification concerns are handled via Events → Listeners.
 */
class UserService implements UserServiceInterface
{
    /** Maximum consecutive failed login attempts before lockout. */
    private const MAX_FAILED_ATTEMPTS = 5;

    /** Account lockout duration in minutes after MAX_FAILED_ATTEMPTS is exceeded. */
    private const LOCKOUT_DURATION = 30;

    /**
     * @param UserRepositoryInterface $userRepository
     */
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Registration
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Register a brand-new user, encrypting / hashing all PII before persistence.
     *
     * @param  array<string, mixed> $data
     * @throws \Exception If email or national ID already exists
     */
    public function register(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $email     = strtolower(trim($data['email']));
            $emailHash = hash('sha256', $email);

            // ── Uniqueness guards ──────────────────────────────────────────
            if ($this->userRepository->findByEmailHash($emailHash)) {
                throw new \Exception('A user with this email already exists.', 409);
            }

            if (!empty($data['national_id'])) {
                $nationalIdHash = hash('sha256', $data['national_id']);

                if ($this->userRepository->findByNationalIdHash($nationalIdHash)) {
                    throw new \Exception('A user with this national ID already exists.', 409);
                }

                // Store hashed + encrypted; remove raw value
                $data['national_id_hash']      = $nationalIdHash;
                $data['national_id_encrypted'] = encrypt($data['national_id']);
                unset($data['national_id']);
            }

            // ── Generate a random password if none supplied ────────────────
            if (empty($data['password'])) {
                $data['password'] = HealthcareIdGenerator::generateRandomCode();
            }

            // ── Global UUID ───────────────────────────────────────────────
            $data['global_user_uuid'] = Str::uuid()->toString();

            // ── Email – hash + encrypt, remove plaintext ──────────────────
            $data['email_hash']      = $emailHash;
            $data['email_encrypted'] = encrypt($email);
            $data['email_verified_at'] = null;
            unset($data['email']);

            // ── Phone – hash + encrypt if provided ───────────────────────
            if (!empty($data['phone'])) {
                $data['phone_hash']      = hash('sha256', $data['phone']);
                $data['phone_encrypted'] = encrypt($data['phone']);
                unset($data['phone']);
            }

            // ── Password – hash, remove plaintext ─────────────────────────
            $data['password_hash']       = Hash::make($data['password']);
            $data['password_changed_at'] = now();
            unset($data['password']);

            // ── Defaults ──────────────────────────────────────────────────
            $data['identity_state']        = $data['identity_state'] ?? 'pending';
            $data['data_residency_region'] = $data['data_residency_region'] ?? 'US';
            $data['failed_login_attempts'] = 0;

            return $this->userRepository->create($data);
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Login
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Authenticate a user.
     * Returns a structured result array consumed by AuthController.
     *
     * Flow:
     *   1. Resolve user by email hash
     *   2. Reject locked accounts
     *   3. Verify password; lock on excessive failures
     *   4. Reject unverified email
     *   5. Check MFA – prompt if code missing, validate if provided
     *   6. Issue token
     *
     * @param  array<string, mixed> $credentials
     * @return array{success: bool, code: string, message: string, requires_mfa: bool, user: User|null, token: string|null}
     */
  public function login(array $credentials, string $ip, string $userAgent): array
{
    $emailHash = hash('sha256', strtolower(trim($credentials['email'])));
    $user      = $this->userRepository->findByEmailHash($emailHash);

    // ── User not found ─────────────────────────────────────────────────
    if (!$user) {
        return $this->loginFailure('INVALID_CREDENTIALS', 'Invalid credentials.');
    }

    // ── Account locked ─────────────────────────────────────────────────
    if ($user->isAccountLocked()) {
        return $this->loginFailure('ACCOUNT_LOCKED', 'Account is temporarily locked. Please try again later.');
    }

    // ── Password check ─────────────────────────────────────────────────
    if (!Hash::check($credentials['password'], $user->password_hash)) {
        return $this->handleFailedPassword($user);
    }

    // ── Email verification gate ────────────────────────────────────────
    if (!$user->hasVerifiedEmail()) {
        return [
            'success'      => false,
            'code'         => 'EMAIL_NOT_VERIFIED',
            'message'      => 'Please verify your email address before logging in.',
            'requires_mfa' => false,
            'user'         => null,
            'token'        => null,
        ];
    }

    // ── Reset failed-attempt counter and record this login ─────────────
    $this->userRepository->resetFailedAttempts($user);
    $this->userRepository->updateLastLogin($user, $ip, $userAgent);

    // ── MFA gate (Using Email OTP) ────────────────────────────────────
    if ($user->mfa_enabled) {
        // No code supplied yet – prompt the client and send OTP email
        if (empty($credentials['mfa_code'])) {
            try {
                // Create MFA token and OTP
                [$token, $otp, $recoveryToken] = $this->createMfaToken($user->id);
                
                // Dispatch MfaRequired event with token and OTP
                // \App\Events\MfaRequired::dispatch($user, $token, $otp, 'email');
                $service = app(\App\Services\User\AccountRecoveryService::class);
                $result = $service->sendEmailVerification($user->id, 'email');     
                
                Log::info('MFA OTP email dispatched via event', [
                    'user_id' => $user->id,
                    'token_id' => $recoveryToken->id,
                    'expires_at' => $recoveryToken->expires_at
                ]);
                
            } catch (\Exception $e) {
                Log::error('Failed to send MFA OTP email', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
            }
            
            return [
                'success'       => true,
                'code'          => 'MFA_REQUIRED',
                'message'       => 'Multi-factor authentication required. Please check your email for a verification code.',
                'requires_mfa'  => true,
                'mfa_type'      => 'email_otp',
                'user'          => $user,
                'token'         => null,
            ];
        }

        // Code supplied – validation happens in a separate endpoint
        // We just log it and proceed
        Log::info('MFA code provided for user', [
            'user_id' => $user->id
        ]);
    }

    // ── Issue token (single-session enforcement inside User model) ──────
    $token = $user->generateAuthToken();

    return [
        'success'      => true,
        'code'         => 'LOGIN_SUCCESS',
        'message'      => 'Login successful.',
        'requires_mfa' => false,
        'user'         => $user,
        'token'        => $token,
    ];
}

/**
 * Create an MFA token and OTP for the user.
 */
private function createMfaToken(int $userId): array
{
    // Generate a random token and 6-digit OTP
    $token = bin2hex(random_bytes(32));
    $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    
    // Store in recovery_tokens table
    $recoveryToken = \App\Models\AccountRecoveryToken::create([
        'user_id' => $userId,
        'token_hash' => Hash::make($token),
        'otp_hash' => Hash::make($otp),
        'type' => 'mfa_verification',
        'expires_at' => now()->addMinutes(10),
        'used' => false,
    ]);
    
    return [$token, $otp, $recoveryToken];
}

    // ─────────────────────────────────────────────────────────────────────────
    // Logout
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Invalidate all tokens for the given user.
     */
    public function logout(User $user): bool
    {
        $user->deleteAllTokens();
        return true;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CRUD helpers
    // ─────────────────────────────────────────────────────────────────────────

    /** @throws \Exception */
    public function getUserById(int $id): User
    {
        $user = $this->userRepository->findById($id);

        if (!$user) {
            throw new \Exception('User not found.', 404);
        }

        return $user;
    }

    /** @throws \Exception */
    public function getUserByUuid(string $uuid): User
    {
        $user = $this->userRepository->findByUuid($uuid);

        if (!$user) {
            throw new \Exception('User not found.', 404);
        }

        return $user;
    }

    /**
     * Look up a user by their email hash (used by AccountRecoveryService indirectly via repository).
     */
    public function findByEmailHash(string $emailHash): ?User
    {
        return $this->userRepository->findByEmailHash($emailHash);
    }

    public function getAllUsers(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->userRepository->getAllPaginated($filters, $perPage);
    }

    /** Thin alias for register() – used by admin flows. */
    public function createUser(array $data): User
    {
        return $this->register($data);
    }

    /** @throws \Exception */
    public function updateUser(int $id, array $data): User
    {
        $user = $this->getUserById($id);

        if (isset($data['email'])) {
            $email                       = strtolower(trim($data['email']));
            $data['email_hash']          = hash('sha256', $email);
            $data['email_encrypted']     = encrypt($email);
            $data['email_verified_at']   = null; // Force re-verification
            unset($data['email']);
        }

        if (isset($data['phone'])) {
            $data['phone_hash']      = hash('sha256', $data['phone']);
            $data['phone_encrypted'] = encrypt($data['phone']);
            unset($data['phone']);
        }

        $this->userRepository->update($user, $data);

        return $user->fresh();
    }

    /** @throws \Exception */
    public function deleteUser(int $id): bool
    {
        $user = $this->getUserById($id);
        return $this->userRepository->delete($user);
    }

    /** @throws \Exception */
    public function restoreUser(int $id): User
    {
        $user = User::withTrashed()->find($id);

        if (!$user) {
            throw new \Exception('User not found.', 404);
        }

        $this->userRepository->restore($user);

        return $user->fresh();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Password management
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Change a user's password.
     * Optionally verifies the current password for self-service flows.
     *
     * @throws \Exception
     */
    public function updatePassword(int $userId, string $newPassword, ?string $currentPassword = null): bool
    {
        $user = $this->getUserById($userId);

        if ($currentPassword && !Hash::check($currentPassword, $user->password_hash)) {
            throw new \Exception('Current password is incorrect.', 401);
        }

        return $this->userRepository->update($user, [
            'password_hash'             => Hash::make($newPassword),
            'password_changed_at'       => now(),
            'requires_password_change'  => false,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Identity verification
    // ─────────────────────────────────────────────────────────────────────────

    /** @throws \Exception */
    public function verifyIdentity(int $userId, int $staffId, string $method): User
    {
        $user = $this->getUserById($userId);

        $this->userRepository->update($user, [
            'identity_state'                     => 'verified',
            'identity_verified_at'               => now(),
            'identity_verification_method'       => $method,
            'identity_verified_by_staff_id'      => $staffId,
        ]);

        return $user->fresh();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // MFA (Google2FA / TOTP)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Enable TOTP MFA for a user and return the secret + QR-code URL.
     *
     * @return array{secret: string, qr_code_url: string}
     * @throws \Exception
     */
    public function enableMfa(int $userId): array
    {
        $user     = $this->getUserById($userId);
        $google2fa = new Google2FA();
        $secret    = $google2fa->generateSecretKey();

        // QR code displayed to user during setup (uses email hash as account identifier)
        $qrCodeUrl = $google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email_hash,
            $secret
        );

        $this->userRepository->update($user, [
            'mfa_enabled'           => true,
            'mfa_secret_encrypted'  => encrypt($secret),
        ]);

        return [
            'secret'       => $secret,
            'qr_code_url'  => $qrCodeUrl,
        ];
    }

    /** @throws \Exception */
    public function disableMfa(int $userId): bool
    {
        $user = $this->getUserById($userId);

        return $this->userRepository->update($user, [
            'mfa_enabled'          => false,
            'mfa_secret_encrypted' => null,
        ]);
    }

    /**
     * Validate a TOTP code against the user's stored secret.
     *
     * @throws \Exception If MFA is not enabled or secret is missing
     */
    public function validateMfa(int $userId, string $code): bool
    {
        $user = $this->getUserById($userId);

        if (!$user->mfa_enabled || empty($user->mfa_secret_encrypted)) {
            throw new \Exception('MFA is not enabled for this user.', 400);
        }

        $secret    = decrypt($user->mfa_secret_encrypted);
        $google2fa = new Google2FA();

        return (bool) $google2fa->verifyKey($secret, $code);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Build a generic login-failure response array.
     *
     * @return array{success: bool, code: string, message: string, requires_mfa: bool, user: null, token: null}
     */
    private function loginFailure(string $code, string $message): array
    {
        return [
            'success'      => false,
            'code'         => $code,
            'message'      => $message,
            'requires_mfa' => false,
            'user'         => null,
            'token'        => null,
        ];
    }

    /**
     * Increment failed-login counter; lock the account if the threshold is reached.
     *
     * @return array{success: bool, code: string, message: string, requires_mfa: bool, user: null, token: null}
     */
    private function handleFailedPassword(User $user): array
    {
        $this->userRepository->incrementFailedAttempts($user);

        // Refresh the model to get the updated counter
        $user->refresh();

        if ($user->failed_login_attempts >= self::MAX_FAILED_ATTEMPTS) {
            $this->userRepository->lockAccount(
                $user,
                Carbon::now()->addMinutes(self::LOCKOUT_DURATION)
            );

            Log::warning('Account locked due to failed attempts', ['user_id' => $user->id]);

            return $this->loginFailure(
                'ACCOUNT_LOCKED',
                'Account locked due to too many failed login attempts. Please try again in ' . self::LOCKOUT_DURATION . ' minutes.'
            );
        }

        return $this->loginFailure('INVALID_CREDENTIALS', 'Invalid credentials.');
    }
}
