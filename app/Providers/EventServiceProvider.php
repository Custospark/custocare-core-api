<?php
// app/Providers/EventServiceProvider.php

declare(strict_types=1);

namespace App\Providers;

use App\Events\EmailVerificationRequested;
use App\Events\FacilityRegistered;
use App\Events\MfaRequired;
use App\Events\PasswordChanged;
use App\Events\PasswordResetRequested;
use App\Events\PatientRegistered;
use App\Events\StaffRegistered;
use App\Events\UserEmailVerified;
use App\Listeners\SendPasswordResetNotification;
use App\Listeners\SendEmailVerificationNotification;
use App\Listeners\SendFacilityRegisteredNotification;
use App\Listeners\SendMfaRequiredNotification;
use App\Listeners\SendPatientWelcomeNotification;
use App\Listeners\SendStaffRegisteredNotification;
use App\Listeners\SendUserWelcomeNotification;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event-to-listener mappings for the application
     *
     * @var array<class-string, array<class-string>>
     */
    protected $listen = [
        // ── Auth events ─────────────────────────────────────────────────────
        EmailVerificationRequested::class => [
            SendEmailVerificationNotification::class,
        ],

        PasswordResetRequested::class => [
            SendPasswordResetNotification::class,
        ],

        PasswordChanged::class => [
            SendPasswordResetNotification::class,
        ],

        MfaRequired::class => [
            SendMfaRequiredNotification::class,
        ],
            \App\Events\UserStatusChanged::class => [
            \App\Listeners\SendUserStatusChangeNotification::class,
        ],
        
        \App\Events\FacilityStatusChanged::class => [
            \App\Listeners\SendFacilityStatusChangeNotification::class,
        ],

        // ── Onboarding / welcome events ──────────────────────────────────
        StaffRegistered::class => [
            SendStaffRegisteredNotification::class,
        ],

        FacilityRegistered::class => [
            SendFacilityRegisteredNotification::class,
        ],

        UserEmailVerified::class => [
            SendUserWelcomeNotification::class,
        ],

        PatientRegistered::class => [
            SendPatientWelcomeNotification::class,
        ],

        // ── Laravel built-in (keep if using standard email verification) ───
        // Registered::class => [
        //     LaravelVerificationNotification::class,
        // ],
    ];

    /**
     * Register any events for the application.
     */
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
