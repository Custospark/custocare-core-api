<?php
// app/Listeners/Auth/SendEmailVerificationNotification.php

declare(strict_types=1);

namespace App\Listeners;

use App\Constants\ActionTypes;
use App\Events\EmailVerificationRequested;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * Listens for EmailVerificationRequested and dispatches
 * the appropriate transactional email / SMS to the user.
 *
 * Implements ShouldHandleEventsAfterCommit so the notification is
 * only sent once the DB transaction that fired the event has committed,
 * guaranteeing the user record is fully persisted before delivery.
 */
class SendEmailVerificationNotification implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private readonly NotificationService $notificationService
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Public entry-point
    // ─────────────────────────────────────────────────────────────────────────

    public function handle(EmailVerificationRequested $event): void
    {
        $user    = $event->user;
        $token   = $event->token;
        $otp     = $event->otp;
        $channel = $event->channel;
        $action  = $event->action;

        $messageData     = $this->buildMessage($user, $token, $otp, $action);
        $deliveryChannel = ($channel === 'both') ? 'both' : 'email';

        $this->notificationService->sendToUser(
            user:    $user,
            title:   $messageData['title'],
            body:    $messageData['body'],
            type:    $this->mapActionToType($action),
            channel: $deliveryChannel,
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Routing helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Translate a raw action string to its canonical notification-type key.
     */
    private function mapActionToType(string $action): string
    {
        return match ($action) {
            ActionTypes::PASSWORD_RESET      => 'password_reset',
            ActionTypes::PASSWORD_CHANGED    => 'password_changed',
            ActionTypes::LOGIN_CONFIRMATION  => 'login_confirmation',
            ActionTypes::ACCOUNT_CREATION    => 'email_verification',
            default                          => 'email_verification',
        };
    }

    /**
     * Dispatch to the correct message-builder and return ['title', 'body'].
     *
     * @throws \InvalidArgumentException for unknown action types.
     */
    private function buildMessage(
        User   $user,
        string $token,
        string $otp,
        string $action,
    ): array {
        return match ($action) {
            ActionTypes::PASSWORD_RESET     => $this->buildPasswordResetMessage($user, $token, $otp),
            ActionTypes::PASSWORD_CHANGED   => $this->buildPasswordChangedMessage($user),
            ActionTypes::LOGIN_CONFIRMATION => $this->buildLoginConfirmationMessage($user, $otp),
            ActionTypes::ACCOUNT_CREATION   => $this->buildAccountCreationMessage($user, $otp),
            default => throw new \InvalidArgumentException(
                "Unrecognised action type passed to SendEmailVerificationNotification: \"{$action}\""
            ),
        };
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Shared rendering primitives
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Render the full-width OTP code block.
     *
     * @param string $label       Short label shown above the code (e.g. "Verification Code")
     * @param string $otp         The numeric/alphanumeric code to display
     * @param int    $expiryMins  Expiry window in minutes
     */
    private function renderOtpBlock(string $label, string $otp, int $expiryMins): string
    {
        return "
        <div style='
            background: linear-gradient(135deg, #f0f7ff 0%, #f0fdf8 100%);
            border: 1px solid #bfdbfe;
            border-radius: 12px;
            padding: 32px 24px;
            text-align: center;
            margin: 28px 0;
        '>
            <p style='
                margin: 0 0 6px 0;
                font-size: 11px;
                font-weight: 700;
                letter-spacing: 2px;
                text-transform: uppercase;
                color: #2563eb;
            '>{$label}</p>

            <p style='
                font-size: 38px;
                font-weight: 700;
                letter-spacing: 10px;
                margin: 12px 0;
                color: #111827;
                font-family: \"Courier New\", Courier, monospace;
            '>{$otp}</p>

            <div style='
                display: inline-block;
                background-color: #fef3c7;
                border: 1px solid #fcd34d;
                border-radius: 20px;
                padding: 4px 16px;
                margin-top: 8px;
            '>
                <p style='margin: 0; font-size: 12px; color: #92400e; font-weight: 600;'>
                    ⏱ &nbsp;Expires in {$expiryMins} minutes — do not share this code
                </p>
            </div>
        </div>";
    }

    /**
     * Render a security notice banner.
     *
     * @param string  $message   The notice copy.
     * @param string  $severity  'info' | 'warning' | 'critical'
     */
    private function renderSecurityNotice(string $message, string $severity = 'info'): string
    {
        $styles = match ($severity) {
            'critical' => [
                'bg'     => '#fef2f2',
                'border' => '#fca5a5',
                'icon'   => '🚨',
                'label'  => 'Critical Security Alert',
                'label_color' => '#991b1b',
                'text'   => '#7f1d1d',
            ],
            'warning' => [
                'bg'     => '#fffbeb',
                'border' => '#fcd34d',
                'icon'   => '⚠️',
                'label'  => 'Security Notice',
                'label_color' => '#92400e',
                'text'   => '#78350f',
            ],
            default => [   // info
                'bg'     => '#eff6ff',
                'border' => '#93c5fd',
                'icon'   => '🔒',
                'label'  => 'Security Notice',
                'label_color' => '#1d4ed8',
                'text'   => '#1e3a5f',
            ],
        };

        return "
        <div style='
            background-color: {$styles['bg']};
            border: 1px solid {$styles['border']};
            border-radius: 10px;
            padding: 16px 20px;
            margin: 28px 0;
        '>
            <p style='
                margin: 0 0 6px 0;
                font-size: 12px;
                font-weight: 700;
                letter-spacing: 1px;
                text-transform: uppercase;
                color: {$styles['label_color']};
            '>{$styles['icon']} &nbsp;{$styles['label']}</p>
            <p style='margin: 0; font-size: 14px; color: {$styles['text']}; line-height: 1.6;'>
                {$message}
            </p>
        </div>";
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

    // ─────────────────────────────────────────────────────────────────────────
    // Message builders
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * ── Account Creation ─────────────────────────────────────────────────────
     * Sent when a new user registers. Contains only the OTP; no reset link.
     */
    private function buildAccountCreationMessage(User $user, string $otp): array
    {
        $firstName     = $user->first_name ?? 'Valued Customer';
        $expiryMinutes = User::TOKEN_EXPIRATION_MINUTES;
        $appName       = config('app.name');

        $title = 'Verify Your Email Address';

        $otpBlock = $this->renderOtpBlock('Email Verification Code', $otp, (int)$expiryMinutes);

        $securityNotice = $this->renderSecurityNotice(
            'If you did not create an account with ' . $appName . ', you can safely disregard this email. '
            . 'No account will be activated without this verification step.',
            'info'
        );

        $signOff = $this->renderSignOff($appName);

        $body = "
        <p style='margin: 0 0 20px 0; color: #374151;'>Dear {$firstName},</p>

        <p style='margin: 0 0 16px 0;'>
            Welcome to <strong>{$appName}</strong>. We're pleased to have you on board.
        </p>

        <p style='margin: 0 0 24px 0; color: #374151;'>
            To complete your registration and activate your account, please verify your
            email address by entering the one-time verification code below in the
            registration screen.
        </p>

        {$otpBlock}

        <p style='margin: 0 0 8px 0; color: #374151;'>
            <strong>How to verify:</strong>
        </p>
        <ol style='margin: 0 0 24px 0; padding-left: 20px; color: #4b5563; line-height: 2;'>
            <li>Return to the <strong>{$appName}</strong> registration screen.</li>
            <li>Enter the six-digit code displayed above.</li>
            <li>Your account will be activated immediately upon successful verification.</li>
        </ol>

        {$securityNotice}
        {$signOff}";

        return ['title' => $title, 'body' => $body];
    }

    /**
     * ── Login Confirmation (2FA / MFA) ───────────────────────────────────────
     * Sent when a sign-in attempt requires secondary authentication.
     * Contains only the OTP; no reset link.
     */
    private function buildLoginConfirmationMessage(User $user, string $otp): array
    {
        $firstName     = $user->first_name ?? 'Valued Customer';
        $expiryMinutes = User::TOKEN_EXPIRATION_MINUTES;
        $appName       = config('app.name');
        $requestTime   = now()->format('l, F j, Y \a\t g:i A T');
        $ipAddress     = request()->ip() ?? ($_SERVER['REMOTE_ADDR'] ?? 'Unavailable');

        $title = 'Your Sign-In Verification Code';

        $detailTable = $this->renderDetailTable([
            'Timestamp' => $requestTime,
            'IP Address' => "<span style='font-family: monospace; font-size: 13px;'>{$ipAddress}</span>",
        ]);

        $otpBlock = $this->renderOtpBlock('One-Time Authentication Code', $otp, (int)$expiryMinutes);

        $securityNotice = $this->renderSecurityNotice(
            '<strong>Did not attempt to sign in?</strong> If you did not initiate this login request, '
            . 'your account credentials may be compromised. Please change your password immediately and '
            . 'contact our support team. Never share this code with anyone — '
            . $appName . ' staff will never ask for it.',
            'warning'
        );

        $signOff = $this->renderSignOff($appName);

        $body = "
        <p style='margin: 0 0 20px 0; color: #374151;'>Dear {$firstName},</p>

        <p style='margin: 0 0 16px 0;'>
            A sign-in attempt to your <strong>{$appName}</strong> account has been detected.
            To protect your account, we require you to confirm this request with a
            one-time authentication code.
        </p>

        <p style='margin: 0 0 8px 0; color: #6b7280; font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;'>
            Request Details
        </p>

        {$detailTable}
        {$otpBlock}

        <p style='margin: 0 0 24px 0; color: #374151;'>
            Enter this code on the authentication screen to complete your sign-in.
            This code is valid for a single use only and will expire after
            <strong>{$expiryMinutes} minutes</strong>.
        </p>

        {$securityNotice}
        {$signOff}";

        return ['title' => $title, 'body' => $body];
    }

    /**
     * ── Password Changed Notification ────────────────────────────────────────
     * Sent immediately after a successful password change.
     * This is a security alert — no verification code required.
     * If the change was not authorised, the user must act immediately.
     */
    private function buildPasswordChangedMessage(User $user): array
    {
        $firstName    = $user->first_name ?? 'Valued Customer';
        $appName      = config('app.name');
        $changedAt    = now()->format('l, F j, Y \a\t g:i A T');
        $ipAddress    = request()->ip() ?? ($_SERVER['REMOTE_ADDR'] ?? 'Unavailable');
        $userAgent    = request()->userAgent() ?? ($_SERVER['HTTP_USER_AGENT'] ?? 'Unavailable');
        $supportEmail = config('app.support_email', 'support@' . config('app.domain', 'custospark.com'));
        $supportPhone = config('app.support_phone', 'Please refer to our website');

        $title = 'Your Password Has Been Changed';

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

        return ['title' => $title, 'body' => $body];
    }

    /**
     * ── Password Reset Request ───────────────────────────────────────────────
     * Sent when the user requests a password reset.
     * Provides both a secure one-click link AND a fallback OTP code.
     */
   private function buildPasswordResetMessage(User $user, string $token, string $otp): array
{
    $firstName     = $user->first_name ?? 'Valued Customer';
    $expiryMinutes = User::TOKEN_EXPIRATION_MINUTES;
    $appName       = config('app.name');
    $baseUrl       = rtrim(config('app.frontend_url'), '/');
    $resetLink     = $baseUrl . '/reset-password?token=' . $token;

    $title = 'Password Reset Request';

    $linkBlock = "
    <div style='
        background: linear-gradient(135deg, #eff6ff 0%, #f0fdf4 100%);
        border: 1px solid #bfdbfe;
        border-radius: 12px;
        padding: 28px 24px;
        text-align: center;
        margin: 28px 0;
    '>
        <p style='
            margin: 0 0 6px 0;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #2563eb;
        '>Reset Your Password</p>

        <p style='margin: 12px 0 20px 0; font-size: 15px; color: #374151;'>
            Click the button below to be taken directly to the password reset screen.
            This link is uniquely tied to your account and expires in
            <strong>{$expiryMinutes} minutes</strong>.
        </p>

        <a href='{$resetLink}'
           style='
               display: inline-block;
               background: linear-gradient(90deg, #2563eb 0%, #059669 100%);
               color: #ffffff;
               text-decoration: none;
               padding: 14px 40px;
               border-radius: 30px;
               font-weight: 700;
               font-size: 15px;
               letter-spacing: 0.5px;
               box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35);
           '>
            Reset My Password &rarr;
        </a>

        <p style='
            margin: 20px 0 0 0;
            font-size: 12px;
            color: #9ca3af;
            word-break: break-all;
            line-height: 1.6;
        '>
            If the button does not work, copy and paste this URL into your browser:<br>
            <span style='color: #6b7280;'>{$resetLink}</span>
        </p>
    </div>";

    $securityNotice = $this->renderSecurityNotice(
        'If you did not request a password reset, no action is required and your password remains unchanged. '
        . 'However, if you believe someone has unauthorised access to your account, '
        . 'please contact our support team immediately.',
        'warning'
    );

    $signOff = $this->renderSignOff($appName);

    $body = "
    <p style='margin: 0 0 20px 0; color: #374151;'>Dear {$firstName},</p>

    <p style='margin: 0 0 16px 0;'>
        We received a request to reset the password for your <strong>{$appName}</strong> account.
        Use the link below to complete the process — it expires in
        <strong>{$expiryMinutes} minutes</strong>.
    </p>

    {$linkBlock}

    <p style='margin: 0 0 24px 0; color: #374151;'>
        For security, this link is valid for a <strong>single use only</strong>.
        Once your password has been reset, any previous credentials will be invalidated.
    </p>

    {$securityNotice}
    {$signOff}";

    return ['title' => $title, 'body' => $body];
}
}
