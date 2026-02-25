<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\MfaRequired;
use App\Services\User\AccountRecoveryService;
// use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendMfaVerification 
{
    use InteractsWithQueue;

    public function __construct(
        private readonly AccountRecoveryService $accountRecoveryService
    ) {}

    public function handle(MfaRequired $event): void
    {
        $this->accountRecoveryService->sendEmailVerification(
            $event->userId,
            $event->channel
        );
    }
}