<?php
// app/Events/Auth/PasswordChanged.php

declare(strict_types=1);

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after a user's password has been successfully changed.
 * Listeners send a security confirmation notification.
 */
class PasswordChanged
{
    use Dispatchable, SerializesModels;

    /**
     * @param User $user The user whose password was changed
     */
    public function __construct(
        public readonly User $user
    ) {}
}
