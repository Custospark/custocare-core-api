<?php
// app/Events/Auth/MfaRequired.php

declare(strict_types=1);

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when MFA verification is required during login.
 * Listeners are responsible for sending the OTP code to the user.
 */
class MfaRequired
{
    use Dispatchable, SerializesModels;

    /**
     * @param User   $user    The user who needs to complete MFA
     * @param string $token   The raw (un-hashed) verification token
     * @param string $otp     The 6-digit OTP for code-based verification
     * @param string $channel Delivery channel: 'email' | 'sms' | 'both'
     */
    public function __construct(
        public readonly User   $user,
        public readonly string $token,
        public readonly string $otp,
        public readonly string $channel = 'email'
    ) {}
}