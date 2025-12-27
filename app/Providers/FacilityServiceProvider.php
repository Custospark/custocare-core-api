<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Repositories\Contracts\FacilityRepositoryInterface;
use App\Repositories\Facility\FacilityRepository;
use App\Services\Contracts\FacilityServiceInterface;
use App\Services\Facility\FacilityService;

/**
 * Class FacilityServiceProvider
 * 
 * Service provider for Facility module.
 * Registers repository and service bindings.
 */
class FacilityServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
        // Bind Repository interface to implementation
        $this->app->bind(
            FacilityRepositoryInterface::class,
            FacilityRepository::class
        );
        
        // Bind Service interface to implementation
        $this->app->bind(
            FacilityServiceInterface::class,
            FacilityService::class
        );
        
        // Register facility-specific configurations
        $this->registerConfigurations();
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(): void
    {
        // Publish configuration files if needed
        $this->publishConfigurations();
        
        // Register policies
        $this->registerPolicies();
        
        // Register API routes
        // $this->loadRoutesFrom(__DIR__ . '/../../routes/api.php');
    }

    /**
     * Register facility-specific configurations.
     *
     * @return void
     */
    private function registerConfigurations(): void
    {
        // Merge facility-specific config with main app config
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/facility.php',
            'facility'
        );
    }

    /**
     * Publish configuration files.
     *
     * @return void
     */
    private function publishConfigurations(): void
    {
        // if ($this->app->runningInConsole()) {
        //     $this->publishes([
        //         __DIR__ . '/../../config/facility.php' => config_path('facility.php'),
        //     ], 'facility-config');
        // }
    }

    /**
     * Register policies.
     *
     * @return void
     */
    private function registerPolicies(): void
    {
        // Policies are automatically discovered in Laravel
        // This method is kept for future extensions
    }
}