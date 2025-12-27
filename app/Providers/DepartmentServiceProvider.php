<?php

namespace App\Providers;

use App\Repositories\Contracts\DepartmentRepositoryInterface;
use App\Repositories\Department\DepartmentRepository;
use App\Services\Contracts\DepartmentServiceInterface;
use App\Services\Department\DepartmentService;
use Illuminate\Support\ServiceProvider;

class DepartmentServiceProvider extends ServiceProvider
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
            DepartmentRepositoryInterface::class,
            DepartmentRepository::class
        );

        // Bind Service interface to implementation
        $this->app->bind(
            DepartmentServiceInterface::class,
            DepartmentService::class
        );

        // Register additional department-related services if needed
        $this->registerAdditionalServices();
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(): void
    {
        // Publish configuration if needed
        $this->publishConfigurations();

        // Register middleware if needed
        $this->registerMiddleware();

        // Register views if needed
        $this->loadViews();

        // Register translations if needed
        $this->loadTranslations();
    }

    /**
     * Register additional department-related services.
     *
     * @return void
     */
    private function registerAdditionalServices(): void
    {
        // Example: Register a DepartmentCapacityService if needed
        // $this->app->singleton(DepartmentCapacityService::class, function ($app) {
        //     return new DepartmentCapacityService(
        //         $app->make(DepartmentRepositoryInterface::class)
        //     );
        // });
    }

    /**
     * Publish package configurations.
     *
     * @return void
     */
    private function publishConfigurations(): void
    {
        // Example: Publish configuration file
        // $this->publishes([
        //     __DIR__.'/../config/department.php' => config_path('department.php'),
        // ], 'department-config');
    }

    /**
     * Register middleware.
     *
     * @return void
     */
    private function registerMiddleware(): void
    {
        // Example: Register middleware
        // $router = $this->app['router'];
        // $router->aliasMiddleware('department.access', DepartmentAccessMiddleware::class);
    }

    /**
     * Load views for the department module.
     *
     * @return void
     */
    private function loadViews(): void
    {
        // $this->loadViewsFrom(__DIR__.'/../resources/views', 'department');
    }

    /**
     * Load translations for the department module.
     *
     * @return void
     */
    private function loadTranslations(): void
    {
        // $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'department');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides(): array
    {
        return [
            DepartmentRepositoryInterface::class,
            DepartmentServiceInterface::class,
        ];
    }
}