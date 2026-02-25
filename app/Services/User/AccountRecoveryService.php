<?php
// app/Services/User/AccountRecoveryService.php

declare(strict_types=1);

namespace App\Services\User;

use App\Models\AccountRecoveryToken;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AccountRecoveryService
{
    private const TOKEN_LENGTH = 64;
    private const OTP_LENGTH = 6;
    
    /**
     * Create a new service instance.
     *
     * @param UserService $userService
     * @param NotificationService $notificationService
     */
    public function __construct(
        private readonly UserService $userService,
        private readonly NotificationService $notificationService
    ) {}
    
    /**
     * Send email verification token/OTP.
     *
     * @param int $userId
     * @param string $channel
     * @return array
     * @throws \Exception
     */
    public function sendEmailVerification(int $userId, string $channel = 'email'): array
    {
        return DB::transaction(function () use ($userId, $channel) {
            $user = $this->userService->getUserById($userId);
            
            // Check if already verified
            if ($user->hasVerifiedEmail()) {
                throw new \Exception('Email already verified', 400);
            }
            // Generate both token and OTP
            $token = $this->generateSecureToken();
            $otp = $this->generateOTP();
            
            // Delete any existing unused tokens for this type
            AccountRecoveryToken::where('user_id', $userId)
                ->where('type', 'email_verification')
                ->whereNull('used_at')
                ->delete();
            
            // Create new token record
            $recoveryToken = AccountRecoveryToken::create([
                'user_id' => $userId,
                'token_hash' => Hash::make($token),
                'otp_code' => $otp,
                'type' => 'email_verification',
                'channel' => $channel,
                'expires_at' => Carbon::now()->addMinutes(User::TOKEN_EXPIRATION_MINUTES),
            ]);
            
            // Send notification based on channel
            $this->sendVerificationNotification($user, $token, $otp, $channel);
            
            Log::info('Email verification sent', [
                'user_id' => $userId,
                'channel' => $channel,
                'user'=>$user,
            ]);
            
            return [
                'token_id' => $recoveryToken->id,
                'expires_at' => $recoveryToken->expires_at,
                'message' => 'Verification code sent successfully',
            ];
        });
    }
    
    /**
     * Verify email with token or OTP.
     *
     * @param int $userId
     * @param string $code
     * @param bool $isToken
     * @return bool
     * @throws \Exception
     */
    public function verifyEmail(int $userId, string $code, bool $isToken = false): bool
    {
        return DB::transaction(function () use ($userId, $code, $isToken) {
            $user = $this->userService->getUserById($userId);
            
            if ($user->hasVerifiedEmail()) {
                throw new \Exception('Email already verified', 400);
            }
            
            // Find valid token
            $query = AccountRecoveryToken::where('user_id', $userId)
                ->where('type', 'email_verification')
                ->whereNull('used_at')
                ->where('expires_at', '>', Carbon::now());
            
            if ($isToken) {
                // Verify using token (hash check)
                $tokens = $query->get();
                $validToken = null;
                
                foreach ($tokens as $tokenRecord) {
                    if (Hash::check($code, $tokenRecord->token_hash)) {
                        $validToken = $tokenRecord;
                        break;
                    }
                }
                
                if (!$validToken) {
                    throw new \Exception('Invalid or expired token', 401);
                }
            } else {
                // Verify using OTP
                $validToken = $query->where('otp_code', $code)->first();
                
                if (!$validToken) {
                    throw new \Exception('Invalid or expired OTP', 401);
                }
            }
            
            // Mark token as used
            $validToken->markAsUsed();
            
            // Mark email as verified
            $user->markEmailAsVerified();
            
            Log::info('Email verified successfully', [
                'user_id' => $userId,
                'method' => $isToken ? 'token' : 'otp'
            ]);
            
            return true;
        });
    }
    
    /**
     * Initiate password reset.
     *
     * @param string $email
     * @param string $channel
     * @return array
     * @throws \Exception
     */
    public function initiatePasswordReset(string $email, string $channel = 'email'): array
    {
        $emailHash = hash('sha256', strtolower($email));
        $user = $this->userService->findByEmailHash($emailHash);
        
        if (!$user) {
            // Return success even if user not found (security)
            return [
                'message' => 'If the email exists, a reset code has been sent',
            ];
        }
        
        return DB::transaction(function () use ($user, $channel) {
            // Generate token and OTP
            $token = $this->generateSecureToken();
            $otp = $this->generateOTP();
            
            // Delete any existing unused password reset tokens
            AccountRecoveryToken::where('user_id', $user->id)
                ->where('type', 'password_reset')
                ->whereNull('used_at')
                ->delete();
            
            // Create new token
            $recoveryToken = AccountRecoveryToken::create([
                'user_id' => $user->id,
                'token_hash' => Hash::make($token),
                'otp_code' => $otp,
                'type' => 'password_reset',
                'channel' => $channel,
                'expires_at' => Carbon::now()->addMinutes(User::TOKEN_EXPIRATION_MINUTES),
            ]);
            
            // Send password reset notification
            $this->sendPasswordResetNotification($user, $token, $otp, $channel);
            
            Log::info('Password reset initiated', [
                'user_id' => $user->id,
                'channel' => $channel
            ]);
            
            return [
                'token_id' => $recoveryToken->id,
                'expires_at' => $recoveryToken->expires_at,
                'message' => 'Password reset code sent successfully',
            ];
        });
    }
    
    /**
     * Reset password using token/OTP.
     *
     * @param string $email
     * @param string $code
     * @param string $newPassword
     * @param bool $isToken
     * @return bool
     * @throws \Exception
     */
    public function resetPassword(string $email, string $code, string $newPassword, bool $isToken = false): bool
    {
        return DB::transaction(function () use ($email, $code, $newPassword, $isToken) {
            $emailHash = hash('sha256', strtolower($email));
            $user = $this->userService->findByEmailHash($emailHash);
            
            if (!$user) {
                throw new \Exception('Invalid or expired reset code', 401);
            }
            
            // Find valid token
            $query = AccountRecoveryToken::where('user_id', $user->id)
                ->where('type', 'password_reset')
                ->whereNull('used_at')
                ->where('expires_at', '>', Carbon::now());
            
            $validToken = null;
            
            if ($isToken) {
                $tokens = $query->get();
                foreach ($tokens as $tokenRecord) {
                    if (Hash::check($code, $tokenRecord->token_hash)) {
                        $validToken = $tokenRecord;
                        break;
                    }
                }
            } else {
                $validToken = $query->where('otp_code', $code)->first();
            }
            
            if (!$validToken) {
                throw new \Exception('Invalid or expired reset code', 401);
            }
            
            // Update password
            $this->userService->updatePassword($user->id, $newPassword);
            
            // Mark token as used
            $validToken->markAsUsed();
            
            // Delete all existing sessions/tokens
            $user->deleteAllTokens();
            
            // Send password changed notification
            $this->sendPasswordChangedNotification($user);
            
            Log::info('Password reset successful', [
                'user_id' => $user->id
            ]);
            
            return true;
        });
    }
    
    /**
     * Generate secure random token.
     *
     * @return string
     */
    private function generateSecureToken(): string
    {
        return bin2hex(random_bytes(self::TOKEN_LENGTH / 2));
    }
    
    /**
     * Generate numeric OTP.
     *
     * @return string
     */
    private function generateOTP(): string
    {
        return (string) random_int(100000, 999999);
    }
    
    /**
     * Send verification notification.
     *
     * @param User $user
     * @param string $token
     * @param string $otp
     * @param string $channel
     * @return void
     */
    private function sendVerificationNotification(User $user, string $token, string $otp, string $channel): void
    {
        $title = 'Verify Your Email Address';
        $mailBody = $this->buildVerificationEmailBody($user, $token, $otp);
        
        // Decrypt email for notification service
        $email = decrypt($user->email_encrypted);
        
        // Use notification service
        $this->notificationService->sendNotification(
            $title,
            $mailBody,
            'email_verification',
            $channel === 'both' ? 'both' : 'email',
            $user->id
        );

    }
    
    /**
     * Send password reset notification.
     *
     * @param User $user
     * @param string $token
     * @param string $otp
     * @param string $channel
     * @return void
     */
    private function sendPasswordResetNotification(User $user, string $token, string $otp, string $channel): void
    {
        $title = 'Reset Your Password';
        $mailBody = $this->buildPasswordResetEmailBody($user, $token, $otp);
        
        $this->notificationService->sendNotification(
            $title,
            $mailBody,
            'password_reset',
            $channel === 'both' ? 'both' : 'email',
            $user->id
        );
        
        Log::info('Password reset codes', [
            'user_id' => $user->id,
            'token' => $token,
            'otp' => $otp
        ]);
    }
    
    /**
     * Send password changed notification.
     *
     * @param User $user
     * @return void
     */
    private function sendPasswordChangedNotification(User $user): void
    {
        $title = 'Password Changed Successfully';
        $mailBody = $this->buildPasswordChangedEmailBody($user);
        
        $this->notificationService->sendNotification(
            $title,
            $mailBody,
            'security_alert',
            'email',
            $user->id
        );
    }
    
    /**
     * Build verification email body.
     *
     * @param User $user
     * @param string $token
     * @param string $otp
     * @return string
     */
    private function buildVerificationEmailBody(User $user, string $token, string $otp): string
    {
        $firstName = $user->first_name ?? 'User';
        $verificationLink = config('app.frontend_url') . "/verify-email?token={$token}";
        
        return "
            <h2>Hello {$firstName},</h2>
            <p>Thank you for registering. Please verify your email address using one of the methods below:</p>
            
            <h3>Option 1: Click the verification link</h3>
            <p><a href='{$verificationLink}'>Verify Email Address</a></p>
            <p>Or copy this link: {$verificationLink}</p>
            
            <h3>Option 2: Use OTP code</h3>
            <p><strong>OTP Code: {$otp}</strong></p>
            <p>This code will expire in " . User::TOKEN_EXPIRATION_MINUTES . " minutes.</p>
            
            <p>If you didn't create an account, please ignore this email.</p>
            
            <p>Regards,<br>" . config('app.name') . " Team</p>
        ";
    }
    
    /**
     * Build password reset email body.
     *
     * @param User $user
     * @param string $token
     * @param string $otp
     * @return string
     */
    private function buildPasswordResetEmailBody(User $user, string $token, string $otp): string
    {
        $firstName = $user->first_name ?? 'User';
        $resetLink = config('app.frontend_url') . "/reset-password?token={$token}";
        
        return "
            <h2>Hello {$firstName},</h2>
            <p>We received a request to reset your password. Use one of the methods below:</p>
            
            <h3>Option 1: Click the reset link</h3>
            <p><a href='{$resetLink}'>Reset Password</a></p>
            <p>Or copy this link: {$resetLink}</p>
            
            <h3>Option 2: Use OTP code</h3>
            <p><strong>OTP Code: {$otp}</strong></p>
            <p>This code will expire in " . User::TOKEN_EXPIRATION_MINUTES . " minutes.</p>
            
            <p>If you didn't request this, please ignore this email or contact support.</p>
            
            <p>Regards,<br>" . config('app.name') . " Team</p>
        ";
    }
    
    /**
     * Build password changed email body.
     *
     * @param User $user
     * @return string
     */
    private function buildPasswordChangedEmailBody(User $user): string
    {
        $firstName = $user->first_name ?? 'User';
        
        return "
            <h2>Hello {$firstName},</h2>
            <p>Your password has been successfully changed.</p>
            <p>If you didn't make this change, please contact support immediately.</p>
            
            <p>Regards,<br>" . config('app.name') . " Team</p>
        ";
    }
}