<?php
// app/Listeners/Auth/SendPasswordChangedNotification.php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\PasswordChanged;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Log;

/**
 * Listens for PasswordChanged and sends a security alert
 * to confirm the password was changed successfully.
 *
 * Implements ShouldHandleEventsAfterCommit so the notification is
 * only sent once the DB transaction that fired the event has committed,
 * guaranteeing the user record is fully persisted before delivery.
 */
class SendPasswordChangedNotification implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private readonly NotificationService $notificationService
    ) {}

    /**
     * Handle the event.
     */
    public function handle(PasswordChanged $event): void
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
        $firstName    = $user->first_name ?? 'Valued Customer';
        $appName      = config('app.name');
        $changedAt    = now()->format('l, F j, Y \a\t g:i A T');
        $ipAddress    = request()->ip() ?? ($_SERVER['REMOTE_ADDR'] ?? 'Unavailable');
        $userAgent    = request()->userAgent() ?? ($_SERVER['HTTP_USER_AGENT'] ?? 'Unavailable');
        $supportEmail = config('app.support_email', 'support@' . config('app.domain', 'custospark.com'));
        $supportPhone = config('app.support_phone', 'Please refer to our website');

        $detailTable = $this->renderDetailTable([
            'Changed At' => $changedAt,
            'IP Address' => "<span style='font-family: monospace; font-size: 13px;'>{$ipAddress}</span>",
            'Device'     => "<span style='font-size: 12px; color: #6b7280;'>{$userAgent}</span>",
        ]);

        $criticalAlert = "
        <div style='
            background-color: #fef2f2;
            border: 2px solid #f87171;
            border-radius: 12px;
            padding: 28px 24px;
            margin: 32px 0;
        '>
            <p style='
                margin: 0 0 4px 0;
                font-size: 11px;
                font-weight: 700;
                letter-spacing: 2px;
                text-transform: uppercase;
                color: #991b1b;
            '>🚨 &nbsp;Urgent Action Required</p>

            <p style='margin: 12px 0 16px 0; font-size: 17px; font-weight: 700; color: #7f1d1d;'>
                If you did not change your password, your account may be at risk.
            </p>

            <p style='margin: 0 0 16px 0; font-size: 15px; color: #374151;'>
                Take the following steps immediately:
            </p>

            <ol style='margin: 0 0 24px 0; padding-left: 22px; color: #374151; line-height: 2; font-size: 15px;'>
                <li><strong>Contact our support team</strong> using the details below to have your account secured.</li>
                <li><strong>Attempt to sign in</strong> — if access is still available, change your password immediately.</li>
                <li><strong>Review recent account activity</strong> for any unauthorised changes.</li>
                <li><strong>Enable multi-factor authentication</strong> to protect your account going forward.</li>
            </ol>

            <div style='
                background-color: white;
                border: 1px solid #fecaca;
                border-radius: 8px;
                padding: 20px;
            '>
                <p style='margin: 0 0 12px 0; font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #991b1b;'>
                    Contact Support Immediately
                </p>
                <p style='margin: 0 0 8px 0; font-size: 15px; color: #111827;'>
                    📧 &nbsp;<a href='mailto:{$supportEmail}' style='color: #2563eb; text-decoration: none; font-weight: 600;'>{$supportEmail}</a>
                </p>
                <p style='margin: 0 0 16px 0; font-size: 15px; color: #111827;'>
                    📞 &nbsp;<strong>{$supportPhone}</strong>
                </p>
                <p style='margin: 0; font-size: 13px; color: #6b7280; line-height: 1.6;'>
                    When contacting support, please reference that you received an unauthorised
                    password change notification so our security team can prioritise your case.
                </p>
            </div>
        </div>";

        $ifYouBlock = "
        <div style='
            background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%);
            border: 1px solid #6ee7b7;
            border-radius: 10px;
            padding: 20px 24px;
            margin: 24px 0;
        '>
            <p style='margin: 0 0 6px 0; font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #065f46;'>
                ✅ &nbsp;No Action Needed
            </p>
            <p style='margin: 0; font-size: 15px; color: #374151; line-height: 1.6;'>
                If <strong>you</strong> initiated this password change, your account is secure.
                You may disregard the urgent section above. We recommend reviewing your active
                sessions and enabling multi-factor authentication if not already configured.
            </p>
        </div>";

        $signOff = $this->renderSignOff($appName);

        $body = "
        <p style='margin: 0 0 20px 0; color: #374151;'>Dear {$firstName},</p>

        <p style='margin: 0 0 16px 0;'>
            This is a security notification to confirm that the password associated with
            your <strong>{$appName}</strong> account was successfully changed.
        </p>

        <p style='margin: 0 0 8px 0; color: #6b7280; font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;'>
            Change Details
        </p>

        {$detailTable}
        {$criticalAlert}
        {$ifYouBlock}
        {$signOff}";

        return $body;
    }

    /**
     * Render a detail-row table (e.g. for login metadata).
     *
     * @param array<string, string> $rows  Associative array of label => value pairs.
     */
    private function renderDetailTable(array $rows): string
    {
        $rowsHtml = '';
        foreach ($rows as $label => $value) {
            $rowsHtml .= "
            <tr>
                <td style='
                    padding: 10px 16px 10px 0;
                    font-size: 13px;
                    color: #6b7280;
                    white-space: nowrap;
                    vertical-align: top;
                    width: 120px;
                    font-weight: 600;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                '>{$label}</td>
                <td style='
                    padding: 10px 0;
                    font-size: 14px;
                    color: #111827;
                    font-weight: 500;
                    line-height: 1.5;
                    border-bottom: 1px solid #f3f4f6;
                '>{$value}</td>
            </tr>";
        }

        return "
        <div style='
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 8px 20px;
            margin: 24px 0;
        '>
            <table style='width: 100%; border-collapse: collapse;'>
                {$rowsHtml}
            </table>
        </div>";
    }

    /**
     * Render a standard email sign-off block.
     */
    private function renderSignOff(string $appName): string
    {
        return "
        <div style='margin-top: 36px; padding-top: 24px; border-top: 1px solid #e5e7eb;'>
            <p style='margin: 0 0 4px 0; font-size: 15px; color: #374151;'>Warm regards,</p>
            <p style='margin: 0 0 2px 0; font-size: 15px; font-weight: 700; color: #111827;'>{$appName} Security Team</p>
        </div>";
    }
}