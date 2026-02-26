<?php
// app/Listeners/Auth/SendMfaRequiredNotification.php

declare(strict_types=1);

namespace App\Listeners\Auth;

use App\Events\MfaRequired;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Log;

/**
 * Listens for MfaRequired and dispatches the MFA OTP code to the user.
 *
 * Implements ShouldHandleEventsAfterCommit so the notification is
 * only sent once the DB transaction that fired the event has committed.
 */
class SendMfaRequiredNotification implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private readonly NotificationService $notificationService
    ) {}

    /**
     * Handle the event.
     */
    public function handle(MfaRequired $event): void
    {
        Log::debug('🔵 MFA LISTENER STARTED', [
            'user_id' => $event->user->id,
            'channel' => $event->channel,
            'has_token' => !empty($event->token),
            'has_otp' => !empty($event->otp)
        ]);

        $user    = $event->user;
        $token   = $event->token;
        $otp     = $event->otp;
        $channel = $event->channel;

        $title = 'Your Login Verification Code';
        $body  = $this->buildMfaEmailBody($user, $otp);

        Log::debug('🔵 ABOUT TO CALL NOTIFICATION SERVICE FOR MFA', [
            'user_id' => $user->id,
            'notification_service_class' => get_class($this->notificationService)
        ]);

        $deliveryChannel = ($channel === 'both') ? 'both' : 'email';

        try {
            $this->notificationService->sendToUser(
                user:    $user,
                title:   $title,
                body:    $body,
                type:    'mfa_required',
                channel: $deliveryChannel
            );
            Log::debug('✅ MFA NOTIFICATION SERVICE CALL COMPLETED', ['user_id' => $user->id]);
        } catch (\Throwable $e) {
            Log::error('❌ EXCEPTION IN MFA NOTIFICATION SERVICE', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        Log::info('MFA verification notification dispatched', [
            'user_id' => $user->id,
            'channel' => $deliveryChannel,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Build the HTML email body for MFA verification.
     */
    private function buildMfaEmailBody(User $user, string $otp): string
    {
        $firstName = $user->first_name ?? 'User';
        $expiryMinutes = 10; // MFA codes expire in 10 minutes
        $appName = config('app.name');
        $loginTime = now()->format('F j, Y \a\t g:i A');
        $ip = request()->ip();
        $userAgent = request()->userAgent();

        return "
            <h2>Login Verification Required</h2>
            
            <p>Hello {$firstName},</p>
            
            <p>We received a login attempt on your <strong>{$appName}</strong> account. To complete the login, please use the verification code below:</p>

            <h3>Your Verification Code</h3>
            <p><strong style='font-size:1.4em;letter-spacing:0.15em;'>{$otp}</strong></p>
            
            <p>This code expires in <strong>{$expiryMinutes} minutes</strong>.</p>
            
            <div style='background-color: #f8f9fa; padding: 16px; border-radius: 8px; margin: 20px 0;'>
                <p style='margin: 0;'><strong>Login attempt details:</strong></p>
                <p style='margin: 8px 0 0; font-family: monospace;'>
                    Time: {$loginTime}<br>
                    IP Address: {$ip}<br>
                    Device: {$userAgent}
                </p>
            </div>

            <p><strong>Didn't request this?</strong><br>
            If you did not attempt to log in, please secure your account immediately by changing your password.</p>

            <p>Regards,<br>{$appName} Security Team</p>
        ";
    }
}