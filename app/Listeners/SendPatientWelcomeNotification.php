<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\PatientRegistered;
use App\Models\Patient;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class SendPatientWelcomeNotification implements ShouldHandleEventsAfterCommit
{
    private const LOGIN_URL = 'https://custocare.custospark.com/login';

    public function __construct(
        private readonly NotificationService $notificationService
    ) {}

    public function handle(PatientRegistered $event): void
    {
        $this->notificationService->sendToUser(
            user:     $event->user,
            title:    'Welcome to Custocare — Your Patient Portal is Ready',
            body:     $this->buildBody($event->patient, $event->user),
            type:     'patient_welcome',
            channel:  'email',
            ctaUrl:   self::LOGIN_URL,
            ctaLabel: 'Log In to Custocare',
        );
    }

    private function buildBody(Patient $patient, User $user): string
    {
        $firstName   = $user->first_name ?? 'Valued Patient';
        $patientUuid = $patient->patient_uuid;

        return "
        <p style='margin: 0 0 20px 0; color: #374151;'>Dear {$firstName},</p>

        <p style='margin: 0 0 16px 0;'>
            Welcome to <strong>Custocare</strong>! Your patient portal is now active and ready to use.
        </p>

        <p style='margin: 0 0 8px 0; font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; color: #6b7280;'>
            Your Patient Number
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
            '>{$patientUuid}</p>
        </div>

        <p style='margin: 0 0 24px 0; color: #374151;'>
            Keep this number handy — you may need it when visiting facilities or speaking with support.
            Share it with your healthcare providers so they can easily locate your records.
        </p>

        <p style='margin: 0 0 8px 0; color: #374151;'><strong>Here's what you can do with your portal:</strong></p>
        <ul style='margin: 0 0 24px 0; padding-left: 20px; color: #4b5563; line-height: 2;'>
            <li><strong>View your health records</strong> — Access your medical history, lab results, and prescriptions.</li>
            <li><strong>Book appointments</strong> — Schedule visits with your healthcare providers at your convenience.</li>
            <li><strong>Secure messaging</strong> — Communicate with your care team.</li>
            <li><strong>Manage prescriptions</strong> — View and request prescription refills.</li>
        </ul>

        <p style='margin: 0 0 8px 0; color: #374151;'><strong>Getting started:</strong></p>
        <ol style='margin: 0 0 24px 0; padding-left: 20px; color: #4b5563; line-height: 2;'>
            <li>Log in to your account.</li>
            <li>Complete your health profile.</li>
            <li>Access your lab results, appointments, and medical history for all visits across all facilities.</li>
        </ol>

        <p style='margin: 0 0 24px 0; color: #374151;'>
            If you have any questions, our support team is happy to help.
        </p>

        <div style='margin-top: 36px; padding-top: 24px; border-top: 1px solid #e5e7eb;'>
            <p style='margin: 0 0 4px 0; font-size: 15px; color: #374151;'>Warm regards,</p>
            <p style='margin: 0; font-size: 15px; font-weight: 700; color: #111827;'>Custocare Team</p>
        </div>";
    }
}
