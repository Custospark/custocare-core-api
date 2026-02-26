<?php
// app/Services/User/AccountRecoveryService.php

declare(strict_types=1);

namespace App\Services\User;

use App\Events\PasswordChanged;
use App\Events\PasswordResetRequested;
use App\Events\EmailVerificationRequested;
use App\Models\AccountRecoveryToken;
use App\Models\User;
use App\Repositories\User\Contracts\UserRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Manages account-recovery flows: email verification and password reset.
 *
 * ─── Circular-dependency note ────────────────────────────────────────────────
 * The original code injected UserService here, which itself injected
 * AccountRecoveryService → circular dependency.
 *
 * Solution: this service injects UserRepositoryInterface DIRECTLY.
 * It never references UserService, eliminating the cycle entirely.
 *
 * Notifications are fired as Events and handled by dedicated Listeners,
 * so there is no NotificationService dependency here either.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class AccountRecoveryService
{
    /** Byte-length of the random token (hex output = TOKEN_BYTES * 2 chars). */
    private const TOKEN_BYTES = 32; // → 64-char hex string

    /**
     * @param UserRepositoryInterface $userRepository  Direct repo; avoids UserService cycle
     */
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Email Verification
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Create an email-verification token/OTP pair and fire the notification event.
     *
     * @param  int    $userId
     * @param  string $channel  'email' | 'sms' | 'both'
     * @return array{token_id: int, expires_at: Carbon, message: string}
     * @throws \Exception If the user is not found or is already verified
     */
    public function sendEmailVerification(int $userId, string $channel = 'email'): array
    {
        return DB::transaction(function () use ($userId, $channel) {
            $user = $this->findUserOrFail($userId);

            if ($user->hasVerifiedEmail()) {
                throw new \Exception('Email is already verified.', 400);
            }

            [$token, $otp, $recoveryToken] = $this->createRecoveryToken(
                $userId,
                'email_verification'
            );

            // Fire event → SendEmailVerificationNotification listener handles delivery
            EmailVerificationRequested::dispatch($user, $token, $otp, $channel);

            Log::info('Email verification token created', [
                'user_id' => $userId,
                'channel' => $channel,
            ]);

            return [
                'token_id'   => $recoveryToken->id,
                'expires_at' => $recoveryToken->expires_at,
                'message'    => 'Verification code sent successfully.',
            ];
        });
    }

    /**
     * Verify a user's email using either a raw token (link) or an OTP code.
     *
     * @param  int    $userId
     * @param  string $code      Raw token string OR 6-digit OTP (always string)
     * @param  bool   $isToken   true → hash comparison; false → OTP comparison
     * @throws \Exception On invalid / expired code, or already-verified state
     */
    public function verifyEmail(int $userId, string $code, bool $isToken = false): bool
    {
        return DB::transaction(function () use ($userId, $code, $isToken) {
            $user = $this->findUserOrFail($userId);

            if ($user->hasVerifiedEmail()) {
                throw new \Exception('Email is already verified.', 400);
            }

            $validToken = $this->findValidToken($userId, 'email_verification', $code, $isToken);

            // Mark token as used and email as verified
            $validToken->markAsUsed();
            $user->markEmailAsVerified();

            Log::info('Email verified', [
                'user_id' => $userId,
                'method'  => $isToken ? 'token' : 'otp',
            ]);

            return true;
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Password Reset
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Initiate a password-reset flow.
     * Returns a neutral response if the email is not found (prevents enumeration).
     *
     * @param  string $email
     * @param  string $channel
     * @return array{message: string, expires_at?: Carbon}
     */
    public function initiatePasswordReset(string $email, string $channel = 'email'): array
    {
        $emailHash = hash('sha256', strtolower(trim($email)));
        $user      = $this->userRepository->findByEmailHash($emailHash);

        // Silently succeed when the user does not exist (prevents email enumeration)
        if (!$user) {
            Log::info('Password reset requested for unknown email hash');

            return ['message' => 'If that email address is registered, a reset code has been sent.'];
        }

        return DB::transaction(function () use ($user, $channel) {
            [$token, $otp, $recoveryToken] = $this->createRecoveryToken(
                $user->id,
                'password_reset'
            );

            // Fire event → SendPasswordResetNotification listener handles delivery
            PasswordResetRequested::dispatch($user, $token, $otp, $channel);

            Log::info('Password reset token created', [
                'user_id' => $user->id,
                'channel' => $channel,
            ]);

            return [
                'message'    => 'Password reset code sent successfully.',
                'expires_at' => $recoveryToken->expires_at,
            ];
        });
    }

    /**
     * Complete the password-reset flow.
     *
     * @param  string $email
     * @param  string $code        Raw token or 6-digit OTP
     * @param  string $newPassword Plain-text new password (hashed inside UserService)
     * @param  bool   $isToken
     * @throws \Exception On invalid / expired code or user not found
     */
    public function resetPassword(
        string $email,
        string $code,
        string $newPassword,
        bool $isToken = false
    ): bool {
        return DB::transaction(function () use ($email, $code, $newPassword, $isToken) {
            $emailHash = hash('sha256', strtolower(trim($email)));
            $user      = $this->userRepository->findByEmailHash($emailHash);

            if (!$user) {
                throw new \Exception('Invalid or expired reset code.', 401);
            }

            $validToken = $this->findValidToken($user->id, 'password_reset', $code, $isToken);

            // ── Update password directly via repository (avoids UserService dep) ──
            $this->userRepository->update($user, [
                'password_hash'            => Hash::make($newPassword),
                'password_changed_at'      => now(),
                'requires_password_change' => false,
            ]);

            // Mark token as used
            $validToken->markAsUsed();

            // Revoke all active sessions so the compromised session is invalidated
            $user->deleteAllTokens();

            // Fire event → SendPasswordChangedNotification listener handles alert
            PasswordChanged::dispatch($user->fresh());

            Log::info('Password reset completed', ['user_id' => $user->id]);

            return true;
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Resolve a user by ID or throw a descriptive exception.
     *
     * @throws \Exception
     */
    private function findUserOrFail(int $userId): User
    {
        $user = $this->userRepository->findById($userId);

        if (!$user) {
            throw new \Exception('User not found.', 404);
        }

        return $user;
    }

    /**
     * Invalidate existing unused tokens of a given type, then create a fresh pair.
     *
     * @return array{string, string, AccountRecoveryToken}
     *         [raw token, otp, Eloquent model]
     */
    private function createRecoveryToken(int $userId, string $type): array
    {
        // Expire any previous unused tokens of the same type
        AccountRecoveryToken::where('user_id', $userId)
            ->where('type', $type)
            ->whereNull('used_at')
            ->delete();

        $token = $this->generateSecureToken();
        $otp   = $this->generateOtp();

        $recoveryToken = AccountRecoveryToken::create([
            'user_id'    => $userId,
            'token_hash' => Hash::make($token),
            'otp_code'   => $otp,
            'type'       => $type,
            'expires_at' => Carbon::now()->addMinutes(User::TOKEN_EXPIRATION_MINUTES),
        ]);

        return [$token, $otp, $recoveryToken];
    }

    /**
     * Find a valid (non-expired, non-used) token record.
     * Performs constant-time hash comparison for token mode to prevent timing attacks.
     *
     * @throws \Exception When no valid record is found
     */
    private function findValidToken(
        int    $userId,
        string $type,
        string $code,
        bool   $isToken
    ): AccountRecoveryToken {
        $baseQuery = AccountRecoveryToken::where('user_id', $userId)
            ->where('type', $type)
            ->whereNull('used_at')
            ->where('expires_at', '>', Carbon::now());

        if ($isToken) {
            // Load all candidates and find the one whose hash matches
            $validToken = null;

            foreach ($baseQuery->get() as $record) {
                if (Hash::check($code, $record->token_hash)) {
                    $validToken = $record;
                    break;
                }
            }

            if (!$validToken) {
                throw new \Exception('Invalid or expired token.', 401);
            }

            return $validToken;
        }

        // OTP: direct equality check (OTPs are short-lived numeric strings)
        $validToken = $baseQuery->where('otp_code', $code)->first();

        if (!$validToken) {
            throw new \Exception('Invalid or expired OTP code.', 401);
        }

        return $validToken;
    }

    /**
     * Generate a cryptographically secure random hex token.
     */
    private function generateSecureToken(): string
    {
        return bin2hex(random_bytes(self::TOKEN_BYTES));
    }

    /**
     * Generate a cryptographically secure 6-digit OTP.
     * Uses the range 100000–999999 so no leading zeros are possible.
     */
    private function generateOtp(): string
    {
        return (string) random_int(100000, 999999);
    }
}
