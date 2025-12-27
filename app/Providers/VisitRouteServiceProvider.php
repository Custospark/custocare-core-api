<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class VisitRouteServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(
            \App\Repositories\Contracts\VisitRouteRepositoryInterface::class,
            \App\Repositories\VisitRoute\VisitRouteRepository::class
        );
        
        $this->app->bind(
            \App\Services\Contracts\VisitRouteServiceInterface::class,
            \App\Services\VisitRoute\VisitRouteService::class
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register policies if needed
        Gate::policy(\App\Models\VisitRoute::class, \App\Policies\VisitRoutePolicy::class);
        
        // Publish configuration if needed
        $this->publishes([
            __DIR__ . '/../config/visit_routes.php' => config_path('visit_routes.php'),
        ], 'visit-routes-config');
        
        // Load routes
        // $this->loadRoutesFrom(__DIR__ . '/../routes/visit_routes.php');
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            \App\Repositories\Contracts\VisitRouteRepositoryInterface::class,
            \App\Services\Contracts\VisitRouteServiceInterface::class,
        ];
    }
}