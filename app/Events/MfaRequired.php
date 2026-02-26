<?php
// app/Events/Auth/MfaRequired.php

declare(strict_types=1);

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when MFA verification is required during login.
 * Since MFA uses TOTP (Google2FA), no code is sent via this event —
 * the user opens their authenticator app. This event exists for
 * audit logging or future extensibility (e.g. push notifications).
 */
class MfaRequired
{
    use Dispatchable, SerializesModels;

    /**
     * @param User $user The user who must complete MFA
     */
    public function __construct(
        public readonly User $user
    ) {}
}
