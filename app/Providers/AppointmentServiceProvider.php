<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Repositories\Contracts\AppointmentRepositoryInterface;
use App\Repositories\Appointment\AppointmentRepository;
use App\Services\Contracts\AppointmentServiceInterface;
use App\Services\Appointment\AppointmentService;

class AppointmentServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Bind repository interface to implementation
        $this->app->bind(
            AppointmentRepositoryInterface::class,
            AppointmentRepository::class
        );

        // Bind service interface to implementation
        $this->app->bind(
            AppointmentServiceInterface::class,
            AppointmentService::class
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register policies if not using auto-discovery
        $this->registerPolicies();
    }

    /**
     * Register policies for the appointment module
     */
    private function registerPolicies(): void
    {
        // Policies are typically auto-discovered, but we can register them here if needed
        // Gate::policy(Appointment::class, AppointmentPolicy::class);
    }
}