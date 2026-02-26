<?php
// app/Events/EmailVerificationRequested.php

declare(strict_types=1);

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a user requests email verification (registration or resend).
 * Listeners are responsible for sending the actual notification.
 */
class EmailVerificationRequested
{
    use Dispatchable, SerializesModels;

    /**
     * @param User   $user    The user who needs email verification
     * @param string $token   The raw (un-hashed) verification token for link-based verification
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
