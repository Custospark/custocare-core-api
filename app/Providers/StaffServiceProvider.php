<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Repositories\Contracts\StaffRepositoryInterface;
use App\Repositories\Staff\StaffRepository;
use App\Services\Contracts\StaffServiceInterface;
use App\Services\Staff\StaffService;

class StaffServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Bind Repository interface to implementation
        $this->app->bind(
            StaffRepositoryInterface::class,
            StaffRepository::class
        );
        
        // Bind Service interface to implementation
        $this->app->bind(
            StaffServiceInterface::class,
            StaffService::class
        );
        
        // Register policies
        $this->registerPolicies();
    }
    
    /**
     * Register authorization policies.
     */
    protected function registerPolicies(): void
    {
        // Policies are auto-discovered in Laravel, but we can explicitly register them
        // This ensures they're available even if auto-discovery is disabled
        \Illuminate\Support\Facades\Gate::policy(\App\Models\Staff::class, \App\Policies\StaffPolicy::class);
    }
    
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish configuration files if needed
        $this->publishConfigurations();
    }
    
    /**
     * Publish package configurations.
     */
    protected function publishConfigurations(): void
    {
        // Example: php artisan vendor:publish --tag=staff-config
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/staff.php' => config_path('staff.php'),
            ], 'staff-config');
        }
    }
}