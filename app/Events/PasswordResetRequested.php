<?php
// app/Events/Auth/PasswordResetRequested.php

declare(strict_types=1);

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a user initiates a password reset.
 * Listeners are responsible for sending the reset notification.
 */
class PasswordResetRequested
{
    use Dispatchable, SerializesModels;

    /**
     * @param User   $user    The user requesting the password reset
     * @param string $token   The raw (un-hashed) reset token for link-based reset
     * @param string $otp     The 6-digit OTP for code-based reset
     * @param string $channel Delivery channel: 'email' | 'sms' | 'both'
     */
    public function __construct(
        public readonly User   $user,
        public readonly string $token,
        public readonly string $otp,
        public readonly string $channel = 'email'
    ) {}
}
