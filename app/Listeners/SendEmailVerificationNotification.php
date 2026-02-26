<?php
// app/Listeners/Auth/SendEmailVerificationNotification.php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\Auth\EmailVerificationRequested;
use App\Events\EmailVerificationRequested as EventsEmailVerificationRequested;
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

    public function handle(EventsEmailVerificationRequested $event): void
{
    Log::debug('🔵 LISTENER STARTED', [
        'user_id' => $event->user->id,
        'channel' => $event->channel,
        'has_token' => !empty($event->token),
        'has_otp' => !empty($event->otp)
    ]);

    $user    = $event->user;
    $token   = $event->token;
    $otp     = $event->otp;
    $channel = $event->channel;

    $title = 'Verify Your Email Address';
    $body  = $this->buildEmailBody($user, $token, $otp);

    Log::debug('🔵 ABOUT TO CALL NOTIFICATION SERVICE', [
        'user_id' => $user->id,
        'notification_service_class' => get_class($this->notificationService)
    ]);

    $deliveryChannel = ($channel === 'both') ? 'both' : 'email';

    try {
        $this->notificationService->sendToUser(
            user:    $user,
            title:   $title,
            body:    $body,
            type:    'email_verification',
            channel: $deliveryChannel
        );
        Log::debug('✅ NOTIFICATION SERVICE CALL COMPLETED', ['user_id' => $user->id]);
    } catch (\Throwable $e) {
        Log::error('❌ EXCEPTION IN NOTIFICATION SERVICE', [
            'user_id' => $user->id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }

    Log::info('Email verification notification dispatched', [
        'user_id' => $user->id,
        'channel' => $deliveryChannel,
    ]);
}
    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Build the HTML email body for email verification.
     */
    private function buildEmailBody(User $user, string $token, string $otp): string
    {
        $firstName        = $user->first_name ?? 'User';
        $verificationLink = rtrim(config('app.frontend_url'), '/') . "/verify-email?token={$token}";
        $expiryMinutes    = User::TOKEN_EXPIRATION_MINUTES;
        $appName          = config('app.name');

        return "
            <h2>Hello {$firstName},</h2>
            <p>Thank you for registering. Please verify your email address using one of the options below:</p>

            <h3>Option 1 — Click the verification link</h3>
            <p><a href='{$verificationLink}'>Verify Email Address</a></p>
            <p style='word-break:break-all;'>Or copy this link: {$verificationLink}</p>

            <h3>Option 2 — Enter the OTP code</h3>
            <p><strong style='font-size:1.4em;letter-spacing:0.15em;'>{$otp}</strong></p>
            <p>This code expires in <strong>{$expiryMinutes} minutes</strong>.</p>

            <p>If you did not create an account with {$appName}, you can safely ignore this email.</p>

            <p>Regards,<br>{$appName} Team</p>
        ";
    }
}
