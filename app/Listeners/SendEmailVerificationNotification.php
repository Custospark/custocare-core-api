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

    $title = "{$appName} — Complete Your Registration";

    $body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        </head>
        <body style='font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; line-height: 1.5; color: #1f2937; margin: 0; padding: 0;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2 style='font-weight: 500; margin-bottom: 24px;'>Account Registration</h2>
                
                <p style='margin-bottom: 20px;'>Dear {$firstName},</p>
                
                <p style='margin-bottom: 20px;'>
                    Thank you for registering with {$appName}. To activate your account, 
                    please verify your email address using the authentication code below.
                </p>

                <div style='background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 24px; text-align: center; margin: 24px 0;'>
                    <p style='margin: 0 0 8px 0; font-size: 14px; color: #6b7280;'>Authentication Code</p>
                    <p style='font-size: 32px; font-weight: 600; letter-spacing: 4px; margin: 8px 0; color: #1f2937;'>{$otp}</p>
                    <p style='margin: 8px 0 0 0; font-size: 13px; color: #6b7280;'>
                        This code expires in {$expiryMinutes} minutes
                    </p>
                </div>

                <p style='margin-bottom: 24px;'>
                    Enter this code in the verification screen to complete your registration.
                </p>

                <div style='border-top: 1px solid #e5e7eb; margin: 32px 0 24px 0;'></div>

                <p style='font-size: 13px; color: #6b7280; margin-bottom: 8px;'>
                    If you did not initiate this request, no further action is required.
                </p>

                <p style='font-size: 13px; color: #6b7280; margin-bottom: 4px;'>
                    Regards,
                </p>
                
                <p style='font-size: 13px; color: #6b7280; margin-top: 0;'>
                    {$appName} Security Team
                </p>
            </div>
        </body>
        </html>
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

    $title = "{$appName} — Authentication Code";

    $body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        </head>
        <body style='font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; line-height: 1.5; color: #1f2937; margin: 0; padding: 0;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2 style='font-weight: 500; margin-bottom: 24px;'>Authentication Required</h2>
                
                <p style='margin-bottom: 20px;'>Dear {$firstName},</p>
                
                <p style='margin-bottom: 20px;'>
                    A sign-in attempt was initiated for your {$appName} account.
                </p>

                <div style='background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; margin: 24px 0;'>
                    <p style='margin: 0; font-size: 14px;'><strong>Request Time:</strong> {$currentTime}</p>
                </div>

                <div style='background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 24px; text-align: center; margin: 24px 0;'>
                    <p style='margin: 0 0 8px 0; font-size: 14px; color: #6b7280;'>Authentication Code</p>
                    <p style='font-size: 32px; font-weight: 600; letter-spacing: 4px; margin: 8px 0; color: #1f2937;'>{$otp}</p>
                    <p style='margin: 8px 0 0 0; font-size: 13px; color: #6b7280;'>
                        This code expires in {$expiryMinutes} minutes
                    </p>
                </div>

                <p style='margin-bottom: 24px;'>
                    Enter this code to complete authentication and access your account.
                </p>

                <div style='background-color: #fef2f2; border: 1px solid #fee2e2; border-radius: 6px; padding: 16px; margin: 24px 0;'>
                    <p style='margin: 0; font-size: 13px; color: #b91c1c;'>
                        <strong>Security Notice:</strong> If you did not attempt to sign in, 
                        please secure your account immediately. Never share this code with anyone.
                    </p>
                </div>

                <div style='border-top: 1px solid #e5e7eb; margin: 32px 0 24px 0;'></div>

                <p style='font-size: 13px; color: #6b7280; margin-bottom: 4px;'>
                    Regards,
                </p>
                
                <p style='font-size: 13px; color: #6b7280; margin-top: 0;'>
                    {$appName} Security Team
                </p>
            </div>
        </body>
        </html>
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
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        </head>
        <body style='font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; line-height: 1.5; color: #1f2937; margin: 0; padding: 0;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2 style='font-weight: 500; margin-bottom: 24px;'>Password Reset Request</h2>
                
                <p style='margin-bottom: 20px;'>Dear {$firstName},</p>
                
                <p style='margin-bottom: 24px;'>
                    A request has been received to reset your {$appName} account password. 
                    You can complete this process using one of the options below.
                </p>

                <h3 style='font-size: 16px; font-weight: 600; margin: 24px 0 12px 0;'>Option 1 — Secure Reset Link</h3>
                
                <div style='text-align: center; margin: 20px 0;'>
                    <a href='{$resetLink}' 
                       style='display: inline-block; background-color: #1f2937; color: white; 
                              padding: 12px 32px; text-decoration: none; border-radius: 6px; 
                              font-weight: 500;'>
                       Reset Password
                    </a>
                </div>

                <p style='font-size: 12px; word-break: break-all; text-align: center; color: #6b7280; margin: 12px 0 24px 0;'>
                    {$resetLink}
                </p>

                <h3 style='font-size: 16px; font-weight: 600; margin: 24px 0 12px 0;'>Option 2 — One-Time Code</h3>

                <div style='background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 24px; text-align: center; margin: 12px 0 24px 0;'>
                    <p style='margin: 0 0 8px 0; font-size: 14px; color: #6b7280;'>Password Reset Code</p>
                    <p style='font-size: 28px; font-weight: 600; letter-spacing: 4px; margin: 8px 0; color: #1f2937;'>{$otp}</p>
                    <p style='margin: 8px 0 0 0; font-size: 13px; color: #6b7280;'>
                        This code expires in {$expiryMinutes} minutes
                    </p>
                </div>

                <div style='background-color: #fffbeb; border: 1px solid #fef3c7; border-radius: 6px; padding: 16px; margin: 24px 0;'>
                    <p style='margin: 0; font-size: 13px; color: #92400e;'>
                        <strong>Important:</strong> If you did not request a password reset, 
                        no further action is required. Never share your authentication credentials.
                    </p>
                </div>

                <div style='border-top: 1px solid #e5e7eb; margin: 32px 0 24px 0;'></div>

                <p style='font-size: 13px; color: #6b7280; margin-bottom: 4px;'>
                    Regards,
                </p>
                
                <p style='font-size: 13px; color: #6b7280; margin-top: 0;'>
                    {$appName} Security Team
                </p>
            </div>
        </body>
        </html>
    ";

    return ['title' => $title, 'body' => $body];
}
}