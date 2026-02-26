<?php
// app/Listeners/SendPasswordResetNotification.php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\Auth\PasswordResetRequested;
use App\Events\PasswordResetRequested as EventsPasswordResetRequested;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Log;

/**
 * Listens for PasswordResetRequested and sends the reset
 * token / OTP to the user via the requested channel.
 */
class SendPasswordResetNotification implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private readonly NotificationService $notificationService
    ) {}

    /**
     * Handle the event.
     */
    public function handle(EventsPasswordResetRequested $event): void
    {
        $user    = $event->user;
        $token   = $event->token;
        $otp     = $event->otp;
        $channel = $event->channel;

        $title           = 'Reset Your Password';
        $body            = $this->buildEmailBody($user, $token, $otp);
        $deliveryChannel = ($channel === 'both') ? 'both' : 'email';

        $this->notificationService->sendToUser(
            user:    $user,
            title:   $title,
            body:    $body,
            type:    'password_reset',
            channel: $deliveryChannel
        );

        Log::info('Password reset notification dispatched', [
            'user_id' => $user->id,
            'channel' => $deliveryChannel,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Build the HTML email body for password reset.
     */
    private function buildEmailBody(User $user, string $token, string $otp): string
    {
        $firstName     = $user->first_name ?? 'User';
        $resetLink     = rtrim(config('app.frontend_url'), '/') . "/reset-password?token={$token}";
        $expiryMinutes = User::TOKEN_EXPIRATION_MINUTES;
        $appName       = config('app.name');

        return "
            <h2>Hello {$firstName},</h2>
            <p>We received a request to reset your password. Use one of the options below:</p>

            <h3>Option 1 — Click the reset link</h3>
            <p><a href='{$resetLink}'>Reset Password</a></p>
            <p style='word-break:break-all;'>Or copy this link: {$resetLink}</p>

            <h3>Option 2 — Enter the OTP code</h3>
            <p><strong style='font-size:1.4em;letter-spacing:0.15em;'>{$otp}</strong></p>
            <p>This code expires in <strong>{$expiryMinutes} minutes</strong>.</p>

            <p>If you did not request a password reset, please ignore this email or contact support if you have concerns.</p>

            <p>Regards,<br>{$appName} Team</p>
        ";
    }
}
