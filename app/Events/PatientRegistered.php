<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;

class PatientRegistered
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public Patient $patient,
        public User $user,
    ) {}
}
