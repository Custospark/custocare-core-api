<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class PatientConsentServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        // Bind repository interface to implementation
        $this->app->bind(
            \App\Repositories\Contracts\PatientConsentRepositoryInterface::class,
            \App\Repositories\PatientConsent\PatientConsentRepository::class
        );

        // Bind service interface to implementation
        $this->app->bind(
            \App\Services\Contracts\PatientConsentServiceInterface::class,
            \App\Services\PatientConsent\PatientConsentService::class
        );
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        // Register policies
        \Illuminate\Support\Facades\Gate::policy(
            \App\Models\PatientConsent::class,
            \App\Policies\PatientConsentPolicy::class
        );
    }
}