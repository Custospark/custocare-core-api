<?php
// app/Listeners/Auth/SendEmailVerificationNotification.php

declare(strict_types=1);

namespace App\Listeners;

use App\Constants\ActionTypes;
use App\Events\EmailVerificationRequested;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Log;

/**
 * Listens for EmailVerificationRequested and dispatches
 * the verification email / SMS to the user.
 *
 * Implements ShouldHandleEventsAfterCommit so the notification is
 * only sent once the DB transaction that fired the event has committed.
 */
class SendEmailVerificationNotification implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private readonly NotificationService $notificationService
    ) {}

   public function handle(EmailVerificationRequested $event): void
{
    $user    = $event->user;
    $token   = $event->token;
    $otp     = $event->otp;
    $channel = $event->channel;
    $action  = $event->action;

    $messageData = $this->buildMessage($user, $token, $otp, $action);

    $deliveryChannel = ($channel === 'both') ? 'both' : 'email';

    $this->notificationService->sendToUser(
        user:    $user,
        title:   $messageData['title'],
        body:    $messageData['body'],
        type:    $this->mapActionToType($action),
        channel: $deliveryChannel
    );
}

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Map action to notification type
     */
    private function mapActionToType(string $action): string
    {
        return match($action) {
            'password_reset' => 'password_reset',
            'login_confirmation' => 'login_confirmation',
            default => 'email_verification'
        };
    }

    /**
     * Build message based on action type
     */
   
       private function buildMessage(User $user, string $token, string $otp, string $action): array
{
    return match($action) {
        ActionTypes::PASSWORD_RESET => $this->buildPasswordResetMessage($user, $token, $otp),
        ActionTypes::LOGIN_CONFIRMATION => $this->buildLoginConfirmationMessage($user, $otp),
        ActionTypes::ACCOUNT_CREATION => $this->buildAccountCreationMessage($user, $otp),
        default => throw new \InvalidArgumentException("Unknown action type: {$action}")
    };
}

    /**
     * Build message for account creation - ONLY verification code, no link
     */
    private function buildAccountCreationMessage(User $user, string $otp): array
{
    $firstName     = $user->first_name ?? 'User';
    $expiryMinutes = User::TOKEN_EXPIRATION_MINUTES;
    $appName       = config('app.name');

    $title = "{$appName} — Email Verification Required";

    $body = "
        <h2>Dear {$firstName},</h2>

        <p>
            Thank you for registering with {$appName}.
            To activate your account, please verify your email address using the code below.
        </p>

        <div style='background:#f4f6f8; padding:20px; border-radius:6px; text-align:center; margin:20px 0;'>
            <p style='margin:0; font-size:13px; color:#6b7280;'>Verification Code</p>
            <p style='font-size:28px; font-weight:600; letter-spacing:4px; margin:10px 0;'>
                {$otp}
            </p>
            <p style='margin:0; font-size:12px; color:#6b7280;'>
                This code expires in {$expiryMinutes} minutes.
            </p>
        </div>

        <p>
            Enter this code in the verification screen to complete your registration.
        </p>

        <p style='font-size:12px; color:#6b7280; margin-top:30px;'>
            If you did not initiate this request, no further action is required.
        </p>

        <p>
            Custocare AI Security Team
        </p>
    ";

    return ['title' => $title, 'body' => $body];
}

    /**
     * Build message for login confirmation - ONLY verification code, no link
     */
   private function buildLoginConfirmationMessage(User $user, string $otp): array
{
    $firstName     = $user->first_name ?? 'User';
    $expiryMinutes = User::TOKEN_EXPIRATION_MINUTES;
    $appName       = config('app.name');
    $currentTime   = now()->format('F j, Y \a\t g:i A');

    $title = "{$appName} — Login Authentication Code";

    $body = "
        <h2>Dear {$firstName},</h2>

        <p>
            A login attempt was initiated for your {$appName} account.
        </p>

        <p>
            <strong>Request Time:</strong> {$currentTime}
        </p>

        <div style='background:#eef2f7; padding:20px; border-radius:6px; text-align:center; margin:20px 0;'>
            <p style='margin:0; font-size:13px; color:#6b7280;'>Authentication Code</p>
            <p style='font-size:28px; font-weight:600; letter-spacing:4px; margin:10px 0;'>
                {$otp}
            </p>
            <p style='margin:0; font-size:12px; color:#6b7280;'>
                This code expires in {$expiryMinutes} minutes.
            </p>
        </div>

        <p>
            Enter this code to complete authentication.
        </p>

        <p style='font-size:12px; color:#b91c1c; margin-top:30px;'>
            If you did not attempt to sign in, please secure your account immediately.
            Do not share this code with any individual or third party.
        </p>

        <p>
            Custocare AI Security Team
        </p>
    ";

    return ['title' => $title, 'body' => $body];
}

    /**
     * Build message for password reset - INCLUDES link AND verification code
     */
 private function buildPasswordResetMessage(User $user, string $token, string $otp): array
{
    $firstName     = $user->first_name ?? 'User';
    $expiryMinutes = User::TOKEN_EXPIRATION_MINUTES;
    $appName       = config('app.name');
    $resetLink     = rtrim(config('app.frontend_url'), '/') . "/reset-password?token={$token}";

    $title = "{$appName} — Password Reset Request";

    $body = "
        <h2>Dear {$firstName},</h2>

        <p>
            A request has been received to reset your {$appName} account password.
        </p>

        <h3>Option 1 — Secure Reset Link</h3>
        <p style='text-align:center; margin:20px 0;'>
            <a href='{$resetLink}' 
               style='background:#1f2937; color:white; padding:12px 28px; 
               text-decoration:none; border-radius:4px; font-weight:500;'>
               Reset Password
            </a>
        </p>

        <p style='font-size:12px; word-break:break-all; text-align:center; color:#6b7280;'>
            {$resetLink}
        </p>

        <h3>Option 2 — One-Time Code</h3>

        <div style='background:#f4f6f8; padding:20px; border-radius:6px; text-align:center; margin:20px 0;'>
            <p style='margin:0; font-size:13px; color:#6b7280;'>Password Reset Code</p>
            <p style='font-size:24px; font-weight:600; letter-spacing:4px; margin:10px 0;'>
                {$otp}
            </p>
            <p style='margin:0; font-size:12px; color:#6b7280;'>
                This code expires in {$expiryMinutes} minutes.
            </p>
        </div>

        <p style='font-size:12px; color:#6b7280; margin-top:30px;'>
            If you did not request a password reset, no further action is required.
            Never share your authentication credentials.
        </p>

        <p>
            Custocare AI Security Team
        </p>
    ";

    return ['title' => $title, 'body' => $body];
}
}