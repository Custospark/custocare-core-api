<?php
// app/Events/Auth/EmailChanged.php

declare(strict_types=1);

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a user's email address has been successfully changed.
 * Listeners send a security confirmation notification to both the old and new email addresses.
 */
class PasswordChanged
{
    use Dispatchable, SerializesModels;

    /**
     * @param User $user The user whose email was changed
     * @param string $channel Delivery channel: 'email' | 'sms' | 'both' (defaults to 'email')
     * @param string $action Action type: 'email_change_confirmation' | 'email_change_alert'
     */
    public function __construct(
        public readonly User $user,
        public readonly string $channel = 'email',
        public readonly string $action
    ) {}
}