<?php
// app/Listeners/Auth/SendPasswordChangedNotification.php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\Auth\PasswordChanged;
use App\Events\PasswordChanged as EventsPasswordChanged;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Log;

/**
 * Listens for PasswordChanged and sends a security alert
 * to confirm the password was changed successfully.
 */
class SendPasswordChangedNotification implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private readonly NotificationService $notificationService
    ) {}

    /**
     * Handle the event.
     */
    public function handle(EventsPasswordChanged $event): void
    {
        $user = $event->user;

        $title = 'Your Password Has Been Changed';
        $body  = $this->buildEmailBody($user);

        $this->notificationService->sendToUser(
            user:    $user,
            title:   $title,
            body:    $body,
            type:    'security_alert',
            channel: 'email'
        );

        Log::info('Password changed notification dispatched', [
            'user_id' => $user->id,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Build the HTML email body for password-changed alert.
     */
    private function buildEmailBody(User $user): string
    {
        $firstName = $user->first_name ?? 'User';
        $appName   = config('app.name');

        return "
            <h2>Hello {$firstName},</h2>
            <p>Your password has been <strong>successfully changed</strong>.</p>
            <p>If you did not make this change, please <strong>contact support immediately</strong> and secure your account.</p>

            <p>Regards,<br>{$appName} Team</p>
        ";
    }
}
