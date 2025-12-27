<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Repositories\Contracts\VisitActorRepositoryInterface;
use App\Repositories\VisitActor\VisitActorRepository;
use App\Services\Contracts\VisitActorServiceInterface;
use App\Services\VisitActor\VisitActorService;
use Illuminate\Support\Facades\Gate;

class VisitActorServiceProvider extends ServiceProvider
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
            VisitActorRepositoryInterface::class,
            VisitActorRepository::class
        );

        // Bind service interface to implementation
        $this->app->bind(
            VisitActorServiceInterface::class,
            VisitActorService::class
        );

        // Register policy
        $this->app->singleton(\App\Policies\VisitActorPolicy::class, function ($app) {
            return new \App\Policies\VisitActorPolicy();
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(): void
    {
        // Register policy
        Gate::policy(\App\Models\VisitActor::class, \App\Policies\VisitActorPolicy::class);

        // Publish configuration if needed
        // $this->publishes([
        //     __DIR__ . '/../config/visitactor.php' => config_path('visitactor.php'),
        // ], 'visitactor-config');

        // Load routes if modular routing is used
        // $this->loadRoutesFrom(__DIR__ . '/../routes/visitactor.php');
    }
}