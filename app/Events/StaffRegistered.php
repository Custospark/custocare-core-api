<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Staff;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;

class StaffRegistered
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public Staff $staff,
        public User $user,
    ) {}
}
