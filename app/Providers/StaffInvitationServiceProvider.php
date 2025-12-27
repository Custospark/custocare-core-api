<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class StaffInvitationServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Bind Repository Interface to Implementation
        $this->app->bind(
            \App\Repositories\Contracts\StaffInvitationRepositoryInterface::class,
            \App\Repositories\StaffInvitation\StaffInvitationRepository::class
        );

        // Bind Service Interface to Implementation
        $this->app->bind(
            \App\Services\Contracts\StaffInvitationServiceInterface::class,
            \App\Services\StaffInvitation\StaffInvitationService::class
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register policy
        Gate::policy(\App\Models\StaffInvitation::class, \App\Policies\StaffInvitationPolicy::class);

        // Publish configuration if needed
        $this->publishes([
            __DIR__.'/../config/staff_invitations.php' => config_path('staff_invitations.php'),
        ], 'staff-invitations-config');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }
}