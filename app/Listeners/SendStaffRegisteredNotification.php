<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\StaffRegistered;
use App\Models\Staff;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class SendStaffRegisteredNotification implements ShouldHandleEventsAfterCommit
{
    private const LOGIN_URL = 'https://custocare.custospark.com/login';

    public function __construct(
        private readonly NotificationService $notificationService
    ) {}

    public function handle(StaffRegistered $event): void
    {
        $this->notificationService->sendToUser(
            user:     $event->user,
            title:    'Your Custocare Staff Number is Ready',
            body:     $this->buildBody($event->staff, $event->user),
            type:     'staff_registered',
            channel:  'email',
            ctaUrl:   self::LOGIN_URL,
            ctaLabel: 'Log In to Custocare',
        );
    }

    private function buildBody(Staff $staff, User $user): string
    {
        $firstName = $user->first_name ?? 'Valued Professional';

        return "
        <p style='margin: 0 0 20px 0; color: #374151;'>Dear {$firstName},</p>

        <p style='margin: 0 0 16px 0;'>
            Welcome to <strong>Custocare</strong>. Your staff profile has been created
            and you're now part of our healthcare network.
        </p>

        <p style='margin: 0 0 8px 0; font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; color: #6b7280;'>
            Your Staff Number
        </p>

        <div style='
            background: linear-gradient(135deg, #f0f7ff 0%, #f0fdf8 100%);
            border: 1px solid #bfdbfe;
            border-radius: 12px;
            padding: 24px;
            text-align: center;
            margin: 16px 0 24px;
        '>
            <p style='
                font-size: 32px;
                font-weight: 700;
                letter-spacing: 6px;
                margin: 0;
                color: #111827;
                font-family: \"Courier New\", Courier, monospace;
            '>{$staff->staff_uuid}</p>
        </div>

        <p style='margin: 0 0 8px 0; color: #374151;'>
            <strong>What is this Number for?</strong>
        </p>
        <p style='margin: 0 0 16px 0; color: #374151;'>
            This Number is unique to you and tied to your professional profile.
            Share it with <strong>Health Facility Administrators</strong> so they
            can send you an invitation to join their clinical workspaces.
        </p>

        <p style='margin: 0 0 8px 0; color: #374151;'><strong>How it works:</strong></p>
        <ol style='margin: 0 0 24px 0; padding-left: 20px; color: #4b5563; line-height: 2;'>
            <li>Share your Staff Number with a verified Facility Administrator.</li>
            <li>They will send you an invitation to their facility's workspace.</li>
            <li>Accept the invitation to start collaborating.</li>
        </ol>

        <div style='
            background-color: #fffbeb;
            border: 1px solid #fcd34d;
            border-radius: 10px;
            padding: 16px 20px;
            margin: 24px 0;
        '>
            <p style='margin: 0; font-size: 13px; color: #92400e; line-height: 1.6;'>
                <strong>Security tip:</strong> Only share your Staff Number with trusted,
                verified facility administrators. Custocare staff will never ask for it unsolicited.
            </p>
        </div>

        <p style='margin: 0 0 24px 0; color: #374151;'>
            Ready to get started? Log in to your account at any time.
        </p>

        <div style='margin-top: 36px; padding-top: 24px; border-top: 1px solid #e5e7eb;'>
            <p style='margin: 0 0 4px 0; font-size: 15px; color: #374151;'>Warm regards,</p>
            <p style='margin: 0; font-size: 15px; font-weight: 700; color: #111827;'>Custocare Team</p>
        </div>";
    }
}
