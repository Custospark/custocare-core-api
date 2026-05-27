<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\FacilityRegistered;
use App\Mail\StandardEmail;
use App\Models\Facility;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendFacilityRegisteredNotification implements ShouldHandleEventsAfterCommit
{
    private const LOGIN_URL = 'https://custocare.custospark.com/login';

    public function __construct(
        private readonly NotificationService $notificationService
    ) {}

    public function handle(FacilityRegistered $event): void
    {
        // Send to the facility owner's user email
        $this->notificationService->sendToUser(
            user:     $event->ownerUser,
            title:    'Welcome to Custocare — Your Facility is Set Up',
            body:     $this->buildBody($event->facility, $event->ownerUser),
            type:     'facility_registered',
            channel:  'email',
            ctaUrl:   self::LOGIN_URL,
            ctaLabel: 'Log In to Custocare',
        );

        // Also send to the facility's direct email if present
        if ($event->facility->email) {
            $this->sendToFacilityEmail($event->facility);
        }
    }

    private function buildBody(Facility $facility, User $user): string
    {
        $firstName     = $user->first_name ?? 'Valued Administrator';
        $facilityName  = $facility->facility_name;
        $facilityCode  = $facility->facility_code;

        return "
        <p style='margin: 0 0 20px 0; color: #374151;'>Dear {$firstName},</p>

        <p style='margin: 0 0 16px 0;'>
            Congratulations! Your healthcare facility <strong>{$facilityName}</strong>
            has been successfully registered on <strong>Custocare</strong>.
        </p>

        <p style='margin: 0 0 8px 0; font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; color: #6b7280;'>
            Your Facility Number
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
            '>{$facilityCode}</p>
        </div>

        <p style='margin: 0 0 8px 0; color: #374151;'>
            This is your facility's unique identifier. Keep it safe — you'll need it for:
        </p>
        <ul style='margin: 0 0 24px 0; padding-left: 20px; color: #4b5563; line-height: 2;'>
            <li>Verifying your facility during support inquiries</li>
            <li>Billing and subscription management</li>
            <li>Staff onboarding — share with staff to link them to this facility</li>
        </ul>

        <p style='margin: 0 0 8px 0; color: #374151;'><strong>Next steps:</strong></p>
        <ol style='margin: 0 0 24px 0; padding-left: 20px; color: #4b5563; line-height: 2;'>
            <li><strong>Set up services</strong> — Configure the clinical services your facility offers.</li>
            <li><strong>Review your subscription</strong> — Check your plan and billing details.</li>
        </ol>

        <p style='margin: 0 0 24px 0; color: #374151;'>
            If you have any questions, our support team is here to help.
        </p>

        <div style='margin-top: 36px; padding-top: 24px; border-top: 1px solid #e5e7eb;'>
            <p style='margin: 0 0 4px 0; font-size: 15px; color: #374151;'>Warm regards,</p>
            <p style='margin: 0; font-size: 15px; font-weight: 700; color: #111827;'>Custocare Team</p>
        </div>";
    }

    private function sendToFacilityEmail(Facility $facility): void
    {
        try {
            Mail::to($facility->email)->send(new StandardEmail(
                title:    'Welcome to Custocare — Your Facility is Set Up',
                mailBody: $this->buildFacilityOnlyBody($facility),
                ctaUrl:   self::LOGIN_URL,
                ctaLabel: 'Log In to Custocare',
                isHtml:   true,
            ));

            Log::info('Facility welcome email sent to facility email', [
                'facility_id' => $facility->id,
                'email'       => $facility->email,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send facility welcome email to facility email', [
                'facility_id' => $facility->id,
                'error'       => $e->getMessage(),
            ]);
        }
    }

    private function buildFacilityOnlyBody(Facility $facility): string
    {
        $facilityName = $facility->facility_name;
        $facilityCode = $facility->facility_code;

        return "
        <p style='margin: 0 0 16px 0;'>
            Your healthcare facility <strong>{$facilityName}</strong> has been registered on <strong>Custocare</strong>.
        </p>

        <p style='margin: 0 0 8px 0; font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; color: #6b7280;'>
            Facility Number
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
            '>{$facilityCode}</p>
        </div>

        <p style='margin: 0 0 24px 0; color: #374151;'>
            This is your facility's unique identifier for support, billing, and staff onboarding.
        </p>

        <div style='margin-top: 36px; padding-top: 24px; border-top: 1px solid #e5e7eb;'>
            <p style='margin: 0 0 4px 0; font-size: 15px; color: #374151;'>Warm regards,</p>
            <p style='margin: 0; font-size: 15px; font-weight: 700; color: #111827;'>Custocare Team</p>
        </div>";
    }
}
