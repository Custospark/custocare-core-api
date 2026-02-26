<?php
// app/Listeners/Auth/SendMfaRequiredNotification.php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\Auth\MfaRequired;
use App\Events\MfaRequired as EventsMfaRequired;
use Illuminate\Support\Facades\Log;

/**
 * Listens for the MfaRequired event.
 *
 * MFA here uses TOTP (Google Authenticator / similar apps), so no code
 * needs to be sent to the user — they open their authenticator app.
 * This listener is intentionally lightweight; it exists as an extension
 * point for audit logging, push-notification nudges, or future email-OTP MFA.
 */
class SendMfaRequiredNotification
{
    /**
     * Handle the event.
     */
    public function handle(EventsMfaRequired $event): void
    {
        // TOTP MFA: no code to transmit — user reads code from their app.
        // Log for audit trail only.
        Log::info('MFA challenge initiated for user', [
            'user_id' => $event->user->id,
        ]);
    }
}
