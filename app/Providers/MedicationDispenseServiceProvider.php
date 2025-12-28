<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class MedicationDispenseServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
        // Bind repository interface to implementation
        $this->app->bind(
            \App\Repositories\Contracts\MedicationDispenseRepositoryInterface::class,
            \App\Repositories\MedicationDispense\MedicationDispenseRepository::class
        );

        // Bind service interface to implementation
        $this->app->bind(
            \App\Services\Contracts\MedicationDispenseServiceInterface::class,
            \App\Services\MedicationDispense\MedicationDispenseService::class
        );
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(): void
    {
        // Register policy
        Gate::policy(\App\Models\MedicationDispense::class, \App\Policies\MedicationDispensePolicy::class);

        // Publish configuration if needed
        // $this->publishes([
        //     __DIR__.'/../config/medication_dispense.php' => config_path('medication_dispense.php'),
        // ], 'medication-dispense-config');

        // Load routes if modular approach is used
        // $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
    }
}