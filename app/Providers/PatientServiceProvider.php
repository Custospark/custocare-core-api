<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class PatientServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Bind Repository interface to implementation
        $this->app->bind(
            \App\Repositories\Contracts\PatientRepositoryInterface::class,
            \App\Repositories\Patient\PatientRepository::class
        );

        // Bind Service interface to implementation
        $this->app->bind(
            \App\Services\Contracts\PatientServiceInterface::class,
            \App\Services\Patient\PatientService::class
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register policies if not using auto-discovery
        // Or add additional boot logic here
    }
}