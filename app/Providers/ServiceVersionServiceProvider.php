<?php

namespace App\Providers;

use App\Repositories\Contracts\ServiceVersionRepositoryInterface;
use App\Repositories\ServiceVersion\ServiceVersionRepository;
use App\Services\Contracts\ServiceVersionServiceInterface as ContractsServiceVersionServiceInterface;
use App\Services\ServiceVersion\ServiceVersionService;
use Illuminate\Support\ServiceProvider;

/**
 * ServiceVersionServiceProvider
 * 
 * Registers ServiceVersion service and repository bindings for dependency injection.
 * Enables modular architecture and easy testing.
 */
class ServiceVersionServiceProvider extends ServiceProvider
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
            ServiceVersionRepositoryInterface::class,
            ServiceVersionRepository::class
        );

        // Bind Service interface to implementation
        $this->app->bind(
            ContractsServiceVersionServiceInterface::class,
            ServiceVersionService::class
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
        $this->registerPolicies();
        
        // Publish configuration if needed
        $this->publishConfigurations();
    }

    /**
     * Register policies for authorization.
     *
     * @return void
     */
    protected function registerPolicies(): void
    {
        // Policies are automatically discovered in Laravel 8+
        // This method is kept for compatibility and future extensions
    }

    /**
     * Publish package configurations.
     *
     * @return void
     */
    protected function publishConfigurations(): void
    {
        // If this was a package, we would publish configurations here
        // For now, it's kept as a placeholder for future extensibility
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            ServiceVersionRepositoryInterface::class,
            ServiceVersionService::class,
        ];
    }
}