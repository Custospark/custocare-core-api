<?php
// app/Services/User/AccountRecoveryService.php

declare(strict_types=1);

namespace App\Services\User;

use App\Constants\ActionTypes;
use App\Events\PasswordChanged;
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
    public function sendEmailVerification(int $userId, string $channel = 'email',$action): array
    {
        return DB::transaction(function () use ($userId, $channel,$action) {
            $user = $this->findUserOrFail($userId);
            [$token, $otp, $recoveryToken] = $this->createRecoveryToken(
                $userId,
                'email_verification'
            );

            // Fire event → SendEmailVerificationNotification listener handles delivery
            EmailVerificationRequested::dispatch($user, $token, $otp, $channel,$action);

            Log::info('Email verification token created', [
                'user_id' => $userId,
                'channel' => $channel,
            ]);

            return [
                'token_id'   => $recoveryToken->id,
                'expires_at' => $recoveryToken->expires_at,
                'message'    => 'Authentication code sent successfully.',
            ];
        });
    }

    public function verifyEmail(int $userId, string $code, bool $isToken = false, ?string $ip = null, ?string $userAgent = null): bool
    {
        return DB::transaction(function () use ($userId, $code, $isToken, $ip, $userAgent) {
            $user = $this->findUserOrFail($userId);
            
            // Find valid token - this may throw an exception or return null
            try {
                $validToken = $this->findValidToken($userId, 'email_verification', $code, $isToken);
                
                // If findValidToken returns null instead of throwing
                if (!$validToken) {
                    throw new \Exception('Invalid or expired verification code.', 400);
                }
            } catch (\Exception $e) {
                // Re-throw with a cleaner message or handle as needed
                Log::warning('Token validation failed', [
                    'user_id' => $userId,
                    'error' => $e->getMessage()
                ]);
                throw new \Exception('Invalid or expired verification code.', 400);
            }
            
            // Token is valid - mark it as used regardless of verification status
            $validToken->markAsUsed();
            
            // Now check if email is already verified
            if ($user->hasVerifiedEmail()) {
                Log::info('Email already verified - token consumed', [
                    'user_id' => $userId,
                    'method' => $isToken ? 'token' : 'otp',
                ]);
                
                return true; // Still return true - email IS verified
            }

            // Email not verified yet - verify it now
            $user->markEmailAsVerified();

            Log::info('Email verified', [
                'user_id' => $userId,
                'method'  => $isToken ? 'token' : 'otp',
            ]);

            // ✅ Update last login after successful email verification
            if ($ip && $userAgent) {
                $user->update([
                    'last_login_at' => now(),
                    'last_login_ip' => $ip,
                    'last_login_user_agent' => $userAgent,
                    'failed_login_attempts' => 0, // Reset failed attempts on successful verification
                ]);

                Log::info('Last login updated after email verification', [
                    'user_id' => $userId,
                    'ip' => $ip,
                    'user_agent' => $userAgent
                ]);
            }

            return true;
        });
    }
     public function initiatePasswordReset(string $email, string $channel = 'email'): array
    {
        $emailHash = hash('sha256', strtolower(trim($email)));
        $user      = $this->userRepository->findByEmailHash($emailHash);

        // Silently succeed when the user does not exist (prevents email enumeration)
        if (!$user) {
            Log::info('Password reset requested for unknown email hash');
            return ['message' => 'If that email address is associated with an account, a password reset code has been sent.'];
        }

        return DB::transaction(function () use ($user, $channel) {
            [$token, $otp, $recoveryToken] = $this->createRecoveryToken(
                $user->id,
                'password_reset'
            );

            EmailVerificationRequested::dispatch(
                $user,
                $token,    // the actual password_reset raw token
                $otp,      // the actual password_reset OTP
                $channel,
                ActionTypes::PASSWORD_RESET
            );

            Log::info('Password reset token created', [
                'user_id' => $user->id,
                'channel' => $channel,
            ]);

            return [
                'message'    => 'If that email address is associated with an account, a password reset code has been sent.',
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
    bool $isToken //Note: We are using token at the moment,in the future we can use code(Otp)
): bool {
    return DB::transaction(function () use ($email, $code, $newPassword, $isToken) {
        $emailHash = hash('sha256', strtolower(trim($email)));
        $user      = $this->userRepository->findByEmailHash($emailHash);

        if (!$user) {
            throw new \Exception('Invalid or expired reset code.', 401);
        }

        $validToken = $this->findValidToken($user->id, 'password_reset', $code, true);

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

        // Dispatch PasswordChanged event (not EmailVerificationRequested)
        PasswordChanged::dispatch($user,"email",ActionTypes::PASSWORD_CHANGED);

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
     */private function createRecoveryToken(int $userId, string $type): array
    {
        // Expire any previous unused tokens of the same type
        AccountRecoveryToken::where('user_id', $userId)
            ->where('type', $type)
            ->whereNull('used_at')
            ->delete();

        $token = $this->generateSecureToken(); // 64-char lowercase hex
        $otp   = $this->generateOtp();         // 6-digit code

        // SHA-256 lookup hashes — stored alongside the bcrypt hash for fast indexed queries.
        // The raw token/OTP are NEVER stored; only their hashes are persisted.
        $tokenHashLookup = hash('sha256', strtolower(trim($token)));
        $otpHashLookup   = hash('sha256', trim($otp));

        // Use now() (app timezone) consistently so creation and comparison
        // always operate in the same timezone context.
        $expiresAt = now()->addMinutes(User::TOKEN_EXPIRATION_MINUTES);

        $recoveryToken = AccountRecoveryToken::create([
            'user_id'           => $userId,
            'token_hash'        => Hash::make($token), // bcrypt — for secure fallback verification
            'token_hash_lookup' => $tokenHashLookup,   // SHA-256 — for fast indexed lookup
            'otp_code'          => $otp,               // stored plain for OTP fallback
            'otp_hash_lookup'   => $otpHashLookup,     // SHA-256 — for fast indexed OTP lookup
            'type'              => $type,
            'expires_at'        => $expiresAt,
        ]);

        Log::info('Token created', [
            'token_id'           => $recoveryToken->id,
            'user_id'            => $userId,
            'type'               => $type,
            'expires_at'         => $expiresAt->toDateTimeString(),
            'lookup_hash_prefix' => substr($tokenHashLookup, 0, 8) . '...',
        ]);

        return [$token, $otp, $recoveryToken];
    }

    /**
     * Find a valid (non-expired, non-used) token record.
     *
     * Strategy (token mode):
     *   1. Fast path  — indexed SHA-256 lookup on token_hash_lookup
     *   2. Slow path  — bcrypt check on token_hash (fallback / legacy rows)
     *   3. Both paths require: used_at IS NULL AND expires_at > now
     *
     * Strategy (OTP mode):
     *   1. Fast path  — indexed SHA-256 lookup on otp_hash_lookup
     *   2. Slow path  — direct otp_code comparison (plain-text fallback)
     *
     * @throws \Exception When no valid record is found or token is expired
     */
    private function findValidToken(
        int    $userId,
        string $type,
        string $code,
        bool   $isToken
    ): AccountRecoveryToken {
        // Use now() to match the timezone used during creation
        $now = now();

        if ($isToken) {
            $cleanToken      = strtolower(trim($code));
            $tokenHashLookup = hash('sha256', $cleanToken);

            Log::debug('Token lookup attempt', [
                'user_id'            => $userId,
                'type'               => $type,
                'token_prefix'       => substr($code, 0, 8) . '...',
                'lookup_hash_prefix' => substr($tokenHashLookup, 0, 8) . '...',
            ]);

            // ── Fast path: indexed SHA-256 lookup ─────────────────────────
            $token = AccountRecoveryToken::where('user_id', $userId)
                ->where('type', $type)
                ->where('token_hash_lookup', $tokenHashLookup)
                ->whereNull('used_at')
                ->where('expires_at', '>', $now)
                ->first();

            if ($token) {
                Log::info('Token found via SHA-256 lookup', [
                    'token_id' => $token->id,
                    'user_id'  => $userId,
                ]);
                return $token;
            }

            // ── Check if token exists but is expired (better error message) ──
            $expiredToken = AccountRecoveryToken::where('user_id', $userId)
                ->where('type', $type)
                ->where('token_hash_lookup', $tokenHashLookup)
                ->whereNull('used_at')
                ->first();

            if ($expiredToken) {
                Log::warning('Token found but expired', [
                    'token_id'    => $expiredToken->id,
                    'expires_at'  => $expiredToken->expires_at,
                    'current_now' => $now->toDateTimeString(),
                ]);
                throw new \Exception('Token has expired. Please request a new one.', 401);
            }

            // ── Slow path: bcrypt fallback (covers rows without lookup hash) ──
            Log::info('SHA-256 lookup missed — falling back to bcrypt scan', [
                'user_id' => $userId,
                'type'    => $type,
            ]);

            $candidates = AccountRecoveryToken::where('user_id', $userId)
                ->where('type', $type)
                ->whereNull('used_at')
                ->where('expires_at', '>', $now)
                ->get();

            foreach ($candidates as $candidate) {
                if (Hash::check($cleanToken, $candidate->token_hash)) {
                    // Backfill the lookup hash so future lookups use the fast path
                    $candidate->update(['token_hash_lookup' => $tokenHashLookup]);

                    Log::info('Token found via bcrypt fallback — lookup hash backfilled', [
                        'token_id' => $candidate->id,
                    ]);
                    return $candidate;
                }
            }

            Log::warning('No valid token found', [
                'user_id' => $userId,
                'type'    => $type,
            ]);
            throw new \Exception('Invalid or expired token.', 401);
        }

        // ── OTP validation ────────────────────────────────────────────────
        $cleanOtp      = trim($code);
        $otpHashLookup = hash('sha256', $cleanOtp);

        // Fast path: indexed SHA-256 lookup on otp_hash_lookup
        $token = AccountRecoveryToken::where('user_id', $userId)
            ->where('type', $type)
            ->where('otp_hash_lookup', $otpHashLookup)
            ->whereNull('used_at')
            ->where('expires_at', '>', $now)
            ->first();

        if ($token) {
            return $token;
        }

        // Slow path: plain otp_code comparison (covers legacy rows)
        $token = AccountRecoveryToken::where('user_id', $userId)
            ->where('type', $type)
            ->where('otp_code', $cleanOtp)
            ->whereNull('used_at')
            ->where('expires_at', '>', $now)
            ->first();

        if ($token) {
            // Backfill the lookup hash so future lookups use the fast path
            $token->update(['otp_hash_lookup' => $otpHashLookup]);
            return $token;
        }

        throw new \Exception('Invalid or expired OTP.', 401);
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
