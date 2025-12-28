<?php

namespace App\Providers;

use App\Repositories\Contracts\ServiceCatalogRepositoryInterface;
use App\Repositories\ServiceCatalog\ServiceCatalogRepository;
use App\Services\Contracts\ServiceCatalogServiceInterface;
use App\Services\ServiceCatalog\ServiceCatalogService;
use Illuminate\Support\ServiceProvider;

class ServiceCatalogServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
        // Register Repository binding
        $this->app->bind(
            ServiceCatalogRepositoryInterface::class,
            ServiceCatalogRepository::class
        );

        // Register Service binding
        $this->app->bind(
            ServiceCatalogServiceInterface::class,
            ServiceCatalogService::class
        );

        // Register additional bindings if needed
        $this->app->when(ServiceCatalogService::class)
            ->needs(ServiceCatalogRepositoryInterface::class)
            ->give(ServiceCatalogRepository::class);
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(): void
    {
        // Publish configuration if needed
        // $this->publishes([
        //     __DIR__.'/../config/service-catalog.php' => config_path('service-catalog.php'),
        // ], 'service-catalog-config');

        // Load routes
        // $this->loadRoutesFrom(__DIR__ . '/../routes/service-catalog.php');

        // Load views if needed
        // $this->loadViewsFrom(__DIR__.'/../resources/views', 'service-catalog');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        // Register commands if needed
        // $this->commands([
        //     // Your commands here
        // ]);
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            ServiceCatalogRepositoryInterface::class,
            ServiceCatalogServiceInterface::class,
        ];
    }
}