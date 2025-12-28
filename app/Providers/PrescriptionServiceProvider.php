<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Contracts\PrescriptionServiceInterface;
use App\Services\Prescription\PrescriptionService;
use App\Repositories\Contracts\PrescriptionRepositoryInterface;
use App\Repositories\Prescription\PrescriptionRepository;

class PrescriptionServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
        // Bind Repository Interface to Implementation
        $this->app->bind(
            PrescriptionRepositoryInterface::class,
            PrescriptionRepository::class
        );

        // Bind Service Interface to Implementation
        $this->app->bind(
            PrescriptionServiceInterface::class,
            PrescriptionService::class
        );

        // Register Facade if needed (optional)
        // $this->app->singleton('prescription.service', function ($app) {
        //     return $app->make(PrescriptionServiceInterface::class);
        // });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/prescription.php' => config_path('prescription.php'),
        ], 'prescription-config');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        
        // Load routes
        // $this->loadRoutesFrom(__DIR__ . '/../../routes/prescription.php');
    }
}