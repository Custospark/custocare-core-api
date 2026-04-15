<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\UserStatusChanged;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendUserStatusChangeNotification implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private readonly NotificationService $notificationService
    ) {}

    public function handle(UserStatusChanged $event): void
    {
        $affectedUser = $event->user;
        $oldStatus = $event->oldStatus;
        $newStatus = $event->newStatus;
        $reason = $event->reason;
        $changedByStaffId = $event->changedByStaffId;

        // Get the admin who made the change for personalization
        $changedBy = null;
        try {
            $changedByStaff = DB::table('staff')
                ->join('users', 'staff.user_id', '=', 'users.id')
                ->where('staff.id', $changedByStaffId)
                ->select('users.first_name', 'users.last_name', 'users.display_name')
                ->first();
            
            if ($changedByStaff) {
                $changedBy = $changedByStaff->display_name ?? trim($changedByStaff->first_name . ' ' . $changedByStaff->last_name);
            }
        } catch (\Exception $e) {
            Log::warning('Could not fetch admin details', ['error' => $e->getMessage()]);
        }

        // Get the decrypted email for the user
        $email = null;
        try {
            $email = decrypt($affectedUser->email_encrypted);
        } catch (\Exception $e) {
            Log::warning('Failed to decrypt user email', [
                'user_id' => $affectedUser->id,
                'error' => $e->getMessage()
            ]);
            return;
        }

        if (!$email) {
            Log::info('No email found for user, skipping notification', [
                'user_id' => $affectedUser->id
            ]);
            return;
        }

        $personalizedBody = $this->buildPersonalizedEmailBody(
            $affectedUser,
            $oldStatus,
            $newStatus,
            $reason,
            $changedBy
        );

        // Send email notification
        $this->notificationService->sendToUser(
            user: $affectedUser,
            title: $this->getTitle($newStatus, $affectedUser),
            body: $personalizedBody,
            type: 'user_status_change',
            channel: 'email'
        );
    }

    private function getTitle(string $newStatus, User $user): string
    {
        $firstName = $this->getFirstName($user);
        
        return match ($newStatus) {
            'suspended' => "Important: {$firstName}, Your Account Has Been Temporarily Suspended",
            'banned' => "Urgent: {$firstName}, Your Account Has Been Permanently Banned",
            'active' => "Good News: {$firstName}, Your Account Has Been Reactivated",
            default => "{$firstName}, Important Update Regarding Your Account",
        };
    }

    private function getFirstName(User $user): string
    {
        if (!empty($user->display_name)) {
            return explode(' ', $user->display_name)[0];
        }
        if (!empty($user->first_name)) {
            return $user->first_name;
        }
        return 'Valued User';
    }

    private function buildPersonalizedEmailBody(
        User $user,
        string $oldStatus,
        string $newStatus,
        ?string $reason,
        ?string $changedBy
    ): string {
        $firstName = $this->getFirstName($user);
        $fullName = trim($user->first_name . ' ' . $user->last_name) ?: 'Valued User';
        $appName = config('app.name');
        $currentTime = now()->format('l, F j, Y \a\t g:i A T');
        $supportEmail = 'support@custocare.com';
        $supportPhone = '+256 756 697 871';

        // Personalized opening based on status change
        $openings = [
            'suspended' => "I hope this message finds you well. After a thorough review, we've had to take action regarding your account.",
            'banned' => "I'm writing to you today with difficult news regarding your {$appName} account.",
            'active' => "Great news! I'm pleased to inform you that we've reinstated access to your {$appName} account.",
        ];

        $opening = $openings[$newStatus] ?? "We're writing to update you about your {$appName} account.";

        $statusMessages = [
            'suspended' => [
                'title' => 'Temporary Suspension',
                'description' => "After careful consideration, your {$appName} account has been temporarily suspended.",
                'action' => 'We genuinely want to work with you to resolve this matter. Please review the reason below and reach out to our support team so we can help you restore full access.',
                'urgency' => 'This is a temporary measure while we work together to address the concerns.',
            ],
            'banned' => [
                'title' => 'Permanent Ban',
                'description' => "After multiple reviews and careful consideration, we've made the difficult decision to permanently ban your {$appName} account.",
                'action' => 'We understand this is disappointing news. If you believe this decision was made in error, you may appeal by contacting our support team within 14 days.',
                'urgency' => 'This action is permanent unless a successful appeal is made.',
            ],
            'active' => [
                'title' => 'Account Reactivated',
                'description' => "We're happy to inform you that full access to your {$appName} account has been restored.",
                'action' => 'You can now log in and access all features normally. We appreciate your cooperation in resolving the previous issues.',
                'urgency' => 'No immediate action is required, but we encourage you to review our platform guidelines to prevent future disruptions.',
            ],
        ];

        $statusInfo = $statusMessages[$newStatus] ?? [
            'title' => 'Status Updated',
            'description' => "Your account status has been changed from '{$oldStatus}' to '{$newStatus}'.",
            'action' => 'If you have questions, please reach out to our support team.',
            'urgency' => 'Please review this change at your earliest convenience.',
        ];

        // Build reason section with empathy
        $reasonHtml = '';
        if ($reason) {
            $reasonHtml = "
            <div style='
                background-color: #fffbeb;
                border-left: 4px solid #f59e0b;
                border-radius: 8px;
                padding: 20px 24px;
                margin: 24px 0;
            '>
                <p style='margin: 0 0 8px 0; font-size: 13px; font-weight: 700; color: #92400e; letter-spacing: 0.5px;'>
                    📋 Reason for This Action
                </p>
                <p style='margin: 0; font-size: 15px; color: #78350f; line-height: 1.6;'>
                    \"{$reason}\"
                </p>
                " . ($changedBy ? "<p style='margin: 12px 0 0 0; font-size: 12px; color: #92400e;'>— Action taken by: {$changedBy}</p>" : "") . "
            </div>";
        }

        // Next steps section with empathy
        $nextStepsHtml = "
        <div style='
            background: linear-gradient(135deg, #eff6ff 0%, #f0fdf4 100%);
            border-radius: 12px;
            padding: 24px;
            margin: 24px 0;
        '>
            <p style='margin: 0 0 12px 0; font-size: 16px; font-weight: 700; color: #1e40af;'>
                📋 What Happens Next?
            </p>
            <p style='margin: 0 0 8px 0; font-size: 15px; color: #374151; line-height: 1.6;'>
                {$statusInfo['action']}
            </p>
            <p style='margin: 12px 0 0 0; font-size: 14px; color: #6b7280;'>
                ⏱ {$statusInfo['urgency']}
            </p>
        </div>";

        // Support section with personal touch
        $supportHtml = "
        <div style='
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 24px;
            margin: 24px 0;
            text-align: center;
        '>
            <p style='margin: 0 0 12px 0; font-size: 28px;'>
                💬
            </p>
            <p style='margin: 0 0 8px 0; font-size: 16px; font-weight: 700; color: #111827;'>
                We're Here to Help, {$firstName}
            </p>
            <p style='margin: 0 0 16px 0; font-size: 14px; color: #6b7280;'>
                Our support team is available to answer your questions and guide you through this process.
            </p>
            <div style='
                display: inline-block;
                background: white;
                border: 1px solid #d1d5db;
                border-radius: 8px;
                padding: 12px 20px;
                margin-top: 8px;
            '>
                <p style='margin: 0; font-size: 14px; color: #374151;'>
                    📧 <a href='mailto:{$supportEmail}' style='color: #2563eb; text-decoration: none; font-weight: 600;'>{$supportEmail}</a>
                    &nbsp;&nbsp;|&nbsp;&nbsp;
                    📞 <span style='color: #374151;'>{$supportPhone}</span>
                </p>
            </div>
        </div>";

        return "
        <p style='margin: 0 0 20px 0; font-size: 16px; color: #374151; line-height: 1.6;'>
            Dear <strong>{$firstName}</strong>,
        </p>

        <p style='margin: 0 0 16px 0; font-size: 15px; color: #374151; line-height: 1.6;'>
            {$opening}
        </p>

        <div style='
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 28px;
            margin: 24px 0;
        '>
            <div style='text-align: center; margin-bottom: 20px;'>
                <div style='
                    display: inline-block;
                    background: " . ($newStatus === 'active' ? '#dcfce7' : ($newStatus === 'suspended' ? '#fef9c3' : '#fee2e2')) . ";
                    padding: 8px 16px;
                    border-radius: 30px;
                    font-size: 12px;
                    font-weight: 700;
                    color: " . ($newStatus === 'active' ? '#166534' : ($newStatus === 'suspended' ? '#854d0e' : '#991b1b')) . ";
                '>
                    " . strtoupper($statusInfo['title']) . "
                </div>
            </div>

            <p style='margin: 0 0 8px 0; font-size: 22px; font-weight: 700; color: #0f172a; text-align: center;'>
                {$fullName}
            </p>

            <p style='margin: 20px 0 0 0; font-size: 15px; color: #334155; line-height: 1.6; text-align: center;'>
                {$statusInfo['description']}
            </p>

            <p style='margin: 12px 0 0 0; font-size: 13px; color: #64748b; text-align: center;'>
                Action Date: {$currentTime}
            </p>
        </div>

        {$reasonHtml}
        {$nextStepsHtml}
        {$supportHtml}

        <div style='margin-top: 32px; padding-top: 24px; border-top: 1px solid #e5e7eb;'>
            <p style='margin: 0 0 8px 0; font-size: 14px; color: #6b7280;'>
                Thank you for your understanding, {$firstName}.
            </p>
            <p style='margin: 0; font-size: 14px; color: #6b7280;'>
                Warm regards,<br>
                <strong>{$appName} Trust & Safety Team</strong>
            </p>
        </div>";
    }
}