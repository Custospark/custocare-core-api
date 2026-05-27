<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\UserEmailVerified;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class SendUserWelcomeNotification implements ShouldHandleEventsAfterCommit
{
    private const LOGIN_URL = 'https://custocare.custospark.com/login';

    public function __construct(
        private readonly NotificationService $notificationService
    ) {}

    public function handle(UserEmailVerified $event): void
    {
        $this->notificationService->sendToUser(
            user:     $event->user,
            title:    'Welcome to Custocare — Your Email is Verified',
            body:     $this->buildBody($event->user),
            type:     'user_welcome',
            channel:  'email',
            ctaUrl:   self::LOGIN_URL,
            ctaLabel: 'Log In to Custocare',
        );
    }

    private function buildBody(User $user): string
    {
        $firstName = $user->first_name ?? 'Valued User';

        return "
        <p style='margin: 0 0 20px 0; color: #374151;'>Dear {$firstName},</p>

        <p style='margin: 0 0 16px 0;'>
            Welcome to <strong>Custocare</strong>! We're excited to have you on board.
        </p>

        <p style='margin: 0 0 24px 0; color: #374151;'>
            Your email address has been verified and your account is now active.
            You can log in and start exploring what Custocare has to offer.
        </p>

        <p style='margin: 0 0 8px 0; color: #374151;'><strong>What you can do now:</strong></p>
        <ul style='margin: 0 0 24px 0; padding-left: 20px; color: #4b5563; line-height: 2;'>
            <li><strong>Complete your profile</strong> — Add your details to personalise your experience.</li>
            <li><strong>Register as a patient</strong> — Set up your patient portal to access health records and book appointments.</li>
            <li><strong>Register as a staff member</strong> — Join clinical workspaces and collaborate with healthcare teams.</li>
            <li><strong>Register a facility</strong> — Set up your healthcare facility on Custocare.</li>
        </ul>

        <p style='margin: 0 0 24px 0; color: #374151;'>
            Need help getting started? Contact our support team.
        </p>

        <div style='margin-top: 36px; padding-top: 24px; border-top: 1px solid #e5e7eb;'>
            <p style='margin: 0 0 4px 0; font-size: 15px; color: #374151;'>Warm regards,</p>
            <p style='margin: 0; font-size: 15px; font-weight: 700; color: #111827;'>Custocare Team</p>
        </div>";
    }
}
